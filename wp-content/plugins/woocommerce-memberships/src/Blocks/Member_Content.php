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

namespace SkyVerge\WooCommerce\Memberships\Blocks;

use SkyVerge\WooCommerce\PluginFramework\v6_1_1 as Framework;
use SkyVerge\WooCommerce\Memberships\Blocks\Helpers\BlockAccessValidator;

defined( 'ABSPATH' ) or exit;

/**
 * Restricted content block.
 *
 * Creates a block to display content to members only.
 *
 * @since 1.15.0
 */
class Member_Content extends Block implements Dynamic_Content_Block {

	public const BLOCK_NAME = 'woocommerce-memberships/member-content';

	/**
	 * Block access validator instance.
	 *
	 * @since 1.27.3
	 */
	private BlockAccessValidator $access_validator;


	/**
	 * Block constructor.
	 *
	 * @since 1.15.0
	 */
	public function __construct() {

		$this->block_type = 'member-content';
		$this->access_validator = new BlockAccessValidator();

		parent::__construct();

		add_filter( 'wc_memberships_trimmed_restricted_excerpt', [ $this, 'remove_block_from_restricted_content_excerpt' ], 1, 4 );
	}




	/**
	 * Renders the block content.
	 *
	 * Displays restricted content to members.
	 *
	 * @since 1.15.0
	 *
	 * @param array $attributes block attributes
	 * @param string $content HTML content
	 * @return string HTML
	 */
	public function render( $attributes, $content ) {
		if ( ! $this->access_validator->canAccessMemberContent( $attributes ) ) {

			$restriction_message = isset( $attributes['showRestrictionMessage'] ) ? $attributes['showRestrictionMessage'] : false;
			$restriction_message = 'custom' === $restriction_message && isset( $attributes['customRestrictionMessage'] ) ? $attributes['customRestrictionMessage'] : $restriction_message; // false or 'default', or HTML string

			// Display a restriction message or nothing to non-members
			if ( is_string( $restriction_message ) && '' !== trim( $restriction_message ) ) {
				$membership_plans = isset( $attributes['membershipPlans'] ) ? array_map( 'absint', (array) $attributes['membershipPlans'] ) : [];
				$content = $this->get_content_restricted_message( $restriction_message, $membership_plans, $this->access_validator->getAccessOffset() );
			} else {
				$content = ''; // use no content restricted message, just hide content
			}
		}

		return $content; // nosemgrep
	}


	/**
	 * Gets a block content restricted message.
	 *
	 * Helper method, do not open to public.
	 * @see \WC_Memberships_User_Messages::get_message() for standard messages usage
	 *
	 * @since 1.15.0
	 *
	 * @param string $restriction_message may be 'default' to use a restriction message defined in settings, or full HTML string for a custom one
	 * @param int[] $membership_plans membership plans IDs
	 * @param int $access_time_offset delayed access timestamp offset
	 * @return string HTML
	 */
	private function get_content_restricted_message( $restriction_message, $membership_plans, $access_time_offset ) {

		if ( $access_time_offset > 0 ) {

			$message_code    = 'content_delayed_message';
			$access_time     = current_time( 'timestamp', true ) + $access_time_offset;
			$access_products = [];

		} else {

			$message_code    = 'content_restricted_message';
			$access_time     = 0;
			$access_products = [ [] ];

			// maybe get plans if the restriction applies to all plans
			$membership_plans = empty( $membership_plans ) ? wc_memberships_get_membership_plans() : $membership_plans;

			foreach ( $membership_plans as $membership_plan_id ) {

				if ( $membership_plan = wc_memberships_get_membership_plan( $membership_plan_id ) ) {

					$access_products[] = $membership_plan->get_product_ids();
				}
			}

			// gather products
			$access_products = array_unique( array_merge( ...$access_products ) );

			// if no products, tweak message code
			if ( empty( $access_products ) ) {
				$message_code .= '_no_products';
			}
		}

		$message_args = [
			'context'     => 'content',
			'products'    => array_values( $access_products ),
			'access_time' => $access_time,
		];

		// unless the restriction message is a custom content string, use the default message as stored in settings (or default value)
		if ( 'default' === $restriction_message ) {
			$restriction_message = \WC_Memberships_User_Messages::get_message( $message_code, $message_args );
		}

		$message = \WC_Memberships_User_Messages::parse_message_merge_tags( $restriction_message, $message_args );

		ob_start();

		// ensure that the block HTML class are persisted in the output content ?>
		<div class="<?php echo sanitize_html_class( $this->block_class ); ?>">
			<?php echo \WC_Memberships_User_Messages::get_notice_html( $message_code, $message, $message_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php

		return ob_get_clean();
	}


}
