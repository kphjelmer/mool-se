<?php

namespace SkyVerge\WooCommerce\Memberships\Plans\Exceptions;

use Exception;

defined('ABSPATH') or exit;

/**
 * Exception thrown when a plan has active memberships and cannot be deleted.
 *
 * @since 1.28.0
 */
class PlanNotDeletableException extends Exception
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
	public function __construct($message = '', $code = 422, $previous = null)
	{
		if (! $message) {
			$message = __('Plan cannot be deleted because it has active memberships.', 'woocommerce-memberships');
		}

		parent::__construct($message, $code, $previous);
	}
}
