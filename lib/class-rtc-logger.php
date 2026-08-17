<?php
/**
 * Debug logging for RTC integration.
 *
 * @package Realtime_Collaboration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple logger for RTC operations.
 *
 * Logs to debug.log when WP_DEBUG_LOG is enabled.
 */
class RTC_Logger {

	/**
	 * Log a message with context.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public static function log( $message, $context = array() ) {
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		$formatted = sprintf(
			'[RTC] %s',
			$message
		);

		if ( ! empty( $context ) ) {
			$formatted .= ' ' . wp_json_encode( $context, JSON_UNESCAPED_SLASHES );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $formatted );
	}

	/**
	 * Log storage method call.
	 *
	 * @param string $method Method name.
	 * @param string $room   Room identifier.
	 * @param mixed  $data   Additional data.
	 */
	public static function storage( $method, $room, $data = null ) {
		$context = array( 'room' => $room );

		if ( null !== $data ) {
			if ( is_array( $data ) ) {
				$context['count'] = count( $data );
			} else {
				$context['data'] = $data;
			}
		}

		self::log( "Storage::{$method}()", $context );
	}

	/**
	 * Log Presence API call.
	 *
	 * @param string $function Function name.
	 * @param string $room     Room identifier.
	 * @param mixed  $result   Result data.
	 */
	public static function presence( $function, $room, $result = null ) {
		$context = array( 'room' => $room );

		if ( is_array( $result ) ) {
			$context['entries'] = count( $result );
		} elseif ( null !== $result ) {
			$context['result'] = $result;
		}

		self::log( "Presence::{$function}()", $context );
	}

	/**
	 * Log integration event.
	 *
	 * @param string $event Event name.
	 * @param array  $data  Event data.
	 */
	public static function event( $event, $data = array() ) {
		self::log( "Event: {$event}", $data );
	}
}
