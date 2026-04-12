<?php

namespace SkyVerge\WooCommerce\Memberships\Plans\Exceptions;

defined('ABSPATH') or exit;

/**
 * Exception thrown when a plan ID doesn't resolve to a valid membership plan.
 *
 * @since 1.28.0
 */
class PlanNotFoundException extends \Exception
{
	/**
	 * Constructor.
	 *
	 * @since 1.28.0
	 *
	 * @param string $message error message
	 * @param int $code error code
	 * @param \Throwable|null $previous the previous exception used for exception chaining
	 */
	public function __construct($message = '', $code = 404, $previous = null)
	{
		if (! $message) {
			$message = __('Plan not found.', 'woocommerce-memberships');
		}

		parent::__construct($message, $code, $previous);
	}
}
