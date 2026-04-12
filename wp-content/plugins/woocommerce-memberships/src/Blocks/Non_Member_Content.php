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

use SkyVerge\WooCommerce\Memberships\Blocks\Helpers\BlockAccessValidator;

defined( 'ABSPATH' ) or exit;

/**
 * Non-member block.
 *
 * Creates a block to display content to non-members only.
 *
 * @since 1.15.0
 */
class Non_Member_Content extends Block implements Dynamic_Content_Block {

	public const BLOCK_NAME = 'woocommerce-memberships/non-member-content';

	/**
	 * Block access validator instance.
	 *
	 * @since 1.27.3
	 */
	protected BlockAccessValidator $access_validator;


	/**
	 * Block constructor.
	 *
	 * @since 1.15.0
	 */
	public function __construct() {

		$this->block_type = 'non-member-content';
		$this->access_validator = new BlockAccessValidator();

		parent::__construct();

		add_filter( 'wc_memberships_trimmed_restricted_excerpt', [ $this, 'remove_block_from_restricted_content_excerpt' ], 1, 4 );
	}


	/**
	 * Renders the block content.
	 *
	 * Displays content to non members.
	 *
	 * @since 1.15.0
	 *
	 * @param array $attributes block attributes
	 * @param string $content HTML content
	 * @return string HTML
	 */
	public function render( $attributes, $content ) {
		return $this->access_validator->canAccessNonMemberContent( $attributes ) ? $content : '';
	}
}
