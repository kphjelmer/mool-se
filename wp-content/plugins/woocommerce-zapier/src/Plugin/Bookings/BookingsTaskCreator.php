<?php

declare(strict_types=1);

namespace OM4\WooCommerceZapier\Plugin\Bookings;

use OM4\WooCommerceZapier\TaskHistory\Task\CreatorBase;

defined( 'ABSPATH' ) || exit;

/**
 * Booking Task Creator.
 *
 * @since 2.8.0
 */
class BookingsTaskCreator extends CreatorBase {

	/**
	 * {@inheritDoc}
	 */
	public static function resource_type() {
		return 'booking';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function resource_name() {
		return __( 'Booking', 'woocommerce-zapier' );
	}
}
