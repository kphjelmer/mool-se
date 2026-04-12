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

namespace SkyVerge\WooCommerce\Memberships\Plans\Abilities;

use SkyVerge\WooCommerce\Memberships\Abilities\Provider;
use SkyVerge\WooCommerce\Memberships\Plans\Exceptions\PlanDeleteFailedException;
use SkyVerge\WooCommerce\Memberships\Plans\Exceptions\PlanNotDeletableException;
use SkyVerge\WooCommerce\Memberships\Plans\Exceptions\PlanNotFoundException;
use SkyVerge\WooCommerce\PluginFramework\v6_1_1\Abilities\Contracts\MakesAbilityContract;
use SkyVerge\WooCommerce\PluginFramework\v6_1_1\Abilities\DataObjects\Ability;
use SkyVerge\WooCommerce\PluginFramework\v6_1_1\Abilities\DataObjects\AbilityAnnotations;
use WC_Memberships_Membership_Plan;
use WP_Error;

defined('ABSPATH') or exit;

/**
 * Ability: Delete Membership Plan.
 *
 * Deletes a membership plan by ID.
 *
 * @since 1.28.0
 */
class DeletePlan implements MakesAbilityContract
{
	const NAME = 'woocommerce-memberships/plans-delete';

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
			__('Delete Membership Plan', 'woocommerce-memberships'),
			__('Deletes a membership plan by ID.', 'woocommerce-memberships'),
			Provider::PLANS_CATEGORY_SLUG,
			function (int $planId) {

				try {
					return wc_memberships()->get_plans_instance()->deletePlan($planId);
				} catch (PlanNotFoundException $e) {
					return new WP_Error('plan_not_found', $e->getMessage(), ['status' => (int) $e->getCode()]);
				} catch (PlanNotDeletableException $e) {
					return new WP_Error('plan_not_deletable', $e->getMessage(), ['status' => (int) $e->getCode()]);
				} catch (PlanDeleteFailedException $e) {
					return new WP_Error('delete_failed', $e->getMessage(), ['status' => (int) $e->getCode()]);
				}
			},
			function () {
				return current_user_can('manage_woocommerce');
			},
			[
				'type' => 'integer',
				'description' => __('The membership plan ID.', 'woocommerce-memberships'),
				'required' => true,
				'minimum' => 1,
			],
			WC_Memberships_Membership_Plan::getJsonSchema(),
			new AbilityAnnotations(false, true, false),
			true
		);
	}
}
