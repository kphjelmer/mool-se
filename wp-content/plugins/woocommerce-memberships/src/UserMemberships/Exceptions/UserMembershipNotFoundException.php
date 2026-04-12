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

namespace SkyVerge\WooCommerce\Memberships\UserMemberships\Exceptions;

use Exception;

defined('ABSPATH') or exit;

/**
 * Exception thrown when a user membership ID doesn't resolve to a valid user membership.
 *
 * @since 1.28.0
 */
class UserMembershipNotFoundException extends Exception
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
			$message = __('User membership not found.', 'woocommerce-memberships');
		}

		parent::__construct($message, $code, $previous);
	}
}
