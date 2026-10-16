<?php
/**
 * Shared utilities: references, tokens, timestamps, CSV safety.
 *
 * All helpers degrade gracefully when WordPress functions are unavailable so
 * pure domain classes stay testable outside a WordPress installation.
 *
 * @package TradeSheetStudio\Support
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Support;

final class Utils {

	public static function nowDb(): string {
		if ( function_exists( 'current_time' ) ) {
			return (string) current_time( 'mysql' );
		}
		return gmdate( 'Y-m-d H:i:s' );
	}

	public static function nowUtc(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Cryptographically secure token (hex).
	 */
	public static function generateToken( int $bytes = 32 ): string {
		try {
			return bin2hex( random_bytes( $bytes ) );
		} catch ( \Throwable $e ) {
			$raw = '';
			for ( $i = 0; $i < $bytes; $i++ ) {
				$raw .= chr( random_int( 0, 255 ) );
			}
			return bin2hex( $raw );
		}
	}

	/**
	 * Tokens are stored hashed; the raw value exists only in the share URL.
	 */
	public static function hashToken( string $token ): string {
		return hash( 'sha256', $token );
	}

	public static function newPacketRef(): string {
		return 'TSS-P-' . strtoupper( self::base36( 4 ) );
	}

	public static function newQuoteRef(): string {
		return 'TSS-Q-' . strtoupper( self::base36( 4 ) );
	}

	private static function base36( int $bytes ): string {
		try {
			$hex = bin2hex( random_bytes( $bytes ) );
		} catch ( \Throwable $e ) {
			$hex = '';
			for ( $i = 0; $i < $bytes; $i++ ) {
				$hex .= str_pad( dechex( random_int( 0, 255 ) ), 2, '0', STR_PAD_LEFT );
			}
		}

		return strtoupper( base_convert( $hex, 16, 36 ) );
	}

	/**
	 * Guard against spreadsheet formula injection when exported cells are
	 * opened in Excel/LibreOffice/Google Sheets (OWASP CSV injection
	 * guidance: neutralise leading formula trigger characters).
	 */
	public static function csvSafe( ?string $value ): string {
		$value = (string) $value;
		if ( '' === $value ) {
			return '';
		}

		$first = $value[0];
		if ( in_array( $first, array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}

		return $value;
	}

	/**
	 * JSON encoding that degrades outside WordPress.
	 */
	public static function jsonEncode( mixed $value ): string {
		if ( function_exists( 'wp_json_encode' ) ) {
			$encoded = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		} else {
			$encoded = json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}

		return is_string( $encoded ) ? $encoded : '';
	}

	/**
	 * Canonical JSON for snapshot checksums: sorted keys, unescaped slashes,
	 * unescaped unicode — deterministic serialization of commercial data.
	 */
	public static function canonicalJson( mixed $value ): string {
		$encoded = json_encode(
			self::canonicalize( $value ),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_SORT_KEYS
		);

		return is_string( $encoded ) ? $encoded : '';
	}

	private static function canonicalize( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			// Re-index numeric sequences and sort associative keys.
			if ( array_keys( $value ) === range( 0, count( $value ) - 1 ) ) {
				$out = array();
				foreach ( $value as $item ) {
					$out[] = self::canonicalize( $item );
				}
				return $out;
			}

			$out = array();
			foreach ( $value as $k => $v ) {
				$out[ (string) $k ] = self::canonicalize( $v );
			}
			ksort( $out, SORT_STRING );

			return $out;
		}

		return $value;
	}

	public static function checksum( string $canonical ): string {
		return hash( 'sha256', $canonical );
	}

	public static function sanitizeText( ?string $value, int $maxLength = 5000 ): string {
		$value = (string) $value;
		if ( function_exists( 'sanitize_text_field' ) ) {
			$value = sanitize_text_field( $value );
		} else {
			$value = trim( strip_tags( $value ) );
		}

		return mb_substr( $value, 0, $maxLength );
	}

	public static function sanitizeFloatish( ?string $value ): string {
		return trim( (string) preg_replace( '/[^0-9.\-]/', '', (string) $value ) );
	}

	/**
	 * @param mixed $value
	 */
	public static function intOr( $value, int $default ): int {
		if ( is_int( $value ) ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}
		return $default;
	}
}
