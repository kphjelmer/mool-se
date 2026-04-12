<?php

namespace OM4\WooCommerceZapier;

defined( 'ABSPATH' ) || exit;

/**
 * Zapier Integration Uninstall routine.
 * Deletes all existing data from the database, including database table and
 * options. Only remove all plugin settings/data if the `WC_ZAPIER_REMOVE_ALL_DATA`
 * constant is defined and set to true in user's wp-config.php.
 *
 * @since 2.0.0
 */
class Uninstall {

	/**
	 * Run the uninstall routine
	 *
	 * @return void
	 */
	public static function run() {
		if ( ! defined( 'WC_ZAPIER_REMOVE_ALL_DATA' ) || true !== WC_ZAPIER_REMOVE_ALL_DATA ) {
			return;
		}

		global $wpdb;

		// Delete Task History database table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}wc_zapier_history`" );

		// Delete all existing Zapier Integration Webhooks.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM `{$wpdb->prefix}wc_webhooks` WHERE `name` = 'WooCommerce Zapier' AND `delivery_url` LIKE '%hooks.zapier.com%'" );

		// Delete all existing Zapier Integration REST API Keys.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM `{$wpdb->prefix}woocommerce_api_keys` WHERE `description` LIKE '%Zapier%'" );

		// Delete all options/settings.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM `$wpdb->options` WHERE option_name LIKE 'wc\_zapier\_%';" );

		// Clear any cached data that has been removed.
		wp_cache_flush();
	}
}
