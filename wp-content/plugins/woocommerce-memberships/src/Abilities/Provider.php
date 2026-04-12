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

namespace SkyVerge\WooCommerce\Memberships\Abilities;

use SkyVerge\WooCommerce\Memberships\Plans\Abilities\CreatePlan;
use SkyVerge\WooCommerce\Memberships\Plans\Abilities\DeletePlan;
use SkyVerge\WooCommerce\Memberships\Plans\Abilities\GetPlan;
use SkyVerge\WooCommerce\Memberships\Plans\Abilities\ListPlans;
use SkyVerge\WooCommerce\Memberships\UserMemberships\Abilities\CreateUserMembership;
use SkyVerge\WooCommerce\Memberships\UserMemberships\Abilities\DeleteUserMembership;
use SkyVerge\WooCommerce\Memberships\UserMemberships\Abilities\GetUserMembership;
use SkyVerge\WooCommerce\Memberships\UserMemberships\Abilities\ListUserMemberships;
use SkyVerge\WooCommerce\PluginFramework\v6_1_1\Abilities\AbstractAbilitiesProvider;
use SkyVerge\WooCommerce\PluginFramework\v6_1_1\Abilities\DataObjects\AbilityCategory;

defined( 'ABSPATH' ) or exit;

/**
 * Abilities provider for WooCommerce Memberships.
 *
 * Registers all abilities and categories for the Memberships plugin
 * with the WordPress Abilities API.
 *
 * @since 1.28.0
 */
class Provider extends AbstractAbilitiesProvider
{
	const PLANS_CATEGORY_SLUG = 'woocommerce-membership-plans';
	const USER_MEMBERSHIPS_CATEGORY_SLUG = 'woocommerce-user-memberships';

	/** @inheritDoc */
	protected array $abilities = [
		// plans
		CreatePlan::class,
		DeletePlan::class,
		GetPlan::class,
		ListPlans::class,

		// user memberships
		CreateUserMembership::class,
		DeleteUserMembership::class,
		GetUserMembership::class,
		ListUserMemberships::class,
	];

	/** @inheritDoc */
	public function getCategories(): array
	{
		return [
			new AbilityCategory(
				static::PLANS_CATEGORY_SLUG,
				__('WooCommerce Membership Plans', 'woocommerce-memberships'),
				__('Abilities related to WooCommerce Membership Plans.', 'woocommerce-memberships'),
			),
			new AbilityCategory(
				static::USER_MEMBERSHIPS_CATEGORY_SLUG,
				__('WooCommerce User Memberships', 'woocommerce-memberships'),
				__('Abilities related to WooCommerce User Memberships.', 'woocommerce-memberships'),
			),
		];
	}
}
