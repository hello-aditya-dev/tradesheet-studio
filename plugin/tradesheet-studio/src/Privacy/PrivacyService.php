<?php
/**
 * WordPress personal-data export/erasure integration.
 *
 * The plugin stores commercial records (packets, quotes, order links)
 * linked to WooCommerce customer ids, plus hashed buyer tokens. Export
 * surfaces metadata; erasure removes tokens and buyer notes while retaining
 * the minimal financial records merchants are typically required to keep —
 * the erasure response says so explicitly and honestly.
 *
 * @package TradeSheetStudio\Privacy
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Privacy;

use TradeSheetStudio\Database\Schema;

final class PrivacyService {

	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'registerExporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'registerEraser' ) );
	}

	/**
	 * @param array<int,array<string,mixed>> $exporters
	 */
	public function registerExporter( array $exporters ): array {
		$exporters[] = array(
			'key'      => 'tradesheet-studio',
			'name'     => __( 'TradeSheet Studio (DealPackets & quotations)', 'tradesheet-studio' ),
			'callback' => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * @param array<int,array<string,mixed>> $erasers
	 */
	public function registerEraser( array $erasers ): array {
		$erasers[] = array(
			'key'      => 'tradesheet-studio',
			'name'     => __( 'TradeSheet Studio (tokens & buyer notes)', 'tradesheet-studio' ),
			'callback' => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * @return array{data:list<array<string,mixed>>,done:bool}
	 */
	public function export( string $email ): array {
		$user = get_user_by( 'email', $email );

		if ( false === $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		global $wpdb;

		$customerId = (int) $user->ID;
		$data       = array();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
		$packets = $wpdb->get_results( $wpdb->prepare( 'SELECT ref, title, status, created_at, updated_at FROM ' . Schema::tableName( 'tss_packets' ) . ' WHERE customer_id = %d ORDER BY id DESC', $customerId ), ARRAY_A );

		$packetItems = array();
		if ( is_array( $packets ) ) {
			foreach ( $packets as $packet ) {
				$packetItems[] = array(
					'name'  => (string) $packet['ref'],
					'value' => sprintf( '%s — %s (%s)', $packet['ref'], $packet['title'], $packet['status'] ),
				);
			}
		}

		$data[] = array(
			'group_id'    => 'tradesheet-studio-packets',
			'group_label' => __( 'TradeSheet Studio: DealPackets', 'tradesheet-studio' ),
			'item_id'     => 'tss-packets-' . $customerId,
			'data'        => $packetItems,
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
		$quotes = $wpdb->get_results( $wpdb->prepare( 'SELECT ref, status, requested_at, notes_buyer FROM ' . Schema::tableName( 'tss_quotes' ) . ' WHERE customer_id = %d ORDER BY id DESC', $customerId ), ARRAY_A );

		$quoteItems = array();
		if ( is_array( $quotes ) ) {
			foreach ( $quotes as $quote ) {
				$quoteItems[] = array(
					'name'  => (string) $quote['ref'],
					'value' => sprintf( '%s — %s (requested %s)', $quote['ref'], $quote['status'], $quote['requested_at'] ),
				);
				if ( '' !== (string) $quote['notes_buyer'] ) {
					$quoteItems[] = array(
						'name'  => (string) $quote['ref'] . ' — note',
						'value' => (string) $quote['notes_buyer'],
					);
				}
			}
		}

		$data[] = array(
			'group_id'    => 'tradesheet-studio-quotes',
			'group_label' => __( 'TradeSheet Studio: Quotations', 'tradesheet-studio' ),
			'item_id'     => 'tss-quotes-' . $customerId,
			'data'        => $quoteItems,
		);

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * @return array{items_removed:int,items_retained:int,messages:list<string>,done:bool}
	 */
	public function erase( string $email ): array {
		$user = get_user_by( 'email', $email );

		if ( false === $user ) {
			return array(
				'items_removed'  => 0,
				'items_retained' => 0,
				'messages'       => array(),
				'done'           => true,
			);
		}

		global $wpdb;

		$customerId = (int) $user->ID;

		// Revoke + delete all buyer tokens for this customer's packets.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from internal fixed lists.
		$tokenRows = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . Schema::tableName( 'tss_buyer_tokens' ) . ' WHERE packet_id IN (SELECT id FROM ' . Schema::tableName( 'tss_packets' ) . ' WHERE customer_id = %d)', $customerId ) );

		$removed = 0;
		if ( is_array( $tokenRows ) ) {
			foreach ( $tokenRows as $tokenId ) {
				$wpdb->delete( Schema::tableName( 'tss_buyer_tokens' ), array( 'id' => (int) $tokenId ), array( '%d' ) );
				++$removed;
			}
		}

		// Remove buyer-authored notes.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Schema::tableName( 'tss_quotes' ) . " SET notes_buyer = '' WHERE customer_id = %d", $customerId ) );

		// Commercial records (packet versions, quote lines, order links) are
		// retained as legitimate business/financial records; nothing personal
		// beyond the id linkage remains in them.
		return array(
			'items_removed'  => $removed,
			'items_retained' => 1,
			'messages'       => array(
				__( 'Buyer access tokens were deleted and buyer notes removed. DealPacket and quotation commercial records are retained as business records; they contain product and pricing data linked to the customer id only.', 'tradesheet-studio' ),
			),
			'done'           => true,
		);
	}
}
