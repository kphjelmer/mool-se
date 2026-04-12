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
use SkyVerge\WooCommerce\Memberships\Plans\Exceptions\PlanCreateFailedException;
use WC_Memberships_Membership_Plan;

defined('ABSPATH') or exit;

/**
 * Action to create a new membership plan.
 *
 * @since 1.28.0
 */
class CreatePlan
{
	/** @var SetPlanRules */
	protected SetPlanRules $setPlanRules;

	/**
	 * Constructor.
	 *
	 * @since 1.28.0
	 */
	public function __construct()
	{
		$this->setPlanRules = new SetPlanRules();
	}

	/**
	 * Creates a new membership plan from the given data.
	 *
	 * @since 1.28.0
	 *
	 * @param array<string, mixed> $data plan data -- {@see \SkyVerge\WooCommerce\Memberships\Plans\Abilities\CreatePlan::getInputSchema()}
	 * @return WC_Memberships_Membership_Plan
	 * @throws InvalidArgumentException
	 * @throws PlanCreateFailedException
	 */
	public function execute(array $data): WC_Memberships_Membership_Plan
	{
		$name   = $this->validateName($data);
		$status = $this->validateStatus($data);
		$slug   = $this->validateSlug($data);
		$access = $this->validateAccess($data);
		$length = $this->validateMembershipLength($data);

		$plan = $this->insertPlan($name, $status, $data['description'] ?? '', $slug);

		$this->configurePlan($plan, $access, $length, $data);

		return $plan;
	}

	/**
	 * Validates and sanitizes the plan name.
	 *
	 * @since 1.28.0
	 *
	 * @param array<string, mixed> $data
	 * @return string
	 * @throws InvalidArgumentException
	 */
	protected function validateName(array $data): string
	{
		$name = $data['name'] ?? '';

		if (! is_string($name)) {
			throw new InvalidArgumentException('Plan name must be a string.');
		}

		$name = sanitize_text_field($name);

		if (empty($name)) {
			throw new InvalidArgumentException('Plan name is required and cannot be empty.');
		}

		return $name;
	}

	/**
	 * Validates the plan status, defaulting to 'publish'.
	 *
	 * @since 1.28.0
	 *
	 * @param array<string, mixed> $data
	 * @return string
	 * @throws InvalidArgumentException
	 */
	protected function validateStatus(array $data): string
	{
		$status = $data['status'] ?? 'publish';

		if (! in_array($status, ['draft', 'publish'], true)) {
			throw new InvalidArgumentException(
				sprintf('Invalid plan status "%s". Must be "draft" or "publish".', $status)
			);
		}

		return $status;
	}

	/**
	 * Validates and sanitizes the plan slug.
	 *
	 * @since 1.28.0
	 *
	 * @param array<string, mixed> $data
	 * @return string|null
	 * @throws InvalidArgumentException
	 */
	protected function validateSlug(array $data): ?string
	{
		if (empty($data['slug'])) {
			return null;
		}

		$slug = sanitize_title($data['slug']);

		if (empty($slug)) {
			throw new InvalidArgumentException(
				sprintf('Slug "%s" is not valid.', $data['slug'])
			);
		}

		return $slug;
	}

	/**
	 * Validates access method and product IDs.
	 *
	 * @since 1.28.0
	 *
	 * @param array<string, mixed> $data
	 * @return array{method: string, product_ids: int[]}
	 * @throws InvalidArgumentException
	 */
	protected function validateAccess(array $data): array
	{
		$access = $data['access'] ?? [];
		if (! is_array($access)) {
			throw new InvalidArgumentException('Access data must be an array.');
		}

		$accessMethod = $data['access']['method'] ?? 'manual-only';

		if (! in_array($accessMethod, ['manual-only', 'signup', 'purchase'], true)) {
			throw new InvalidArgumentException(
				sprintf('Invalid access method "%s". Must be one of: manual-only, signup, purchase.', $accessMethod)
			);
		}

		$productIds = [];

		if ('purchase' === $accessMethod) {

			$productIds = $data['access']['product_ids'] ?? [];

			if (empty($productIds)) {
				throw new InvalidArgumentException('Access method "purchase" requires at least one product ID.');
			}

			if (! is_array($productIds)) {
				throw new InvalidArgumentException('Access product_ids must be an array.');
			}

			foreach ($productIds as $productId) {
				if (! wc_get_product((int) $productId)) {
					throw new InvalidArgumentException(
						sprintf('Product %d is not a valid product.', $productId)
					);
				}
			}
		}

		return [
			'method'      => $accessMethod,
			'product_ids' => $productIds,
		];
	}

	/**
	 * Validates membership length configuration.
	 *
	 * @since 1.28.0
	 *
	 * @param array<string, mixed> $data
	 * @return array{type: string, amount: int, period: string, start_date: string, end_date: string}
	 * @throws InvalidArgumentException
	 */
	protected function validateMembershipLength(array $data): array
	{
		$lengthType = $data['membership_length']['type'] ?? 'unlimited';

		if (! in_array($lengthType, ['unlimited', 'specific', 'fixed'], true)) {
			throw new InvalidArgumentException(
				sprintf('Invalid membership length type "%s". Must be one of: unlimited, specific, fixed.', $lengthType)
			);
		}

		$lengthAmount = 0;
		$lengthPeriod = '';
		$startDate = '';
		$endDate = '';

		if ('specific' === $lengthType) {

			$lengthAmount = (int) ($data['membership_length']['amount'] ?? 0);
			$lengthPeriod = $data['membership_length']['period'] ?? '';

			if ($lengthAmount < 1) {
				throw new InvalidArgumentException('Membership length type "specific" requires a positive "amount".');
			}

			if (! in_array($lengthPeriod, ['days', 'weeks', 'months', 'years'], true)) {
				throw new InvalidArgumentException(
					sprintf('Invalid membership length period "%s". Must be one of: days, weeks, months, years.', $lengthPeriod)
				);
			}
		}

		if ('fixed' === $lengthType) {

			$startDate = $data['membership_length']['start_date'] ?? '';
			$endDate = $data['membership_length']['end_date'] ?? '';

			if (empty($startDate) || empty($endDate)) {
				throw new InvalidArgumentException('Membership length type "fixed" requires both "start_date" and "end_date".');
			}

			$startDateTimestamp = strtotime($startDate);
			$endDateTimestamp = strtotime($endDate);

			if (! $startDateTimestamp) {
				throw new InvalidArgumentException('Membership length "start_date" is invalid.');
			}

			if (! $endDateTimestamp) {
				throw new InvalidArgumentException('Membership length "end_date" is invalid.');
			}

			if ($startDateTimestamp > $endDateTimestamp) {
				throw new InvalidArgumentException('Membership length "start_date" cannot be after "end_date".');
			}
		}

		return [
			'type'       => $lengthType,
			'amount'     => $lengthAmount,
			'period'     => $lengthPeriod,
			'start_date' => $startDate,
			'end_date'   => $endDate,
		];
	}

	/**
	 * Inserts the plan post and returns the plan object.
	 *
	 * @since 1.28.0
	 * @throws PlanCreateFailedException
	 */
	protected function insertPlan(string $name, string $status, string $description, ?string $slug = null): WC_Memberships_Membership_Plan
	{
		$insertArgs = [
			'post_type'    => 'wc_membership_plan',
			'post_title'   => $name,
			'post_status'  => $status,
			'post_content' => $description,
		];

		if (! empty($slug)) {
			$insertArgs['post_name'] = $slug;
		}

		$postId = wp_insert_post($insertArgs, true);

		if (0 === $postId || is_wp_error($postId)) {
			throw new PlanCreateFailedException();
		}

		$plan = wc_memberships()->get_plans_instance()->get_membership_plan($postId);

		if (! $plan instanceof WC_Memberships_Membership_Plan) {
			throw new PlanCreateFailedException();
		}

		return $plan;
	}

	/**
	 * Configures plan settings after insertion.
	 *
	 * @since 1.28.0
	 *
	 * @param WC_Memberships_Membership_Plan $plan
	 * @param array $access
	 * @param array $length
	 * @param array<string, mixed> $data
	 * @return void
	 */
	protected function configurePlan(WC_Memberships_Membership_Plan $plan, array $access, array $length, array $data): void
	{
		$plan->set_access_method($access['method']);

		if (! empty($access['product_ids'])) {
			$plan->set_product_ids($access['product_ids']);
		}

		if ('specific' === $length['type']) {
			$plan->set_access_length($length['amount'] . ' ' . $length['period']);
		}

		if ('fixed' === $length['type']) {
			$timeStart = strtotime('today', strtotime($length['start_date']));
			$timeEnd = strtotime('today', strtotime($length['end_date']));
			$timezone = wc_timezone_string();

			$plan->set_access_start_date(date('Y-m-d H:i:s', wc_memberships_adjust_date_by_timezone($timeStart, 'timestamp', $timezone)));
			$plan->set_access_end_date(date('Y-m-d H:i:s', wc_memberships_adjust_date_by_timezone($timeEnd, 'timestamp', $timezone)));
		}

		if (! empty($data['rules'])) {
			$this->setPlanRules->execute($plan, $data['rules']);
		}
	}
}
