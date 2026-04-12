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

namespace SkyVerge\WooCommerce\Memberships\Plans\Actions;

use InvalidArgumentException;
use SkyVerge\WooCommerce\Memberships\Plans\Abilities\CreatePlan;
use SkyVerge\WooCommerce\Memberships\Plans\Adapters\JsonSerializers\MembershipPlanRuleSerializer;
use WC_Memberships_Membership_Plan;
use WC_Memberships_Membership_Plan_Rule;

defined('ABSPATH') or exit;

/**
 * Action to set rules on a membership plan.
 *
 * @since 1.28.0
 */
class SetPlanRules
{
	/** @var string[] valid rule types */
	private const VALID_RULE_TYPES = ['content_restriction', 'product_restriction', 'purchasing_discount'];

	/**
	 * Sets rules on the given plan from the provided rules data.
	 *
	 * @since 1.28.0
	 *
	 * @param WC_Memberships_Membership_Plan $plan the plan to set rules on
	 * @param array $rulesData rules data keyed by rule type {@see CreatePlan::getInputSchema()} and {@see MembershipPlanRuleSerializer::getJsonSchema()}
	 * @return void
	 * @throws InvalidArgumentException
	 */
	public function execute(WC_Memberships_Membership_Plan $plan, array $rulesData): void
	{
		$collectedRules = [];

		foreach ($rulesData as $ruleType => $rules) {

			if (! in_array($ruleType, self::VALID_RULE_TYPES, true)) {
				throw new InvalidArgumentException(
					sprintf('Invalid rule type "%s". Must be one of: %s.', $ruleType, implode(', ', self::VALID_RULE_TYPES))
				);
			}

			if (! is_array($rules)) {
				continue;
			}

			foreach ($rules as $ruleData) {
				$collectedRules[] = $this->configureRule($plan, $ruleType, $ruleData);
			}
		}

		$plan->set_rules($collectedRules);
	}

	/**
	 * Configures and returns a rule object. (note it's not saved)
	 *
	 * @since 1.28.0
	 */
	protected function configureRule(WC_Memberships_Membership_Plan $plan, string $ruleType, array $ruleData) : WC_Memberships_Membership_Plan_Rule
	{
		if (! isset($ruleData['content_type'], $ruleData['content_type_name'])) {
			throw new InvalidArgumentException(
				sprintf('Each %s rule must have "content_type" and "content_type_name".', $ruleType)
			);
		}

		$rule = $this->instantiateRule();

		$rule->set_id();
		$rule->set_membership_plan_id($plan->get_id());
		$rule->set_rule_type($ruleType);
		$rule->set_content_type($ruleData['content_type']);
		$rule->set_content_type_name($ruleData['content_type_name']);
		$rule->set_object_ids($ruleData['object_ids'] ?? []);

		if ('content_restriction' === $ruleType || 'product_restriction' === $ruleType) {
			$this->setAccessSchedule($rule, $ruleData);
		}

		if ('product_restriction' === $ruleType) {
			$rule->set_access_type($ruleData['access_type'] ?? 'view');
		}

		if ('purchasing_discount' === $ruleType) {
			$this->setDiscountFields($rule, $ruleData);
		}

		return $rule;
	}

	/**
	 * Creates a new instance of WC_Memberships_Membership_Plan_Rule.
	 * This is split off for easier unit testing, as the constructor does some logic that we don't want to have to mock.
	 *
	 * @since 1.28.0
	 *
	 * @codeCoverageIgnore
	 */
	protected function instantiateRule() : WC_Memberships_Membership_Plan_Rule
	{
		return new WC_Memberships_Membership_Plan_Rule();
	}

	/**
	 * Sets the access schedule on a rule from rule data.
	 *
	 * @since 1.28.0
	 *
	 * @param WC_Memberships_Membership_Plan_Rule $rule
	 * @param array $ruleData
	 * @return void
	 */
	protected function setAccessSchedule(WC_Memberships_Membership_Plan_Rule $rule, array $ruleData): void
	{
		$schedule = $ruleData['access_schedule'] ?? [];
		$type = $schedule['type'] ?? 'immediate';

		if (! in_array($type, ['immediate', 'delayed'], true)) {
			throw new InvalidArgumentException(
				sprintf('Invalid access schedule type "%s". Must be "immediate" or "delayed".', $type)
			);
		}

		if ('immediate' === $type) {
			$rule->set_access_schedule('immediate');
		} else {
			$amount = $schedule['amount'] ?? 0;

			if (! is_int($amount) || $amount < 1) {
				throw new InvalidArgumentException('Access schedule "amount" must be a positive integer.');
			}

			$period = $schedule['period'] ?? '';

			if (! in_array($period, ['days', 'weeks', 'months', 'years'], true)) {
				throw new InvalidArgumentException(
					sprintf('Invalid access schedule period "%s". Must be one of: days, weeks, months, years.', $period)
				);
			}

			$rule->set_access_schedule(sprintf('%d %s', $amount, $period));
		}
	}

	/**
	 * Sets discount fields on a purchasing_discount rule.
	 *
	 * @since 1.28.0
	 *
	 * @param WC_Memberships_Membership_Plan_Rule $rule
	 * @param array $ruleData
	 * @return void
	 */
	protected function setDiscountFields(WC_Memberships_Membership_Plan_Rule $rule, array $ruleData): void
	{
		if (! empty($ruleData['active'])) {
			$rule->set_active();
		} else {
			$rule->set_inactive();
		}

		if (isset($ruleData['discount_type'])) {
			if (! in_array($ruleData['discount_type'], ['percentage', 'amount'], true)) {
				throw new InvalidArgumentException(
					sprintf('Invalid discount type "%s". Must be "percentage" or "amount".', $ruleData['discount_type'])
				);
			}

			$rule->set_discount_type($ruleData['discount_type']);
		}

		if (isset($ruleData['discount_amount'])) {
			if (! is_numeric($ruleData['discount_amount'])) {
				throw new InvalidArgumentException('Discount amount must be numeric.');
			}

			$rule->set_discount_amount($ruleData['discount_amount']);
		}
	}
}
