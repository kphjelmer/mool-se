<?php

namespace OM4\WooCommerceZapier\TaskHistory\Listener;

use OM4\WooCommerceZapier\Exception\InvalidImplementationException;
use OM4\WooCommerceZapier\TaskHistory\Task\Event;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Improves create/update REST API requests so that they are recorded in our task history,
 * and also recorded and logged if there is an error with the request.
 *
 * Delete API requests aren't currently supported in the Zapier App, so if a delete request
 * occurs then log it.
 *
 * @since 2.0.0
 */
trait APIListenerTrait {

	/**
	 * Add a filter to check for request validation errors.
	 *
	 * @since 2.10.0
	 *
	 * @return void
	 */
	protected function add_filter_to_check_for_request_validation_error() {
		\add_filter(
			'rest_post_dispatch',
			array( $this, 'rest_post_dispatch_check_for_request_validation_error' ),
			10,
			3
		);
	}

	/**
	 * Item Create.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_REST_Response REST API Response.
	 */
	public function create_item( $request ) {
		$response = parent::create_item( $request );
		if ( \is_wp_error( $response ) ) {
			$this->log_error_response( $request, $response );
			$this->task_creator->record(
				Event::action_create( $this->resource_type, $response ),
				0
			);
			return $response;
		}

		// @phpstan-ignore-next-line Structure comes from WooCommerce.
		$this->task_creator->record( Event::action_create( $this->resource_type ), $response->data['id'] );
		return $response;
	}

	/**
	 * Item Delete.
	 *
	 * @uses WP_REST_Controller::delete_item() as parent::delete_item() Delete a single item.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function delete_item( $request ) {
		/**
		 * Deletes an item.
		 *
		 * Return type differs from docblock. Despite the docblock indicating that
		 * the return type might be a boolean, the actual implementation in
		 * WP_REST_Controller::delete_item() does not ever return a boolean.
		 *
		 * @var WP_Error|WP_REST_Response $response
		 */
		$response = parent::delete_item( $request );
		if ( \is_wp_error( $response ) ) {
			$this->log_error_response( $request, $response );
			return $response;
		}
		$this->log_unsupported_access( $request );
		return $response;
	}

	/**
	 * Item update.
	 *
	 * @uses WP_REST_Controller::update_item() as parent::update_item() Update a single item.

	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_item( $request ) {
		$response = parent::update_item( $request );
		if ( \is_wp_error( $response ) ) {
			return $this->handle_update_error( $request, $response );
		}
		$this->handle_update_success( $response );

		return $response;
	}

	/**
	 * Log a REST API response error.
	 *
	 * @param WP_REST_Request           $request REST API Request.
	 * @param WP_Error|WP_REST_Response $error REST API Error Response.
	 *
	 * @return void
	 *
	 * @throws InvalidImplementationException If the response is not a WP_Error instance or an array with the expected WP_Error structure.
	 */
	protected function log_error_response( $request, $error ) {
		if ( ! \is_wp_error( $error ) ) {
			$data = $error->get_data();
			if (
				is_array( $data ) &&
				isset( $data['code'] ) &&
				isset( $data['message'] ) &&
				isset( $data['data']['status'] )
			) {
				// A WP_Error style response.
				$error = new WP_Error(
					$data['code'],
					$data['message']
				);
			} else {
				// Should not happen.
				throw new InvalidImplementationException( 'Invalid error response type' );
			}
		}

		$this->logger->error(
			'REST API Error Response for Request Route: %s. Request Method: %s. Resource Type: %s. Error Code: %s. Error Message: %s',
			array(
				$request->get_route(),
				$request->get_method(),
				$this->resource_type,
				$error->get_error_code(),
				$error->get_error_message(),
			)
		);
	}

	/**
	 * Log unsupported REST API access.
	 *
	 * @param WP_REST_Request $request REST API Request.
	 *
	 * @return void
	 */
	protected function log_unsupported_access( $request ) {
		$this->logger->error(
			'Unsupported REST API access on ID %d, resource: %s, message: %s',
			array(
				$request['id'],
				$this->resource_type,
				__( 'Deleted via Zapier', 'woocommerce-zapier' ),
			)
		);
	}

	/**
	 * Check if the REST API response is an error, and if so, record it in the task history.
	 *
	 * This is necessary for API request schema validation errors, which are performed by WordPress
	 * without our controller create_item() or update_item() method(s) being called.
	 *
	 * Executed during the `rest_post_dispatch` filter.
	 *
	 * @param WP_REST_Response $response  Result to send to the client. Usually a `WP_REST_Response`.
	 * @param WP_REST_Server   $server  Server instance.
	 * @param WP_REST_Request  $request Request used to generate the response.
	 *
	 * @return WP_REST_Response
	 * @see WP_REST_Request::has_valid_params() Which is where the request validation errors are generated.
	 */
	public function rest_post_dispatch_check_for_request_validation_error( $response, $server, $request ) {
		if ( ! $response->is_error() ) {
			// A "success" response, not an error.
			return $response;
		}

		$route  = $response->get_matched_route();
		$prefix = '/' . $this->namespace . '/' . $this->rest_base;

		if ( 0 !== \strpos( $route, $prefix ) ) {
			// Request URL doesn't match this controller.
			return $response;
		}

		// Check for nested routes: if after the prefix there's another path segment
		// starting with a lowercase letter (e.g., /products/stocks, /orders/notes),
		// it belongs to a nested controller, not this one.
		// Parent controller routes use regex patterns for IDs (e.g., /products/(?P<id>[\d]+)).
		//
		// Pattern notes:
		// - Matches /stocks, /prices, /notes, /some-resource (hyphenated names start with lowercase).
		// - Does NOT match /(?P<id>...) (regex patterns start with parenthesis).
		// - Assumes REST convention of lowercase route segments (uppercase like /API is non-standard).
		$after_prefix = substr( $route, strlen( $prefix ) );
		if ( '' !== $after_prefix && 1 === preg_match( '/^\/[a-z]/', $after_prefix ) ) {
			// Route belongs to a nested controller.
			return $response;
		}

		// For WooCommerce < 10.5, also check the callback instance as a fallback.
		// WooCommerce 10.5+ wraps callbacks in Closures via RestApiCache, so this check won't apply.
		$handler = $response->get_matched_handler();
		if ( is_array( $handler ) && isset( $handler['callback'] ) ) {
			$callback = $handler['callback'];
			if ( is_array( $callback ) && isset( $callback[0] ) && ! ( $callback[0] instanceof $this ) ) {
				// A request for another Controller (not this controller).
				return $response;
			}
		}

		$data = $response->get_data();
		if (
			is_array( $data ) &&
			isset( $data['code'] ) &&
			isset( $data['message'] ) &&
			isset( $data['data']['status'] )
		) {
			if ( ! \is_string( $data['code'] ) || ! \is_string( $data['message'] ) ) {
				// An unexpected WP_Error style response.
				return $response;
			}

			// Only act on WP_Error codes such as `rest_invalid_param`.
			// Other errors codes are not related to WordPress' request validation,
			// and are handled by the controller itself.

			// Note: WooCommerce version 10 changed a `woocommerce_rest_invalid_id` error code
			// (for Customer Update and Customer Delete) to `wc_user_invalid_id`, so ensure that is also handled.
			if ( 'wc_user_invalid_id' !== $data['code'] && strpos( $data['code'], 'rest_' ) !== 0 ) {
				return $response;
			}

			$event       = null;
			$resource_id = 0;
			$child_id    = null;
			switch ( $request->get_method() ) {
				case 'POST':
					// A Create Action.
					$event = Event::action_create(
						$this->resource_type,
						new WP_Error( $data['code'], $data['message'] )
					);
					break;
				case 'PUT':
					// An Update Action.
					$event = $this->modify_event(
						Event::action_update(
							$this->resource_type,
							new WP_Error( $data['code'], $data['message'] )
						)
					);
					if ( isset( $request['id'] ) ) {
						$resource_id = (int) $request['id'];
					}
					break;
			}
			if ( \is_null( $event ) ) {
				return $response;
			}
			$this->task_creator->record(
				$event,
				$this->modify_resource_id( $resource_id, $request, $response ),
				$this->modify_child_id( $child_id, $request, $response )
			);
			$this->log_error_response( $request, $response );
		}
		return $response;
	}

	/**
	 * Modify the event object for this controller.
	 *
	 * This method can be overridden by each controller to modify the event object before the unsuccessful
	 * Task History record is created in rest_post_dispatch_check_for_request_validation_error().
	 *
	 * @since 2.10.0
	 *
	 * @see rest_post_dispatch_check_for_request_validation_error();
	 *
	 * @param  Event $event The event object instance.
	 *
	 * @return Event
	 */
	protected function modify_event( $event ) {
		return $event;
	}

	/**
	 * Modify the resource ID that is used when creating an unsuccessful Task History record.
	 *
	 * This method can be overridden by each controller to modify the resource ID if required.
	 *
	 * @since 2.10.0
	 *
	 * @param int              $resource_id  The resource ID.
	 * @param WP_REST_Request  $request The request used to generate the response.
	 * @param WP_REST_Response $response  The response to be sent to the client.
	 *
	 * @return int
	 */
	protected function modify_resource_id( $resource_id, $request, $response ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $resource_id;
	}

	/**
	 * Modify the child ID that is used when creating an unsuccessful Task History record.
	 *
	 * This method can be overridden by each controller to modify the resource ID if required.
	 *
	 * @since 2.10.0
	 *
	 * @param ?int             $child_id  The resource ID.
	 * @param WP_REST_Request  $request The request used to generate the response.
	 * @param WP_REST_Response $response  The response to be sent to the client.
	 *
	 * @return ?int
	 */
	protected function modify_child_id( $child_id, $request, $response ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $child_id;
	}

	/**
	 * Handle an update action error response.
	 *
	 * @since 2.18.0
	 *
	 * @param WP_REST_Request $request The request used to generate the response.
	 * @param WP_Error        $response The response to be sent to the client.
	 *
	 * @return WP_Error
	 */
	protected function handle_update_error( $request, $response ) {
		$this->log_error_response( $request, $response );
		$this->task_creator->record( Event::action_update( $this->resource_type, $response ), (int) $request['id'] );

		return $response;
	}

	/**
	 * Handle an update action successful response.
	 *
	 * @since 2.18.0
	 *
	 * @param WP_REST_Response $response The response to send to the client.
	 */
	protected function handle_update_success( $response ): void {
		// @phpstan-ignore-next-line Structure comes from WooCommerce.
		$this->task_creator->record( Event::action_update( $this->resource_type ), $response->data['id'] );
	}
}
