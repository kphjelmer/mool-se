<?php
/**
 * WooCommerce Memberships
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Memberships to newer
 * versions in the future. If you wish to customize WooCommerce Memberships for your
 * needs please refer to https://docs.woocommerce.com/document/woocommerce-memberships/ for more information.
 *
 * @author    SkyVerge
 * @copyright Copyright (c) 2014-2026, SkyVerge, Inc. (info@skyverge.com)
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace SkyVerge\WooCommerce\Memberships\Plans\Adapters\JsonSerializers;

defined( 'ABSPATH' ) or exit;

/**
 * Serializes a membership plan into an array suitable for JSON output.
 *
 * @since 1.28.0
 */
class MembershipPlanSerializer {

	/**
	 * Converts a plan object into an associative array for JSON serialization.
	 *
	 * @since 1.28.0
	 *
	 * @param \WC_Memberships_Membership_Plan $plan
	 * @return array<string, mixed>
	 */
	public static function convert(\WC_Memberships_Membership_Plan $plan) : array
	{
		$access_method = $plan->get_access_method();
		$length_type   = $plan->get_access_length_type();

		$access = [ 'method' => $access_method ];

		if ( 'purchase' === $access_method ) {
			$access['product_ids'] = $plan->get_product_ids();
		}

		$membership_length = [ 'type' => $length_type ];

		if ( 'specific' === $length_type ) {
			$membership_length['amount'] = $plan->get_access_length_amount();
			$membership_length['period'] = $plan->get_access_length_period();
		} elseif ( 'fixed' === $length_type ) {
			$membership_length['start_date'] = $plan->get_access_start_date( 'c' );
			$membership_length['end_date']   = $plan->get_access_end_date( 'c' );
		}

		return [
			'id'                => $plan->get_id(),
			'name'              => $plan->get_name(),
			'slug'              => $plan->get_slug(),
			'status'            => $plan->post ? $plan->post->post_status : '',
			'description'       => $plan->post ? $plan->post->post_content : '',
			'access'            => $access,
			'membership_length' => $membership_length,
			'rules'             => [
				'content_restriction' => array_values( array_map( static fn( $rule ) => $rule->jsonSerialize(), $plan->get_content_restriction_rules() ) ),
				'product_restriction' => array_values( array_map( static fn( $rule ) => $rule->jsonSerialize(), $plan->get_product_restriction_rules() ) ),
				'purchasing_discount' => array_values( array_map( static fn( $rule ) => $rule->jsonSerialize(), $plan->get_purchasing_discount_rules() ) ),
			],
		];
	}


	/**
	 * Returns the JSON schema describing the shape of {@see convert()} output.
	 *
	 * @since 1.28.0
	 *
	 * @return array<string, mixed>
	 */
	public static function getJsonSchema() : array
	{
		return [
			'type'       => 'object',
			'properties' => [
				'id'          => [ 'type' => 'integer' ],
				'name'        => [ 'type' => 'string' ],
				'slug'        => [ 'type' => 'string' ],
				'status'      => [ 'type' => 'string' ],
				'description' => [ 'type' => 'string' ],
				'access'      => [
					'type'       => 'object',
					'properties' => [
						'method'      => [
							'type'     => 'string',
							'enum'     => [ 'manual-only', 'signup', 'purchase' ],
							'required' => true,
						],
						'product_ids' => [
							'type'        => 'array',
							'items'       => [ 'type' => 'integer' ],
							'required'    => false,
							'description' => __('Only present when method is "purchase".', 'woocommerce-memberships'),
						],
					],
				],
				'membership_length' => [
					'type'       => 'object',
					'properties' => [
						'type'       => [
							'type'     => 'string',
							'enum'     => [ 'unlimited', 'specific', 'fixed' ],
							'required' => true,
						],
						'amount'     => [
							'type'        => 'integer',
							'required'    => false,
							'description' => __('Only present when type is "specific".', 'woocommerce-memberships'),
						],
						'period'     => [
							'type'        => 'string',
							'enum'        => [ 'days', 'weeks', 'months', 'years' ],
							'required'    => false,
							'description' => __('Only present when type is "specific".', 'woocommerce-memberships'),
						],
						'start_date' => [
							'type'        => 'string',
							'format'      => 'date-time',
							'required'    => false,
							'description' => __('Only present when type is "fixed".', 'woocommerce-memberships'),
						],
						'end_date'   => [
							'type'        => 'string',
							'format'      => 'date-time',
							'required'    => false,
							'description' => __('Only present when type is "fixed".', 'woocommerce-memberships'),
						],
					],
				],
				'rules' => [
					'type'       => 'object',
					'properties' => [
						'content_restriction' => [
							'type'  => 'array',
							'items' => MembershipPlanRuleSerializer::getJsonSchema( 'content_restriction' ),
						],
						'product_restriction' => [
							'type'  => 'array',
							'items' => MembershipPlanRuleSerializer::getJsonSchema( 'product_restriction' ),
						],
						'purchasing_discount' => [
							'type'  => 'array',
							'items' => MembershipPlanRuleSerializer::getJsonSchema( 'purchasing_discount' ),
						],
					],
				],
			],
		];
	}
}
