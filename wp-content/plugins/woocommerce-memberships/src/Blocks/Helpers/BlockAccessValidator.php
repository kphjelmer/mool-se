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

namespace SkyVerge\WooCommerce\Memberships\Blocks\Helpers;

use SkyVerge\WooCommerce\PluginFramework\v6_1_1 as Framework;

defined( 'ABSPATH' ) or exit;

/**
 * Block Access Validator.
 *
 * Centralizes membership access validation logic for restriction blocks.
 *
 * @since 1.27.3
 */
class BlockAccessValidator {


	/**
	 * Access time offset in seconds -- from most recent validation.
	 *
	 * @since 1.27.3
	 */
	protected int $access_offset = 0;


	/**
	 * Validates if user can access member-restricted content.
	 *
	 * @since 1.27.3
	 *
	 * @param array $attributes block attributes
	 * @param int|null $user_id user ID (defaults to current user)
	 * @return bool true if user has access, false otherwise
	 */
	public function canAccessMemberContent( array $attributes, ?int $user_id = null ) : bool {

		$is_member = $is_admin = false;

		$access_offset       = 0;
		$user_id             = $user_id ?: get_current_user_id();
		$user_memberships    = [];
		$membership_plans    = isset( $attributes['membershipPlans'] ) ? array_map( 'absint', (array) $attributes['membershipPlans'] ) : [];
		$delay_access        = isset( $attributes['delayAccess'] ) ? $attributes['delayAccess'] : 'immediate';
		$after_free_trial    = isset( $attributes['afterFreeTrial'] ) && in_array( $attributes['afterFreeTrial'], [ 1, '1', true, 'true' ], true ) && ! Framework\SV_WC_Helper::str_exists( $delay_access, '-' ) && wc_memberships()->get_integrations_instance()->is_subscriptions_active();

		if ( $user_id > 0 ) {

			// skip for admins: they can see all restricted content
			if ( current_user_can( 'wc_memberships_access_all_restricted_content' ) ) {

				$is_member = $is_admin = true;

			// no plans are specified: check if user is active member of at least one plan
			} elseif ( empty( $membership_plans ) ) {

				// if access is only after trial, though, then we need to loop memberships and skip subscription-tied memberships in free trial
				if ( $after_free_trial ) {

					$user_memberships = $this->get_active_user_memberships( $user_id );
					$access_offset    = $this->get_free_trial_offset_time( $user_memberships );
					$is_member        = $access_offset <= 0;

				} else {

					$is_member = wc_memberships_is_user_active_member( $user_id );
				}

			// grant access to content only to members of specific membership plans only
			} else {

				// again, if only allowing access after free trial, we need to loop memberships
				if ( $after_free_trial ) {

					$user_memberships = $this->get_active_user_memberships( $user_id, $membership_plans );
					$access_offset    = $this->get_free_trial_offset_time( $user_memberships );
					$is_member        = $access_offset <= 0;

				} else {

					foreach ( $membership_plans as $membership_plan_id ) {

						if ( wc_memberships_is_user_active_member( $user_id, (int) $membership_plan_id ) ) {

							$is_member = true;
							break;
						}
					}
				}
			}

			// if access condition is not immediate, treat as non-member until further evaluation is done
			if ( ! $is_admin && ( $is_member || $access_offset > 0 ) && 'immediate' !== $delay_access ) {

				$is_member = false;

				// the delay access could be a date in ISO format or a period
				if ( Framework\SV_WC_Helper::str_exists( $delay_access, '-' ) ) {

					// fixed dates access dripping disregard free trial offset
					$access_offset = $this->get_date_offset_time( $delay_access );
					$is_member     = $access_offset <= 0;

				} elseif ( is_string( $delay_access ) ) {

					// for relative periods, these can be cumulative with a free trial offset
					$access_offset += $this->get_period_offset_time( $delay_access, $membership_plans, $user_memberships );
					$is_member      = $access_offset <= 0;
				}
			}
		}

		// final check for delayed access scheduling
		if ( ! $is_member && ! $is_admin && $access_offset > 0 ) {
			// if non-member because of a delay access, confirm that is not scheduled for the same day, as that's the smallest unit we consider for dripping
			$today_time = current_time( 'timestamp', true );
			$delay_time = $access_offset + $today_time;
			$is_member  = (int) date( 'Ymd', $delay_time ) === (int) date( 'Ymd', $today_time );
		}

		// Store the access offset for retrieval via getter
		$this->access_offset = $access_offset;

		return $is_member || $is_admin;
	}


	/**
	 * Gets the access time offset from the most recent validation.
	 *
	 * @since 1.27.3
	 *
	 * @return int access time offset in seconds
	 */
	public function getAccessOffset() : int {

		return $this->access_offset;
	}


	/**
	 * Validates if user can access non-member content.
	 *
	 * @since 1.27.3
	 *
	 * @param array $attributes block attributes
	 * @param int|null $user_id user ID (defaults to current user)
	 * @return bool true if user has access, false otherwise
	 */
	public function canAccessNonMemberContent( array $attributes, ?int $user_id = null ) : bool {

		$is_non_member      = true;
		$user_id            = $user_id ?: get_current_user_id();
		$membership_plans   = ! empty( $attributes['membershipPlans'] ) ? (array) $attributes['membershipPlans'] : [];

		if ( $user_id > 0 ) {

			// skip admins: they are treated as members
			if ( current_user_can( 'wc_memberships_access_all_restricted_content' ) ) {

				$is_non_member = false;

			// non-members of any plan
			} elseif ( empty( $membership_plans ) ) {

				$is_non_member = ! wc_memberships_is_user_active_member( $user_id );

			// non-members of specific plans
			} else {

				foreach ( $membership_plans as $membership_plan ) {

					if ( wc_memberships_is_user_active_member( $user_id, $membership_plan ) ) {

						$is_non_member = false;
						break;
					}
				}
			}
		}

		return $is_non_member;
	}


	/**
	 * Gets all user memberships that have an active status.
	 *
	 * @since 1.27.3
	 *
	 * @param int $user_id the user ID to retrieve memberships for
	 * @param int[] $plans membership plan IDs to limit results for
	 * @return \WC_Memberships_User_Membership[] array of user memberships
	 */
	private function get_active_user_memberships( int $user_id, array $plans = [] ) : array {

		$user_memberships = wc_memberships_get_user_active_memberships( $user_id );

		if ( ! empty( $plans ) ) {

			foreach ( $user_memberships as $user_membership_id => $user_membership ) {

				if ( ! in_array( $user_membership->get_plan_id(), $plans, false ) ) {

					unset( $user_memberships[ $user_membership_id ] );
				}
			}
		}

		return $user_memberships;
	}


	/**
	 * Gets a user access delay offset if they have access only through a free trial membership.
	 *
	 * If the user has more than one membership in free trial, picks the user membership with the earliest free trial end.
	 *
	 * @since 1.27.3
	 *
	 * @param \WC_Memberships_User_Membership[] $user_memberships active user memberships
	 * @return int offset time in seconds
	 */
	private function get_free_trial_offset_time( array $user_memberships ) : int {

		$free_trial_end_time = [];

		foreach ( $user_memberships as $id => $user_membership ) {

			if ( $user_membership instanceof \WC_Memberships_Integration_Subscriptions_User_Membership && $user_membership->has_status( 'free_trial' ) ) {

				$free_trial_end_time[] = $user_membership->get_free_trial_end_date( 'timestamp' );

			} else {

				// bail as the user has access via an active membership that is not in free trial
				$free_trial_end_time[] = 0;
				break;
			}
		}

		// pick the earliest date by comparing timestamps
		$free_trial_end_time = ! empty( $free_trial_end_time ) ? min( $free_trial_end_time ) : 0;

		// obtain the time remaining to the earliest date when a free trial membership that grants access ends
		return $free_trial_end_time > 0 ? $this->get_date_offset_time( date( 'Y-m-d H:i:s', $free_trial_end_time ) ) : 0;
	}


	/**
	 * Gets a time offset for a given date compared to today's date.
	 *
	 * Calculates the relative time remaining to a set date from today's date.
	 *
	 * @since 1.27.3
	 *
	 * @param string $date date in MySQL format
	 * @return int time remaining from today to reach the date (0 if in the past)
	 */
	private function get_date_offset_time( string $date ) : int {

		try {

			// we parse dates assuming local timezone, as that's likely the user intention when setting them, but convert to UTC for internal purposes
			$utc_timezone  = new \DateTimeZone( 'UTC' );
			$site_timezone = new \DateTimeZone( wc_timezone_string() );

			$today_date = new \DateTime( 'now', $site_timezone );
			$today_date->setTimezone( $utc_timezone );

			$today_time = $today_date->getTimestamp();

			$access_date = new \DateTime( $date, $site_timezone );
			$access_date->setTimezone( $utc_timezone );

			$access_offset = $access_date->getTimestamp();

			if ( $access_offset > $today_time ) {
				$access_offset -= $today_time;
			} else {
				$access_offset = 0;
			}

		} catch ( \Exception $e ) {

			$access_offset = 0;
		}

		return $access_offset;
	}


	/**
	 * Gets a time offset relative to the oldest start date of active user memberships.
	 *
	 * @since 1.27.3
	 *
	 * @param string $period a period of time defined as "n days", "n weeks", "n months", "n years"...
	 * @param int[] $membership_plans optional IDs of membership plans to validate any user membership (may be none, if any plan is valid)
	 * @param \WC_Memberships_User_Membership[] $user_memberships optional memberships whose start times will be used to calculate the relative offset from (if unspecified will fetch all user memberships for the current user)
	 * @return int offset time in seconds
	 */
	private function get_period_offset_time( string $period, array $membership_plans = [], array $user_memberships = [] ) : int {

		$access_offset = 0;
		$period_parts  = explode( ' ', $period );
		$period_amount = isset( $period_parts[0] ) ? (int) $period_parts[0] : 0;
		$period_type   = isset( $period_parts[1] ) ? trim( (string) $period_parts[1] ) : '';

		if ( $period_amount > 0 && in_array( $period_type, [ 'days', 'weeks', 'months', 'years' ], true ) ) {

			$all_active_time   = [];
			$delay_access_time = $period_amount;
			$user_memberships  = ! empty( $user_memberships ) ? $user_memberships : $this->get_active_user_memberships( get_current_user_id(), $membership_plans );

			// note: to handle months we need to calculate the access time relative to each membership start date, as they're of variable length
			switch ( $period_type ) {
				case 'days' :
					$delay_access_time *= DAY_IN_SECONDS;
				break;
				case 'weeks' :
					$delay_access_time *= WEEK_IN_SECONDS;
				break;
				case 'years' :
					$delay_access_time *= YEAR_IN_SECONDS;
				break;
				default :
					$delay_access_time = 0;
				break;
			}

			foreach ( $user_memberships as $user_membership ) {

				if ( empty( $membership_plans ) || in_array( $user_membership->get_plan_id(), $membership_plans, false ) ) {

					// calculate months offset time relative to the membership's start date
					if ( 'months' === $period_type ) {
						$start_time        = $user_membership->get_start_date( 'timestamp' );
						$delay_access_time = wc_memberships_add_months_to_timestamp( $start_time, $period_amount ) - $start_time;
					}

					$total_active_time = $user_membership->get_total_active_time();

					if ( $total_active_time > $delay_access_time ) {

						// bail as we have at least one membership with immediate access
						$all_active_time = [];
						break;
					}

					$all_active_time[] = $total_active_time;
				}
			}

			// pick the longest amount of time a membership has been active and subtract the period offset, to obtain the shortest amount of time possible
			$access_offset = ! empty( $all_active_time ) ? absint( max( $all_active_time ) - $delay_access_time ) : 0;
		}

		return $access_offset;
	}


}
