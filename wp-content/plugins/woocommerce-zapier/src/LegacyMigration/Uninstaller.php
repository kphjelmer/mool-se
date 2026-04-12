<?php

namespace OM4\WooCommerceZapier\LegacyMigration;

use OM4\WooCommerceZapier\Logger;
use OM4\WooCommerceZapier\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Permanently deletes Legacy Zapier Feeds and configs
 *
 * @since 2.12.0
 */
class Uninstaller {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings  Settings instance.
	 * @param Logger   $logger    The Logger.
	 */
	public function __construct( Settings $settings, Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	/**
	 * Instructs the uninstaller functionality to initialise itself.
	 *
	 * @return void
	 */
	public function initialise() {
		add_action( 'wc_zapier_db_upgrade_v_20_to_21', array( $this, 'remove_legacy_functionality' ) );
	}

	/**
	 * Remove legacy functionality.
	 *
	 * @return void
	 */
	public function remove_legacy_functionality(): void {
		// Delete Legacy settings.
		$this->settings->delete_setting( 'legacy_mode_enabled' );
		$this->settings->delete_setting( 'feed_messages' );

		// Ensure admin notices are removed.
		delete_option( 'woocommerce_admin_notice_wc_zapier_legacy_feeds_deleted' );
		delete_option( 'woocommerce_admin_notice_wc_zapier_legacy_feeds_migration' );

		// Delete all existing 1.9.x Legacy Zapier Feed records.
		$posts_to_delete = get_posts(
			array(
				'post_type'      => 'wc_zapier_feed',
				'posts_per_page' => -1,
			)
		);
		foreach ( $posts_to_delete as $feed ) {
			wp_delete_post( $feed->ID, false );
		}

		if ( ! empty( $posts_to_delete ) ) {
			$this->logger->notice( count( $posts_to_delete ) . ' Legacy Zapier Feeds deleted.' );
		}
	}
}
