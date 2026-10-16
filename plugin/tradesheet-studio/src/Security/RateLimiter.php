<?php
/**
 * Transient-backed fixed-window rate limiter for public endpoints
 * (token-authenticated buyer actions, CSV uploads).
 *
 * @package TradeSheetStudio\Security
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Security;

final class RateLimiter {

	/**
	 * @param string $key        Bucket key (already hashed/safe).
	 * @param int    $maxActions Allowed actions per window.
	 * @param int    $window     Window in seconds.
	 */
	public static function allow( string $key, int $maxActions, int $window ): bool {
		$bucket = 'tss_rl_' . md5( $key );
		$now    = time();
		$entry  = get_transient( $bucket );

		if ( ! is_array( $entry ) || ! isset( $entry['reset'], $entry['count'] ) || (int) $entry['reset'] <= $now ) {
			$entry = array(
				'reset' => $now + max( 1, $window ),
				'count' => 0,
			);
		}

		++$entry['count'];

		set_transient( $bucket, $entry, max( 1, $window ) + 5 );

		return $entry['count'] <= max( 1, $maxActions );
	}

	public static function clientKey( string $scope ): string {
		$ip = '';
		if ( isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = wp_unslash( $_SERVER['REMOTE_ADDR'] );
		}

		return $scope . '|' . preg_replace( '/[^0-9a-fA-F:.]/', '', (string) $ip );
	}
}
