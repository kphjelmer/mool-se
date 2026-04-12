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

namespace SkyVerge\WooCommerce\Memberships\UserMemberships\Adapters\JsonSerializers;

use WC_Memberships_User_Membership;

defined('ABSPATH') or exit;

/**
 * Serializes a user membership into an array suitable for JSON output.
 *
 * @since 1.28.0
 */
class UserMembershipSerializer
{
	/**
	 * Converts a user membership object into an associative array for JSON serialization.
	 *
	 * @since 1.28.0
	 *
	 * @param WC_Memberships_User_Membership $userMembership
	 * @return array<string, mixed>
	 */
	public static function convert(WC_Memberships_User_Membership $userMembership): array
	{
		return [
			'id'             => $userMembership->get_id(),
			'plan_id'        => $userMembership->get_plan_id(),
			'user_id'        => $userMembership->get_user_id(),
			'status'         => $userMembership->get_status(),
			'start_date'     => $userMembership->get_start_date('c'),
			'end_date'       => $userMembership->get_end_date('c'),
			'cancelled_date' => $userMembership->get_cancelled_date('c'),
			'paused_date'    => $userMembership->get_paused_date('c'),
			'order_id'       => $userMembership->get_order_id(),
			'product_id'     => $userMembership->get_product_id(),
		];
	}

	/**
	 * Returns the JSON schema describing the shape of {@see convert()} output.
	 *
	 * @since 1.28.0
	 *
	 * @return array<string, mixed>
	 */
	public static function getJsonSchema(): array
	{
		return [
			'type' => 'object',
			'properties' => [
				'id' => ['type' => 'integer'],
				'plan_id' => ['type' => 'integer'],
				'user_id' => ['type' => 'integer'],
				'status' => [
					'type' => 'string',
					'enum' => ['active', 'delayed', 'complimentary', 'pending', 'paused', 'expired', 'cancelled'],
				],
				'start_date' => [
					'type'   => ['string', 'null'],
					'format' => 'date-time',
				],
				'end_date' => [
					'type'   => ['string', 'null'],
					'format' => 'date-time',
				],
				'cancelled_date' => [
					'type'   => ['string', 'null'],
					'format' => 'date-time',
				],
				'paused_date' => [
					'type'   => ['string', 'null'],
					'format' => 'date-time',
				],
				'order_id'   => ['type' => ['integer', 'null']],
				'product_id' => ['type' => ['integer', 'null']],
			],
		];
	}
}
