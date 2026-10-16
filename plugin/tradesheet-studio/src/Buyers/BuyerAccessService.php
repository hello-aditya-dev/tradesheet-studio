<?php
/**
 * Buyer access tokens.
 *
 * Tokens are 64 hex characters generated with random_bytes(); ONLY the
 * SHA-256 hash is stored. Raw tokens are returned exactly once when issued
 * (regenerating a share link revokes the previous one — nothing secret is
 * retrievable later from storage).
 *
 * @package TradeSheetStudio\Buyers
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Buyers;

use TradeSheetStudio\Database\Schema;
use TradeSheetStudio\Packets\PacketRepository;
use TradeSheetStudio\Security\Capabilities;
use TradeSheetStudio\Settings\MerchantSettingsService;
use TradeSheetStudio\Support\Utils;

final class BuyerAccessService {

	public function __construct(
		private PacketRepository $packets,
		private MerchantSettingsService $settings
	) {
	}

	/**
	 * Issue a fresh share token for a packet (revokes previous ones).
	 * Returns the RAW token exactly once.
	 */
	public function issueForPacket( int $packetId ): string {
		global $wpdb;

		$now = Utils::nowDb();

		// Revoke previous tokens for this packet.
		$wpdb->update(
			Schema::tableName( 'tss_buyer_tokens' ),
			array( 'revoked_at' => $now ),
			array(
				'packet_id'   => $packetId,
				'revoked_at'  => null,
			),
			array( '%s' ),
			array( '%d', '%s' )
		);

		$token   = Utils::generateToken( 32 );
		$expires = gmdate( 'Y-m-d H:i:s', strtotime( '+' . max( 1, (int) $this->settings->get( 'buyer_token_days' ) ) . ' days', current_time( 'timestamp' ) ) );

		$wpdb->insert(
			Schema::tableName( 'tss_buyer_tokens' ),
			array(
				'packet_id'   => $packetId,
				'token_hash'  => Utils::hashToken( $token ),
				'created_at'  => $now,
				'expires_at'  => $expires,
				'use_count'   => 0,
			),
			array( '%d', '%s', '%s', '%s', '%d' )
		);

		return $token;
	}

	public function validateToken( int $packetId, string $token ): bool {
		global $wpdb;

		$token = trim( $token );
		if ( '' === $token || strlen( $token ) > 128 ) {
			return false;
		}

		$hash = Utils::hashToken( $token );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_buyer_tokens' ) . ' WHERE token_hash = %s AND packet_id = %d', $hash, $packetId ), ARRAY_A );

		if ( ! is_array( $row ) ) {
			return false;
		}
		if ( null !== $row['revoked_at'] ) {
			return false;
		}
		if ( null !== $row['expires_at'] && (string) $row['expires_at'] < Utils::nowDb() ) {
			return false;
		}

		$wpdb->update(
			Schema::tableName( 'tss_buyer_tokens' ),
			array(
				'use_count'    => (int) $row['use_count'] + 1,
				'last_used_at' => Utils::nowDb(),
			),
			array( 'id' => (int) $row['id'] ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Resolve the access mode for a packet request.
	 *
	 * @return string|null 'merchant' | 'owner' | 'token' | null (denied)
	 */
	public function accessFor( array $packet, ?string $token ): ?string {
		if ( Capabilities::canManage() ) {
			return 'merchant';
		}

		$customerId = (int) ( $packet['customer_id'] ?? 0 );
		if ( is_user_logged_in() && (int) get_current_user_id() === $customerId && $customerId > 0 ) {
			return 'owner';
		}

		if ( null !== $token && '' !== $token ) {
			if ( $this->validateToken( (int) $packet['id'], $token ) ) {
				return 'token';
			}
		}

		return null;
	}

	/**
	 * Fresh share URL for a packet (revokes previous tokens).
	 *
	 * @return array{url:string,token:string,expires_at:string}|null
	 */
	public function shareUrl( int $packetId ): ?array {
		$packet = $this->packets->get( $packetId );
		if ( null === $packet ) {
			return null;
		}

		$token = $this->issueForPacket( $packetId );

		$url = add_query_arg(
			't',
			$token,
			home_url( '/tradesheet/packet/' . rawurlencode( (string) $packet['ref'] ) . '/' )
		);

		$days = max( 1, (int) $this->settings->get( 'buyer_token_days' ) );

		return array(
			'url'        => $url,
			'token'      => $token,
			'expires_at' => gmdate( 'Y-m-d H:i:s', strtotime( '+' . $days . ' days', current_time( 'timestamp' ) ) ),
		);
	}

	public function revokeForPacket( int $packetId ): void {
		global $wpdb;

		$wpdb->update(
			Schema::tableName( 'tss_buyer_tokens' ),
			array( 'revoked_at' => Utils::nowDb() ),
			array(
				'packet_id'  => $packetId,
				'revoked_at' => null,
			),
			array( '%s' ),
			array( '%d', '%s' )
		);
	}

	/**
	 * Retention sweep: purge tokens that are revoked or expired for over a
	 * year.
	 */
	public function cleanup(): void {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-365 days', current_time( 'timestamp' ) ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Schema::tableName( 'tss_buyer_tokens' ) . ' WHERE (revoked_at IS NOT NULL AND revoked_at < %s) OR (expires_at IS NOT NULL AND expires_at < %s)', $cutoff, $cutoff ) );
	}
}
