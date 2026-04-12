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
use SkyVerge\WooCommerce\PluginFramework\v6_1_1 as Framework;
use WC_Memberships_User_Membership;
use WP_Error;

defined('ABSPATH') or exit;

/**
 * Ability: Create User Membership.
 *
 * @since 1.28.0
 */
class CreateUserMembership implements MakesAbilityContract
{
	const NAME = 'woocommerce-memberships/user-memberships-create';

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
			__('Create User Membership', 'woocommerce-memberships'),
			__('Creates a new user membership.', 'woocommerce-memberships'),
			Provider::USER_MEMBERSHIPS_CATEGORY_SLUG,
			function (array $data) {
				try {
					return wc_memberships()->get_user_memberships_instance()->create_user_membership($data);
				} catch (Framework\SV_WC_Plugin_Exception $e) {
					return new WP_Error('create_failed', $e->getMessage(), ['status' => 500]);
				}
			},
			function () {
				return current_user_can('manage_woocommerce');
			},
			$this->getInputSchema(),
			WC_Memberships_User_Membership::getJsonSchema(),
			new AbilityAnnotations(false, false, false),
			true
		);
	}

	/**
	 * Returns the input schema for the create user membership ability.
	 *
	 * @since 1.28.0
	 *
	 * @return array<string, mixed>
	 */
	protected function getInputSchema(): array
	{
		return [
			'type'       => 'object',
			'properties' => [
				'plan_id' => [
					'type'        => 'integer',
					'required'    => true,
					'minimum'     => 1,
					'description' => __('The membership plan ID to assign the user to.', 'woocommerce-memberships'),
				],
				'user_id' => [
					'type'        => 'integer',
					'required'    => true,
					'minimum'     => 1,
					'description' => __('The user ID to create the membership for.', 'woocommerce-memberships'),
				],
				'product_id' => [
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __('The product ID that granted access (optional).', 'woocommerce-memberships'),
				],
				'order_id' => [
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __('The order ID that contained the access-granting product (optional).', 'woocommerce-memberships'),
				],
			],
		];
	}
}
