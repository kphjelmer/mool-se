<?php

declare(strict_types=1);

namespace OM4\WooCommerceZapier;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use OM4\WooCommerceZapier\API\API;
use OM4\WooCommerceZapier\AdminUI;
use OM4\WooCommerceZapier\Auth\AuthKeyRotator;
use OM4\WooCommerceZapier\Auth\SessionAuthenticate;
use OM4\WooCommerceZapier\ContainerService;
use OM4\WooCommerceZapier\Helper\FeatureChecker;
use OM4\WooCommerceZapier\Installer;
use OM4\WooCommerceZapier\LegacyMigration\Uninstaller as LegacyUninstaller;
use OM4\WooCommerceZapier\NewUser\NewUser;
use OM4\WooCommerceZapier\Plugin\Bookings\Plugin as BookingsPlugin;
use OM4\WooCommerceZapier\Plugin\Memberships\Plugin as MembershipsPlugin;
use OM4\WooCommerceZapier\Plugin\Subscriptions\Plugin as SubscriptionsPlugin;
use OM4\WooCommerceZapier\TaskHistory\Installer as TaskHistoryInstaller;
use OM4\WooCommerceZapier\TaskHistory\Listener\TriggerListener;
use OM4\WooCommerceZapier\Uninstall;
use OM4\WooCommerceZapier\Webhook\DeliveryFilter as WebhookDeliveryFilter;
use OM4\WooCommerceZapier\Webhook\Installer as WebhookInstaller;
use OM4\WooCommerceZapier\Webhook\Resources as WebhookResources;
use OM4\WooCommerceZapier\WooCommerceResource\Manager as ResourceManager;

defined( 'ABSPATH' ) || exit;

/**
 * Main Zapier Integration 2.0 Plugin class.
 * Bootstraps the plugin, with things starting during the `plugins_loaded` hook,
 * after all WordPress plugins have loaded and WooCommerce has initialised.
 *
 * @since 2.0.0
 */
class Plugin {

	/** The minimum WooCommerce version that this plugin supports. */
	const MINIMUM_SUPPORTED_WOOCOMMERCE_VERSION = '8.3.0';

	/** URL to the documentation for this plugin. */
	const DOCUMENTATION_URL = 'https://docs.tectalic.com/woocommerce-zapier/';

	/** Analytics tags formatted as a URL query string. `utm_campaign` needs to be set individually. */
	const UTM_TAGS = '?utm_source=woocommerce&utm_medium=plugin&utm_campaign=';

	/**
	 * ContainerService instance.
	 *
	 * @var ContainerService
	 */
	protected $container;

	/**
	 * Plugin constructor.
	 *
	 * @param ContainerService $container The Container.
	 */
	public function __construct( ContainerService $container ) {
		$this->container = $container;
	}

	/**
	 * Executed during the 'plugins_loaded' WordPress hook.
	 * - Checks that we're running the correct WooCommerce Version
	 * - Sets up various hooks
	 * - Load Supported Zapier Triggers
	 * - Loads the admin/dashboard interface if required
	 *
	 * @return void
	 */
	public function plugins_loaded() {

		load_plugin_textdomain( 'woocommerce-zapier', false, dirname( plugin_basename( WC_ZAPIER_PLUGIN_FILE ) ) . '/languages' );

		if ( ! $this->container->get( FeatureChecker::class )->class_exists( 'WooCommerce' ) ) {
			// WooCommerce plugin not installed.
			add_action( 'admin_notices', array( $this, 'admin_notice_missing_woocommerce' ) );
			return;
		}

		if ( version_compare( WC_VERSION, self::MINIMUM_SUPPORTED_WOOCOMMERCE_VERSION, '<' ) ) {
			// WooCommerce plugin is older than our minimum required version.
			add_action( 'admin_notices', array( $this, 'admin_notice_unsupported_woocommerce_version' ) );
			return;
		}

		// Our minimum requirements are all met, let's get started!

		add_action( 'before_woocommerce_init', array( $this, 'before_woocommerce_init' ) );
		add_action( 'woocommerce_init', array( $this, 'woocommerce_init' ) );
		add_action( 'init', array( $this, 'initialise' ), 9 );

		add_filter( 'plugin_action_links_' . plugin_basename( WC_ZAPIER_PLUGIN_FILE ), array( $this, 'action_links' ) );
		register_uninstall_hook( WC_ZAPIER_PLUGIN_FILE, array( Uninstall::class, 'run' ) );
	}

	/**
	 * Initialise our functionality that needs to be initialised before WooCommerce loads and enqueues
	 * its active webhooks.
	 * Executed during the `before_woocommerce_init` hook to ensure it loads *before* WooCommerce
	 * loads all active webhooks (which occurs during `init` ie before `woocommerce_init`).
	 *
	 * @return void
	 */
	public function before_woocommerce_init() {
		$this->declare_hpos_compatibility();
		$this->declare_cart_checkout_blocks_compatibility();

		$this->third_party_plugin_compatibility();

		$this->container->get( ResourceManager::class )->initialise();
		$this->container->get( WebhookDeliveryFilter::class )->initialise();
		$this->container->get( TriggerListener::class )->initialise();
		$this->container->get( WebhookResources::class )->initialise();
	}

	/**
	 * Declare compatibility (or incompatibility) with WooCommerce's High-Performance Order Storage (HPOS) feature.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	protected function declare_hpos_compatibility() {
		FeaturesUtil::declare_compatibility( 'custom_order_tables', plugin_basename( WC_ZAPIER_PLUGIN_FILE ), true );
	}

	/**
	 * Declare compatibility with WooCommerce's Cart & Checkout Blocks feature.
	 *
	 * @since 2.10.0
	 *
	 * @return void
	 */
	protected function declare_cart_checkout_blocks_compatibility() {
		FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', plugin_basename( WC_ZAPIER_PLUGIN_FILE ), true );
	}

	/**
	 * Initialise compatibility functionality for third party plugins (such as WooCommerce Subscriptions).
	 * Executed early during `before_woocommerce_init`.
	 *
	 * @return void
	 */
	protected function third_party_plugin_compatibility() {
		$this->container->get( BookingsPlugin::class )->initialise();
		$this->container->get( MembershipsPlugin::class )->initialise();
		$this->container->get( SubscriptionsPlugin::class )->initialise();
	}

	/**
	 * Initialise the plugin's functionality.
	 * Executed during the `woocommerce_init` hook.
	 *
	 * @return void
	 */
	public function woocommerce_init() {
		$this->container->get( API::class )->initialise();
	}

	/**
	 * Functionality that needs to be instantiated during `init`.
	 * Includes SessionAuthenticate (rewrite rule additions) because they need
	 * to be included before rewrite rules are flushed by WordPress on the
	 * Settings, Permalinks screen.
	 * Executed during the `init` hook.
	 *
	 * @return void
	 */
	public function initialise() {
		$this->container->get( SessionAuthenticate::class )->initialise();
		$this->container->get( AuthKeyRotator::class )->initialise();
		$this->container->get( NewUser::class )->initialise();
		$this->container->get( Installer::class )->initialise();
		$this->container->get( TaskHistoryInstaller::class )->initialise();
		$this->container->get( WebhookInstaller::class )->initialise();
		$this->container->get( LegacyUninstaller::class )->initialise();

		if ( is_admin() ) {
			$this->admin();
		}
	}

	/**
	 * Override the Zapier Integration plugin's action links.
	 * Displayed beside the activate/deactivate links on WordPress' Plugins screen.
	 *
	 * @param array $links Array of plugin action links.
	 *
	 * @return array
	 */
	public function action_links( $links ) {

		$plugin_links = array(
			'<a href="' . $this->container->get( Settings::class )->get_settings_page_url() . '">' . __( 'Settings', 'woocommerce-zapier' ) . '</a>',
			'<a href="' . $this->container->get( AdminUI::class )->get_url() . '">' . __( 'Task History', 'woocommerce-zapier' ) . '</a>',
			'<a href="' . self::DOCUMENTATION_URL . self::UTM_TAGS . 'plugins">' . __( 'Docs', 'woocommerce-zapier' ) . '</a>',
			'<a href="' . self::DOCUMENTATION_URL . 'support/' . self::UTM_TAGS . 'plugins">' . __( 'Support', 'woocommerce-zapier' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Administration/Dashboard functionality,
	 * executed if the user is in the Admin/Dashboard.
	 *
	 * @return void
	 */
	public function admin() {
		$this->container->get( AdminUI::class )->initialise();
		$this->container->get( Privacy::class );
	}

	/**
	 * Displays a message if WooCommerce not active.
	 *
	 * @return void
	 */
	public function admin_notice_missing_woocommerce() {
		$class   = 'notice notice-error';
		$message = __( 'Zapier Integration for WooCommerce requires WooCommerce. Please install and activate WooCommerce and try again.', 'woocommerce-zapier' );
		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	/**
	 * Displays a message if the user isn't using a supported version of WooCommerce.
	 *
	 * @return void
	 */
	public function admin_notice_unsupported_woocommerce_version() {
		?>
		<div id="message" class="error">
			<p>
			<?php
			echo esc_html(
				sprintf(
					// Translators: %s: WooCommerce Version.
					__( 'Zapier Integration for WooCommerce requires WooCommerce version %s or later. Please update WooCommerce.', 'woocommerce-zapier' ),
					self::MINIMUM_SUPPORTED_WOOCOMMERCE_VERSION
				)
			);
			?>
			</p>
		</div>
			<?php
	}
}
