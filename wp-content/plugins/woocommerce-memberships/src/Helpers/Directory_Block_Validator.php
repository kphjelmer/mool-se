<?php
/**
 * WooCommerce Memberships.
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

namespace SkyVerge\WooCommerce\Memberships\Helpers;

use SkyVerge\WooCommerce\Memberships\Blocks\Helpers\BlockAccessValidator;
use SkyVerge\WooCommerce\Memberships\Blocks\Member_Content;
use SkyVerge\WooCommerce\Memberships\Blocks\Members_Directory;
use SkyVerge\WooCommerce\Memberships\Blocks\Non_Member_Content;
use WP_REST_Request;

defined( 'ABSPATH' ) or exit;

/**
 * Directory Block Validator.
 *
 * Handles validation and parsing of directory block settings for secure API access.
 *
 * @since 1.27.3
 */
class Directory_Block_Validator {


	/**
	 * Validates if the current user can access the specified page.
	 *
	 * @since 1.27.3
	 *
	 * @param int|mixed $page_id page ID to validate access for
	 * @return bool true if user can access page, false otherwise
	 */
	public function can_user_access_page( $page_id ) : bool {

		if ( ! $page_id || ! is_numeric( $page_id ) ) {
			return false;
		}

		$page = get_post( (int) $page_id );

		if ( ! $page ) {
			return false;
		}

		// below current_user_can() call not working for logged out users, so we need a generic check for published pages
		if ( 'publish' === $page->post_status ) {
			return true;
		}

		return current_user_can( 'read_post', (int) $page_id );
	}


	/**
	 * Gets and validates block settings from the specified page.
	 *
	 * @since 1.27.3
	 *
	 * @param WP_REST_Request $request request object containing page_id and optional block_instance_id
	 * @return array validated block settings with privacy controls
	 */
	public function get_validated_block_settings( WP_REST_Request $request ) : array {

		$page_id = (int) $request->get_param( 'page_id' );
		$block_instance_id = $request->get_param( 'block_instance_id' ) ?: null;

		$page = get_post( $page_id );

		if ( ! $page ) {
			// Fallback to most restrictive settings
			return $this->get_most_restrictive_block_settings();
		}

		$blocks = parse_blocks( $page->post_content );

		return $this->get_directory_block_settings( $blocks, $block_instance_id );
	}


	/**
	 * Extracts directory block settings from parsed blocks with graceful fallback.
	 *
	 * Note: This method assumes access validation has already been performed
	 * (typically in the permission callback via can_user_access_block()).
	 *
	 * @since 1.27.3
	 *
	 * @param array $blocks parsed blocks from post content
	 * @param string|null $block_instance_id optional block instance ID for specific block targeting
	 * @return array block settings with privacy controls
	 */
	protected function get_directory_block_settings( array $blocks, ?string $block_instance_id = null ) : array {

		$directory_blocks = $this->find_directory_blocks( $blocks );

		if ( empty( $directory_blocks ) ) {
			// No directory blocks found, use most restrictive settings
			return $this->get_most_restrictive_block_settings();
		}

		// Strategy 1: If block_instance_id provided, try to find matching block
		if ( $block_instance_id ) {
			foreach ( $directory_blocks as $block ) {
				$attrs = $block['attrs'] ?? [];
				if ( isset( $attrs['blockInstanceId'] ) && $attrs['blockInstanceId'] === $block_instance_id ) {
					return $this->normalize_block_settings( $attrs );
				}
			}
		}

		// Strategy 2: If only one directory block exists, use it
		// We'd end up here for blocks created prior to 1.27.3, which don't have an instance ID yet.
		if ( 1 === count( $directory_blocks ) ) {
			$attrs = $directory_blocks[0]['attrs'] ?? [];
			return $this->normalize_block_settings( $attrs );
		}

		// Strategy 3: Multiple blocks found but no specific match - use most restrictive
		return $this->get_most_restrictive_settings_from_blocks( $directory_blocks );
	}


	/**
	 * Recursively finds all directory blocks in parsed block tree.
	 *
	 * @since 1.27.3
	 *
	 * @param array $blocks parsed blocks to search
	 * @return array directory blocks found
	 */
	protected function find_directory_blocks( array $blocks ) : array {

		$directory_blocks = [];

		foreach ( $blocks as $block ) {
			if ( Members_Directory::BLOCK_NAME === $block['blockName'] ) {
				$directory_blocks[] = $block;
			}

			// Recursively search inner blocks
			if ( ! empty( $block['innerBlocks'] ) ) {
				$inner_directory_blocks = $this->find_directory_blocks( $block['innerBlocks'] );
				$directory_blocks = array_merge( $directory_blocks, $inner_directory_blocks );
			}
		}

		return $directory_blocks;
	}


	/**
	 * Normalizes block settings to ensure all required privacy fields are set.
	 *
	 * @since 1.27.3
	 *
	 * @param array $attrs raw block attributes
	 * @return array normalized settings with defaults
	 */
	protected function normalize_block_settings( array $attrs ) : array {

		/**
		 * It seems incorrect to have the defaults be permissive `true` values, but we need to use the same defaults
		 * as defined in the block registration code. This ensures consistency with how Gutenberg saves/omits attributes.
		 * For example: the block default for 'avatar' is `true`, which means when you save it as "On", it will actually
		 * be entirely omitted from $attrs. If we then had a default of `false` here, we'd accidentally be injecting a
		 * false value for 'avatar'.
		 * @see \SkyVerge\WooCommerce\Memberships\Blocks\Members_Directory::register_attributes()
		 */
		$block_editor_defaults = [
			'showBio'     => true,
			'showEmail'   => true,
			'showPhone'   => true,
			'showAddress' => true,
			'avatar'      => true,
			'avatarSize'  => 100,
		];

		return array_merge( $block_editor_defaults, array_intersect_key( $attrs, $block_editor_defaults ) );
	}


	/**
	 * Returns the most restrictive block settings (all privacy fields hidden).
	 *
	 * @since 1.27.3
	 *
	 * @return array most restrictive settings
	 */
	protected function get_most_restrictive_block_settings() : array {

		return [
			'showBio'     => false,
			'showEmail'   => false,
			'showPhone'   => false,
			'showAddress' => false,
			'avatar'      => false,
			'avatarSize'  => 100,
		];
	}


	/**
	 * Gets the most restrictive settings from multiple directory blocks.
	 *
	 * @since 1.27.3
	 *
	 * @param array $directory_blocks array of directory blocks
	 * @return array most restrictive settings across all blocks
	 */
	protected function get_most_restrictive_settings_from_blocks( array $directory_blocks ) : array {

		$restrictive_settings = $this->get_most_restrictive_block_settings();

		// For each privacy setting, only allow it if ALL blocks allow it
		$privacy_fields = [ 'showBio', 'showEmail', 'showPhone', 'showAddress', 'avatar' ];

		foreach ( $privacy_fields as $field ) {
			$allow_field = true;

			foreach ( $directory_blocks as $block ) {
				$attrs = $block['attrs'] ?? [];
				$normalized = $this->normalize_block_settings( $attrs );

				if ( empty( $normalized[ $field ] ) ) {
					$allow_field = false;
					break;
				}
			}

			$restrictive_settings[ $field ] = $allow_field;
		}

		return $restrictive_settings;
	}


	/**
	 * Recursively finds all directory blocks with their parent restriction blocks.
	 *
	 * @since 1.27.3
	 *
	 * @param array $blocks parsed blocks to search
	 * @param array $parent_restrictions parent restriction blocks found so far
	 * @return array directory block contexts with parent restrictions
	 */
	protected function find_directory_blocks_with_parents( array $blocks, array $parent_restrictions = [] ) : array {

		$directory_block_contexts = [];

		foreach ( $blocks as $block ) {
			$block_name = $block['blockName'] ?? '';

			// Check if this is a restriction block
			$is_restriction_block = in_array( $block_name, [
				Member_Content::BLOCK_NAME,
				Non_Member_Content::BLOCK_NAME,
			], true );

			// If this is a restriction block, add it to the parent chain
			$current_parent_restrictions = $parent_restrictions;
			if ( $is_restriction_block ) {
				$current_parent_restrictions[] = $block;
			}

			// Check if this is a directory block
			if ( Members_Directory::BLOCK_NAME === $block_name ) {
				$directory_block_contexts[] = [
					'directory_block'     => $block,
					'parent_restrictions' => $current_parent_restrictions,
				];
			}

			// Recursively search inner blocks with updated parent chain
			if ( ! empty( $block['innerBlocks'] ) ) {
				$inner_contexts = $this->find_directory_blocks_with_parents( $block['innerBlocks'], $current_parent_restrictions );
				$directory_block_contexts = array_merge( $directory_block_contexts, $inner_contexts );
			}
		}

		return $directory_block_contexts;
	}


	/**
	 * Validates a hierarchy of restriction blocks for the current user.
	 * This ensures the user is allowed to access content placed within restriction blocks.
	 *
	 * @since 1.27.3
	 *
	 * @param array $parent_restrictions array of restriction blocks to validate
	 * @return bool true if user passes all restriction validations, false otherwise
	 */
	protected function validate_restriction_block_hierarchy( array $parent_restrictions ) : bool {

		// No parent restrictions means no additional validation needed
		if ( empty( $parent_restrictions ) ) {
			return true;
		}

		$block_validator = new BlockAccessValidator();

		// User must pass ALL restriction blocks in the hierarchy (AND logic)
		foreach ( $parent_restrictions as $restriction_block ) {
			$block_name = $restriction_block['blockName'] ?? '';
			$attributes = $restriction_block['attrs'] ?? [];

			switch ( $block_name ) {
				case Member_Content::BLOCK_NAME:
					$has_access = $block_validator->canAccessMemberContent( $attributes );
					break;

				case Non_Member_Content::BLOCK_NAME:
					$has_access = $block_validator->canAccessNonMemberContent( $attributes );
					break;

				default:
					// Unknown restriction block type - assume access granted
					$has_access = true;
					break;
			}

			// If any restriction block denies access, fail the entire hierarchy (fail secure)
			if ( ! $has_access ) {
				return false;
			}
		}

		return true;
	}


	/**
	 * Validates if the current user can access the specified directory block context.
	 *
	 * @since 1.27.3
	 *
	 * @param WP_REST_Request $request request object containing page_id and optional block_instance_id
	 * @return bool true if user can access the block, false otherwise
	 */
	public function can_user_access_block( WP_REST_Request $request ) : bool {

		$page_id = (int) $request->get_param( 'page_id' );
		$block_instance_id = $request->get_param( 'block_instance_id' ) ?: null;

		$page = get_post( $page_id );
		if ( ! $page ) {
			return false;
		}

		$blocks = parse_blocks( $page->post_content );
		$directory_block_contexts = $this->find_directory_blocks_with_parents( $blocks );

		// Find the specific directory block context for validation
		$target_directory_context = null;

		if ( $block_instance_id ) {
			// Look for specific block instance
			foreach ( $directory_block_contexts as $context ) {
				$attrs = $context['directory_block']['attrs'] ?? [];
				if ( isset( $attrs['blockInstanceId'] ) && $attrs['blockInstanceId'] === $block_instance_id ) {
					$target_directory_context = $context;
					break;
				}
			}
		} elseif ( 1 === count( $directory_block_contexts ) ) {
			// Single directory block - use it
			$target_directory_context = $directory_block_contexts[0];
		}

		// If we found a target directory block, validate its parent restriction blocks
		if ( $target_directory_context && ! empty( $target_directory_context['parent_restrictions'] ) ) {
			return $this->validate_restriction_block_hierarchy( $target_directory_context['parent_restrictions'] );
		}

		// SECURITY: If a specific block_instance_id was requested but not found, deny access
		if ( $block_instance_id && ! $target_directory_context ) {
			return false;
		}

		// No restrictions found or no specific block found - allow access
		return true;
	}


}
