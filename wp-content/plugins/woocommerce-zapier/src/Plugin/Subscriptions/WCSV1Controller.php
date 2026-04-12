<?php

namespace OM4\WooCommerceZapier\Plugin\Subscriptions;

use Exception;
use OM4\WooCommerceZapier\Helper\FeatureChecker;
use OM4\WooCommerceZapier\Logger;
use OM4\WooCommerceZapier\Plugin\Subscriptions\SubscriptionsTaskCreator;
use OM4\WooCommerceZapier\TaskHistory\Listener\APIListenerTrait;
use WC_Data_Exception;
use WC_REST_Posts_Controller;
use WC_REST_Subscriptions_V1_Controller;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Allows the WooCommerce Subscriptions REST API v1 Subscription endpoint methods to
 * be use directly by our V1 Subscriptions Controller.
 *
 * @since 2.7.0
 *
 * @internal
 */
class WCSV1Controller extends WC_REST_Subscriptions_V1_Controller {

	use APIListenerTrait;

	/**
	 * Resource Type (used for Task History items).
	 *
	 * @var string
	 */
	protected $resource_type = 'subscription';

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * SubscriptionsTaskCreator instance.
	 *
	 * @var SubscriptionsTaskCreator
	 */
	protected $task_creator;

	/**
	 * Feature Checker instance.
	 *
	 * @var FeatureChecker
	 */
	protected $checker;

	/**
	 * Constructor.
	 *
	 * Not calling parent constructor to avoid add_filter() calls in \WC_REST_Subscriptions_V1_Controller::__construct().
	 *
	 * @param  Logger                   $logger        Logger instance.
	 * @param  SubscriptionsTaskCreator $task_creator  SubscriptionsTaskCreator instance.
	 * @param  FeatureChecker           $checker       FeatureChecker instance.
	 */
	public function __construct( Logger $logger, SubscriptionsTaskCreator $task_creator, FeatureChecker $checker ) {
		$this->logger       = $logger;
		$this->task_creator = $task_creator;
		$this->checker      = $checker;
		$this->add_filter_to_check_for_request_validation_error();
	}

	/**
	 * Update a subscription.
	 *
	 * This is a duplicate of WC_REST_Posts_Controller::update_item(), but with several changes:
	 *
	 * - The post type check modified to check both 'shop_subscription' and 'shop_order_placehold' types when HPOS is enabled without sync.
	 * - The handle_update_error() method calls are added.
	 * - The handle_update_success() method call is added.
	 * - Exception handling validates HTTP status codes before using them (WooCommerce core calls non-existent $e->getErrorCode() method).
	 *
	 * This change ensures that the update subscription endpoint works reliably with HPOS enabled.
	 *
	 * Based on WooCommerce core version 10.3.4.
	 *
	 * @see WC_REST_Posts_Controller::update_item()
	 *
	 * @since 2.18.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_item( $request ) {
		try {
			$post_id = (int) $request['id'];

			// Determine expected post type for error messages.
			// When HPOS is enabled without sync, use placeholder type for consistency with WooCommerce's expectations.
			$expected_post_type = ( $this->checker->is_hpos_enabled() && ! $this->checker->is_hpos_in_sync() )
				? $this->checker->hpos_placeholder_order_post_type()
				: $this->post_type;

			// When HPOS is enabled without sync, accept both shop_subscription and shop_order_placehold post types.
			$valid_post_types = ( $this->checker->is_hpos_enabled() && ! $this->checker->is_hpos_in_sync() )
				? array( 'shop_subscription', $this->checker->hpos_placeholder_order_post_type() )
				: array( $this->post_type );

			if ( empty( $post_id ) || ! in_array( get_post_type( $post_id ), $valid_post_types, true ) ) {
				$response = new WP_Error( "woocommerce_rest_{$expected_post_type}_invalid_id", __( 'ID is invalid.', 'woocommerce-zapier' ), array( 'status' => 400 ) );
				$this->handle_update_error( $request, $response );
				return $response;
			}

			$subscription_id = $this->update_order( $request );
			if ( is_wp_error( $subscription_id ) ) {
				$this->handle_update_error( $request, $subscription_id );
				return $subscription_id;
			}

			/**
			 * A post object is returned, never null because of the get_post_type() call above.
			 *
			 * @var WP_Post $post
			 */
			$post = get_post( $subscription_id );
			if ( ! $post instanceof WP_Post ) {
				$response = new WP_Error( "woocommerce_rest_{$expected_post_type}_invalid_id", __( 'ID is invalid.', 'woocommerce-zapier' ), array( 'status' => 400 ) );
				$this->handle_update_error( $request, $response );
				return $response;
			}

			$this->update_additional_fields_for_object( $post, $request );

			/**
			 * Fires after a single item is created or updated via the REST API.
			 *
			 * @param WP_Post         $post      Post object.
			 * @param WP_REST_Request $request   Request object.
			 * @param boolean         $creating  True when creating item, false when updating.
			 */
			do_action( "woocommerce_rest_insert_{$this->post_type}", $post, $request, false );
			$request->set_param( 'context', 'edit' );
			$response = $this->prepare_item_for_response( $post, $request );
			$response = rest_ensure_response( $response );
			$this->handle_update_success( $response );
			return $response;

		} catch ( Exception $e ) {
			$code   = $e->getCode();
			$status = ( $code >= 400 && $code < 600 ) ? $code : 400;
			return new WP_Error( 'woocommerce_rest_cannot_update', $e->getMessage(), array( 'status' => $status ) );
		}
	}

	/**
	 * Prepare a single subscription for update.
	 *
	 * Wraps parent::update_order() with exception handling to work around a WooCommerce core bug
	 * where WC_REST_Orders_V1_Controller::update_item() catches Exception and calls getErrorCode(),
	 * but standard PHP Exception objects don't have this method (only WC_Data_Exception does).
	 *
	 * This catches exceptions thrown during the update_order() flow (e.g., WCS 8.1.0+ invalid status transitions)
	 * before they reach WooCommerce's buggy error handler, allowing the APIListenerTrait to process them properly.
	 *
	 * @since 2.17.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_Error|mixed Prepared subscription object or WP_Error on failure.
	 * @throws WC_Data_Exception If a WC_Data_Exception is thrown by parent (passes through).
	 */
	protected function update_order( $request ) {
		try {
			return parent::update_order( $request );
		} catch ( WC_Data_Exception $e ) {
			// WC_Data_Exception has getErrorCode(), let it pass through normally.
			throw $e;
		} catch ( Exception $e ) {
			// Standard PHP Exception doesn't have getErrorCode(), which causes WooCommerce's
			// error handler to fatal. Convert to WP_Error instead.
			// This handles WCS 8.1.0+ which throws standard Exceptions for invalid status transitions.
			$code   = $e->getCode();
			$status = ( $code >= 400 && $code < 600 ) ? $code : 400;
			return new WP_Error(
				'woocommerce_rest_cannot_update',
				$e->getMessage(),
				array( 'status' => $status )
			);
		}
	}
}
