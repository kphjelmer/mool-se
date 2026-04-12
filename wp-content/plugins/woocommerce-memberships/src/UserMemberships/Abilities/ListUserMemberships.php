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

namespace SkyVerge\WooCommerce\Memberships\UserMemberships\Abilities;

use SkyVerge\WooCommerce\Memberships\Abilities\Provider;
use SkyVerge\WooCommerce\PluginFramework\v6_1_1\Abilities\Contracts\MakesAbilityContract;
use SkyVerge\WooCommerce\PluginFramework\v6_1_1\Abilities\DataObjects\Ability;
use SkyVerge\WooCommerce\PluginFramework\v6_1_1\Abilities\DataObjects\AbilityAnnotations;
use WC_Memberships_User_Membership;

defined('ABSPATH') or exit;

/**
 * Ability: List User Memberships.
 *
 * Retrieves user memberships for a given user.
 *
 * @since 1.28.0
 */
class ListUserMemberships implements MakesAbilityContract
{
	const NAME = 'woocommerce-memberships/user-memberships-list';

	/**
	 * Creates and returns the Ability data object for registration.
	 *
	 * @since 1.28.0
	 *
	 * @return Ability
	 */
	public function makeAbility(): Ability
	{
		return new Ability(
			static::NAME,
			__('List User Memberships', 'woocommerce-memberships'),
			__('Retrieves user memberships for a given user.', 'woocommerce-memberships'),
			Provider::USER_MEMBERSHIPS_CATEGORY_SLUG,
			function (array $params = []) {
				$userId = $params['user_id'] ?? null;

				unset($params['user_id']);

				return array_values(
					wc_memberships()->get_user_memberships_instance()->get_user_memberships($userId, $params)
				);
			},
			function () {
				return current_user_can('manage_woocommerce');
			},
			[
				'type'       => 'object',
				'default'    => [],
				'properties' => [
					'user_id' => [
						'type'        => 'integer',
						'required'    => true,
						'minimum'     => 1,
						'description' => __('The user ID to retrieve memberships for.', 'woocommerce-memberships'),
					],
					'status' => [
						'type'        => ['string', 'array'],
						'items'       => ['type' => 'string'],
						'description' => __('Filter by membership status. A single status string or an array of statuses (e.g. "active", ["active", "expired"], "any").', 'woocommerce-memberships'),
						'default'     => 'any',
					],
				],
			],
			[
				'type'  => 'array',
				'items' => WC_Memberships_User_Membership::getJsonSchema(),
			],
			new AbilityAnnotations(true, false, true),
			true
		);
	}
}
