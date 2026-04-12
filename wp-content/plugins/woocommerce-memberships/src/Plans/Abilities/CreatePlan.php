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

use InvalidArgumentException;
use SkyVerge\WooCommerce\Memberships\Abilities\Provider;
use SkyVerge\WooCommerce\Memberships\Plans\Adapters\JsonSerializers\MembershipPlanRuleSerializer;
use SkyVerge\WooCommerce\Memberships\Plans\Exceptions\PlanCreateFailedException;
use SkyVerge\WooCommerce\PluginFramework\v6_1_1\Abilities\Contracts\MakesAbilityContract;
use SkyVerge\WooCommerce\PluginFramework\v6_1_1\Abilities\DataObjects\Ability;
use SkyVerge\WooCommerce\PluginFramework\v6_1_1\Abilities\DataObjects\AbilityAnnotations;
use WC_Memberships_Membership_Plan;
use WP_Error;

defined('ABSPATH') or exit;

/**
 * Ability: Create Membership Plan.
 *
 * Creates a new membership plan.
 *
 * @since 1.28.0
 */
class CreatePlan implements MakesAbilityContract
{
	const NAME = 'woocommerce-memberships/plans-create';

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
			__('Create Membership Plan', 'woocommerce-memberships'),
			__('Creates a new membership plan.', 'woocommerce-memberships'),
			Provider::PLANS_CATEGORY_SLUG,
			function (array $data) {
				try {
					return wc_memberships()->get_plans_instance()->createPlan($data);
				} catch (PlanCreateFailedException $e) {
					return new WP_Error('create_failed', $e->getMessage(), ['status' => $e->getCode()]);
				} catch (InvalidArgumentException $e) {
					return new WP_Error('invalid_input', $e->getMessage(), ['status' => 422]);
				}
			},
			function () {
				return current_user_can('manage_woocommerce');
			},
			$this->getInputSchema(),
			WC_Memberships_Membership_Plan::getJsonSchema(),
			new AbilityAnnotations(false, false, false),
			true
		);
	}

	/**
	 * Returns the input schema for the create plan ability.
	 *
	 * @since 1.28.0
	 *
	 * @return array<string, mixed>
	 */
	protected function getInputSchema(): array
	{
		$contentRestrictionRuleSchema = MembershipPlanRuleSerializer::getJsonSchema('content_restriction');
		$productRestrictionRuleSchema = MembershipPlanRuleSerializer::getJsonSchema('product_restriction');
		$purchasingDiscountRuleSchema = MembershipPlanRuleSerializer::getJsonSchema('purchasing_discount');

		// for creation, id is not required
		unset(
			$contentRestrictionRuleSchema['properties']['id'],
			$productRestrictionRuleSchema['properties']['id'],
			$purchasingDiscountRuleSchema['properties']['id']
		);

		return [
			'type'       => 'object',
			'properties' => [
				'name' => [
					'type'        => 'string',
					'required'    => true,
					'description' => __('The plan name.', 'woocommerce-memberships'),
				],
				'slug' => [
					'type'        => 'string',
					'description' => __('The plan slug.', 'woocommerce-memberships'),
				],
				'status' => [
					'type'        => 'string',
					'enum'        => ['draft', 'publish'],
					'description' => __('The plan status.', 'woocommerce-memberships'),
				],
				'description' => [
					'type'        => 'string',
					'description' => __('The plan description.', 'woocommerce-memberships'),
				],
				'access' => [
					'type'       => 'object',
					'description' => __('Access settings.', 'woocommerce-memberships'),
					'properties' => [
						'method' => [
							'type' => 'string',
							'enum' => ['manual-only', 'signup', 'purchase'],
						],
						'product_ids' => [
							'type'        => 'array',
							'items'       => ['type' => 'integer'],
							'description' => __('Required when method is "purchase".', 'woocommerce-memberships'),
						],
					],
				],
				'membership_length' => [
					'type'       => 'object',
					'description' => __('Membership length settings.', 'woocommerce-memberships'),
					'properties' => [
						'type' => [
							'type' => 'string',
							'enum' => ['unlimited', 'specific', 'fixed'],
						],
						'amount' => [
							'type'        => 'integer',
							'description' => __('Required when type is "specific".', 'woocommerce-memberships'),
						],
						'period' => [
							'type'        => 'string',
							'enum'        => ['days', 'weeks', 'months', 'years'],
							'description' => __('Required when type is "specific".', 'woocommerce-memberships'),
						],
						'start_date' => [
							'type'        => 'string',
							'format'      => 'date',
							'description' => __('Required when type is "fixed".', 'woocommerce-memberships'),
						],
						'end_date' => [
							'type'        => 'string',
							'format'      => 'date',
							'description' => __('Required when type is "fixed".', 'woocommerce-memberships'),
						],
					],
				],
				'rules' => [
					'type'        => 'object',
					'description' => __('Plan rules.', 'woocommerce-memberships'),
					'properties'  => [
						'content_restriction' => [
							'type'  => 'array',
							'items' => $contentRestrictionRuleSchema,
						],
						'product_restriction' => [
							'type'  => 'array',
							'items' => $productRestrictionRuleSchema,
						],
						'purchasing_discount' => [
							'type'  => 'array',
							'items' => $purchasingDiscountRuleSchema,
						],
					],
				],
			],
		];
	}
}
