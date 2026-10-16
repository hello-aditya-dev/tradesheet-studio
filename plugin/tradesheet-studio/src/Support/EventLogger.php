<?php
/**
 * Audit event logger. Records lifecycle actions WITHOUT sensitive values:
 * token material, passwords, customer emails/addresses and similar fields
 * are redacted recursively before storage.
 *
 * @package TradeSheetStudio\Support
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Support;

use TradeSheetStudio\Database\Schema;

final class EventLogger {

	private const REDACT_SUBSTRINGS = array( 'token', 'password', 'secret', 'email', 'address', 'phone' );

	public function log( string $objectType, int $objectId, string $action, ?int $actorId, array $details = array() ): void {
		global $wpdb;

		$wpdb->insert(
			Schema::tableName( 'tss_events' ),
			array(
				'object_type' => $objectType,
				'object_id'   => $objectId,
				'action'      => $action,
				'actor_id'    => $actorId,
				'details'     => Utils::jsonEncode( $this->redact( $details ) ),
				'created_at'  => Utils::nowDb(),
			),
			array( '%s', '%d', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public function recent( int $limit = 50 ): array {
		global $wpdb;

		$limit = max( 1, min( 200, $limit ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list, limit cast to int.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_events' ) . ' ORDER BY id DESC LIMIT %d', $limit ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public function forObject( string $objectType, int $objectId, int $limit = 100 ): array {
		global $wpdb;

		$limit = max( 1, min( 500, $limit ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::tableName( 'tss_events' ) . ' WHERE object_type = %s AND object_id = %d ORDER BY id DESC LIMIT %d',
				$objectType,
				$objectId,
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Recursive redaction of sensitive keys before persistence.
	 */
	private function redact( array $details ): array {
		$safe = array();

		foreach ( $details as $key => $value ) {
			if ( is_string( $key ) ) {
				$lower = strtolower( $key );
				foreach ( self::REDACT_SUBSTRINGS as $needle ) {
					if ( str_contains( $lower, $needle ) ) {
						$value = '[redacted]';
						break;
					}
				}
			}

			if ( is_array( $value ) ) {
				$value = $this->redact( $value );
			}

			$safe[ (string) $key ] = $value;
		}

		return $safe;
	}
}
