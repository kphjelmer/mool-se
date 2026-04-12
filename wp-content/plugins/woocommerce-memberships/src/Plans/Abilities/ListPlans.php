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
use SkyVerge\WooCommerce\PluginFramework\v6_1_1\Abilities\Contracts\MakesAbilityContract;
use SkyVerge\WooCommerce\PluginFramework\v6_1_1\Abilities\DataObjects\Ability;
use SkyVerge\WooCommerce\PluginFramework\v6_1_1\Abilities\DataObjects\AbilityAnnotations;
use WC_Memberships_Membership_Plan;

defined( 'ABSPATH' ) or exit;

/**
 * Ability: List Membership Plans.
 *
 * Retrieves a collection of membership plans.
 *
 * @since 1.28.0
 */
class ListPlans implements MakesAbilityContract
{
	const NAME = 'woocommerce-memberships/plans-list';

	/**
	 * Creates and returns the Ability data object for registration.
	 *
	 * @since 1.28.0
	 *
	 * @return Ability
	 */
	public function makeAbility() : Ability
	{
		return new Ability(
			static::NAME,
			__('List Membership Plans', 'woocommerce-memberships'),
			__('Retrieves a collection of membership plans.', 'woocommerce-memberships'),
			Provider::PLANS_CATEGORY_SLUG,
			function (array $params = []) {
				return array_values(wc_memberships()->get_plans_instance()->get_membership_plans($params));
			},
			function () {
				return current_user_can( 'manage_woocommerce' );
			},
			// input schema — accepts WP_Query arguments (post_type is set internally)
			[
				'type'                 => 'object',
				'default'              => [],
				'additionalProperties' => true, // allows properties not listed to be passed through
				'description'          => __('Accepts standard WP_Query arguments. Common parameters are listed below; any valid WP_Query parameter may be used.', 'woocommerce-memberships'),
				'properties'           => [
					'posts_per_page' => [
						'type'        => 'integer',
						'description' => __('Number of plans to return. Use -1 for all.', 'woocommerce-memberships'),
						'default'     => -1,
					],
					'paged' => [
						'type'        => 'integer',
						'description' => __('Page number when paginating results.', 'woocommerce-memberships'),
						'minimum'     => 1,
					],
					'offset' => [
						'type'        => 'integer',
						'description' => __('Number of plans to skip before returning results.', 'woocommerce-memberships'),
					],
					'post_status' => [
						'type'        => 'string',
						'description' => __('Plan status. Accepts any valid post status (e.g. "publish", "draft", "any").', 'woocommerce-memberships'),
						'default'     => 'publish',
					],
					'post__in' => [
						'type'        => 'array',
						'description' => __('Limit result set to specific plan IDs.', 'woocommerce-memberships'),
						'items'       => [ 'type' => 'integer' ],
					],
					'post__not_in' => [
						'type'        => 'array',
						'description' => __('Exclude specific plan IDs from the result set.', 'woocommerce-memberships'),
						'items'       => [ 'type' => 'integer' ],
					],
					'post_name__in' => [
						'type'        => 'array',
						'description' => __('Limit result set to plans with specific slugs.', 'woocommerce-memberships'),
						'items'       => [ 'type' => 'string' ],
					],
					'orderby' => [
						'type'        => 'string',
						'description' => __('Sort plans by parameter (e.g. "date", "title", "modified", "ID").', 'woocommerce-memberships'),
					],
					'order' => [
						'type'        => 'string',
						'description' => __('Sort order.', 'woocommerce-memberships'),
						'enum'        => [ 'ASC', 'DESC' ],
					],
				],
			],
			[
				'type'  => 'array',
				'items' => WC_Memberships_Membership_Plan::getJsonSchema(),
			],
			new AbilityAnnotations(true, false, true),
			true
		);
	}
}
