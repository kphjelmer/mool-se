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

use WC_Memberships_Membership_Plan_Rule;

defined( 'ABSPATH' ) or exit;

/**
 * Serializes a membership plan rule into an array suitable for JSON output.
 *
 * @since 1.28.0
 */
class MembershipPlanRuleSerializer {

	/**
	 * Converts a rule object into an associative array for JSON serialization.
	 *
	 * @since 1.28.0
	 *
	 * @param WC_Memberships_Membership_Plan_Rule $rule
	 * @return array<string, mixed>
	 */
	public static function convert(WC_Memberships_Membership_Plan_Rule $rule) : array
	{
		$data = [
			'id'                => $rule->get_id(),
			'content_type'      => $rule->get_content_type(),
			'content_type_name' => $rule->get_content_type_name(),
			'object_ids'        => $rule->get_object_ids(),
		];

		if ( $rule->is_type( 'content_restriction' ) || $rule->is_type( 'product_restriction' ) ) {

			$schedule = [ 'type' => $rule->grants_immediate_access() ? 'immediate' : 'delayed' ];

			if ( 'delayed' === $schedule['type'] ) {
				$schedule['amount'] = $rule->get_access_schedule_amount();
				$schedule['period'] = $rule->get_access_schedule_period();
			}

			$data['access_schedule'] = $schedule;
		}

		if ( $rule->is_type( 'product_restriction' ) ) {
			$data['access_type'] = $rule->get_access_type();
		}

		if ( $rule->is_type( 'purchasing_discount' ) ) {
			$data['active']          = $rule->is_active();
			$data['discount_type']   = $rule->get_discount_type();
			$data['discount_amount'] = $rule->get_discount_amount();
		}

		return $data;
	}


	/**
	 * Returns the JSON schema for a single rule of the given type.
	 *
	 * @since 1.28.0
	 *
	 * @param string $ruleType one of 'content_restriction', 'product_restriction', or 'purchasing_discount'
	 * @return array<string, mixed>
	 */
	public static function getJsonSchema(string $ruleType) : array
	{
		$access_schedule_schema = [
			'type'       => 'object',
			'properties' => [
				'type'   => [
					'type'     => 'string',
					'enum'     => [ 'immediate', 'delayed' ],
					'required' => true,
				],
				'amount' => [
					'type'        => 'integer',
					'required'    => false,
					'description' => __('Only present when type is "delayed".', 'woocommerce-memberships'),
				],
				'period' => [
					'type'        => 'string',
					'enum'        => [ 'days', 'weeks', 'months', 'years' ],
					'required'    => false,
					'description' => __('Only present when type is "delayed".', 'woocommerce-memberships'),
				],
			],
		];

		$titles = [
			'content_restriction' => __('Content Restriction', 'woocommerce-memberships'),
			'product_restriction' => __('Product Restriction', 'woocommerce-memberships'),
			'purchasing_discount' => __('Purchasing Discount', 'woocommerce-memberships'),
		];

		$base = [
			'title'      => $titles[ $ruleType ] ?? '',
			'type'       => 'object',
			'properties' => [
				'id'                => [ 'type' => 'string', 'required' => true ],
				'content_type'      => [
					'type'     => 'string',
					'enum'     => [ 'post_type', 'taxonomy', '' ], // need to allow for empty string
					'required' => true,
				],
				'content_type_name' => [ 'type' => 'string', 'required' => true ],
				'object_ids'        => [
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'required'    => true,
					'description' => __('Targeted object IDs. Empty array means all objects of the content type.', 'woocommerce-memberships'),
				],
			],
		];

		if ( 'content_restriction' === $ruleType ) {
			$base['properties']['access_schedule'] = $access_schedule_schema;
		}

		if ( 'product_restriction' === $ruleType ) {
			$base['properties']['access_schedule'] = $access_schedule_schema;
			$base['properties']['access_type']     = [
				'type'     => 'string',
				'required' => true,
			];
		}

		if ( 'purchasing_discount' === $ruleType ) {
			$base['properties']['active']          = [ 'type' => 'boolean', 'required' => true ];
			$base['properties']['discount_type']   = [
				'type'     => 'string',
				'enum'     => [ 'percentage', 'amount' ],
				'required' => true,
			];
			$base['properties']['discount_amount'] = [ 'type' => ['string', 'number'], 'required' => true ];
		}

		return $base;
	}
}
