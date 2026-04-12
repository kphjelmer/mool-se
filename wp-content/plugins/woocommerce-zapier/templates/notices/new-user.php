<?php

/**
 * Content for the notice that is displayed to first-time users.
 *
 * @since 2.0.0.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<h2><?php echo esc_html( __( 'Welcome to Zapier Integration for WooCommerce!', 'woocommerce-zapier' ) ); ?></h2>
<ul>
	<li><?php echo wp_kses_post( __( '<strong>Explore the Power of Data:</strong> Instantly connect and manage Orders, Products, Coupons, Customers, Subscriptions, Bookings, Memberships, and more.', 'woocommerce-zapier' ) ); ?></li>
	<li><?php echo wp_kses_post( __( '<strong>70+ Triggers:</strong> Unleash your creativity with precise control over when and how your WooCommerce data flows to Zapier.', 'woocommerce-zapier' ) ); ?></li>
	<li><?php echo wp_kses_post( __( '<strong>25+ Actions:</strong> Effortlessly create, find, and update WooCommerce data directly from your Zaps.', 'woocommerce-zapier' ) ); ?></li>
	<li><?php echo wp_kses_post( __( '<strong>Stay Ahead of the Curve:</strong> Enjoy full support for the latest WooCommerce features, including High Performance Order Storage (HPOS) and the sleek new Cart and Checkout Blocks.', 'woocommerce-zapier' ) ); ?></li>
</ul>
<p>
<?php
	echo wp_kses_post(
		sprintf(
			// Translators: 1: Quick Start URL, 2: What's New URL.
			__( '<a class="button button-primary" target="_blank" href="%1$s">Quick Start</a>&nbsp;&nbsp;<a class="button" target="_blank" href="%2$s">What\'s New</a>', 'woocommerce-zapier' ),
			esc_attr( $quick_start_url ),
			esc_attr( $whats_new_url )
		)
	);
	?>
</p>
