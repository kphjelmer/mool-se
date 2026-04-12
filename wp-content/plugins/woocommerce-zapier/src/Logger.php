<?php

namespace OM4\WooCommerceZapier;

use OM4\WooCommerceZapier\Exception\InvalidLogLevelException;
use WC_Logger_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Internal logger utilising the WooCommerces WC_Logger class
 * Implements the \Psr\Log\LoggerInterface interface
 *
 * @see https://www.php-fig.org/psr/psr-3/ PSR-3: Logger Interface.
 *
 * @since 2.0.0
 */
class Logger {

	/**
	 * Default log level. Everything which equal or below is always logged
	 * regardless of whether detailed logging is enabled in settings.
	 */
	const DEFAULT_LEVEL = 4;

	/**
	 * Valid log levels
	 *
	 * @var array
	 */
	protected $levels = array(
		0 => 'emergency',
		1 => 'alert',
		2 => 'critical',
		3 => 'error',
		4 => 'warning',
		5 => 'notice',
		6 => 'info',
		7 => 'debug',
	);

	/**
	 * WC logger instance.
	 *
	 * @var WC_Logger_Interface
	 */
	protected $wc_logger;

	/**
	 * Logger context the WC_Logger uses to group content together.
	 *
	 * @var array
	 */
	protected $wc_logger_context;

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings          = $settings;
		$this->wc_logger         = \wc_get_logger();
		$this->wc_logger_context = array( 'source' => 'woocommerce-zapier' );
	}

	/**
	 * System is unusable.
	 *
	 * @param  string       $message The message to be logged. Can be formatted for printf.
	 * @param  array|string $context [optional] Dynamic part of the formatted message.
	 * @param  array        $wc_logger_context [optional] Additional context for WooCommerce's logger. For example, a `backtrace`.
	 *
	 * @return void
	 */
	public function emergency( $message, $context = array(), $wc_logger_context = array() ) {
		$this->log( 'emergency', $message, $context, $wc_logger_context );
	}

	/**
	 * Action must be taken immediately
	 *
	 * @param  string       $message The message to be logged. Can be formatted for printf.
	 * @param  array|string $context [optional] Dynamic part of the formatted message.
	 * @param  array        $wc_logger_context [optional] Additional context for WooCommerce's logger. For example, a `backtrace`.
	 *
	 * @return void
	 */
	public function alert( $message, $context = array(), $wc_logger_context = array() ) {
		$this->log( 'alert', $message, $context, $wc_logger_context );
	}

	/**
	 * Critical conditions
	 *
	 * @param  string       $message The message to be logged. Can be formatted for printf.
	 * @param  array|string $context [optional] Dynamic part of the formatted message.
	 * @param  array        $wc_logger_context [optional] Additional context for WooCommerce's logger. For example, a `backtrace`.
	 *
	 * @return void
	 */
	public function critical( $message, $context = array(), $wc_logger_context = array() ) {
		$this->log( 'critical', $message, $context, $wc_logger_context );
	}

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored
	 *
	 * @param  string       $message The message to be logged. Can be formatted for printf.
	 * @param  array|string $context [optional] Dynamic part of the formatted message.
	 * @param  array        $wc_logger_context [optional] Additional context for WooCommerce's logger. For example, a `backtrace`.
	 *
	 * @return void
	 */
	public function error( $message, $context = array(), $wc_logger_context = array() ) {
		$this->log( 'error', $message, $context, $wc_logger_context );
	}

	/**
	 * Exceptional occurrences that are not errors
	 *
	 * @param  string       $message The message to be logged. Can be formatted for printf.
	 * @param  array|string $context [optional] Dynamic part of the formatted message.
	 * @param  array        $wc_logger_context [optional] Additional context for WooCommerce's logger. For example, a `backtrace`.
	 *
	 * @return void
	 */
	public function warning( $message, $context = array(), $wc_logger_context = array() ) {
		$this->log( 'warning', $message, $context, $wc_logger_context );
	}

	/**
	 * Normal but significant events
	 *
	 * @param  string       $message The message to be logged. Can be formatted for printf.
	 * @param  array|string $context [optional] Dynamic part of the formatted message.
	 * @param  array        $wc_logger_context [optional] Additional context for WooCommerce's logger. For example, a `backtrace`.
	 *
	 * @return void
	 */
	public function notice( $message, $context = array(), $wc_logger_context = array() ) {
		$this->log( 'notice', $message, $context, $wc_logger_context );
	}

	/**
	 * Interesting events
	 *
	 * @param  string       $message The message to be logged. Can be formatted for printf.
	 * @param  array|string $context [optional] Dynamic part of the formatted message.
	 * @param  array        $wc_logger_context [optional] Additional context for WooCommerce's logger. For example, a `backtrace`.
	 *
	 * @return void
	 */
	public function info( $message, $context = array(), $wc_logger_context = array() ) {
		$this->log( 'info', $message, $context, $wc_logger_context );
	}

	/**
	 * Detailed debug information
	 *
	 * @param  string       $message The message to be logged. Can be formatted for printf.
	 * @param  array|string $context [optional] Dynamic part of the formatted message.
	 * @param  array        $wc_logger_context [optional] Additional context for WooCommerce's logger. For example, a `backtrace`.
	 *
	 * @return void
	 */
	public function debug( $message, $context = array(), $wc_logger_context = array() ) {
		$this->log( 'debug', $message, $context, $wc_logger_context );
	}

	/**
	 * Logs with an arbitrary level
	 *
	 * @param  string       $log_level  The name of the logging level.
	 * @param  string       $message  The message to be logged. Can be formatted for printf.
	 * @param  array|string $context  [optional] Dynamic part of the formatted message.
	 * @param  array        $wc_logger_context [optional] Additional context for WooCommerce's logger. For example, a `backtrace`.
	 *
	 * @return void
	 * @throws InvalidLogLevelException In case the log level is invalid.
	 */
	public function log( $log_level, $message, $context = array(), $wc_logger_context = array() ) {
		$message = $this->assemble_message( $message, $context );
		if ( ! in_array( $log_level, $this->levels, true ) ) {
			throw new InvalidLogLevelException( $log_level, $message );
		}

		/*
		 * If detailed logging isn't on, then only log messages below (more critical) than the default level.
		 * If detailed logging is on, then log all levels all of the time.
		 */
		if (
			! $this->settings->is_detailed_logging_enabled() &&
			static::DEFAULT_LEVEL < array_search( $log_level, $this->levels, true )
		) {
			return;
		}

		$this->wc_logger->log( $log_level, $message, array_merge( $this->wc_logger_context, $wc_logger_context ) );
	}

	/**
	 * Combine message with provided context
	 * Using vsprintf for formatting.
	 *
	 * @param  string       $message The message to be logged. Can be formatted for printf.
	 * @param  array|string $context [optional] Dynamic part of the formatted message.
	 *
	 * @return string
	 */
	protected function assemble_message( $message, $context = array() ) {
		$context = is_array( $context ) ? $context : array( $context );
		if ( ! empty( $context ) ) {
			return vsprintf( $message, $context );
		}
		return $message;
	}
}
