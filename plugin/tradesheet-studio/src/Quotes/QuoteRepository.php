<?php
/**
 * Quote persistence and lifecycle.
 *
 * A quote request references the exact packet version it originated from
 * (identity integrity). Revisions are separate rows chained through
 * parent_quote_id; superseded revisions can never be accepted. Line prices
 * come exclusively from the immutable version snapshot or an explicit
 * merchant revision — never from the browser.
 *
 * @package TradeSheetStudio\Quotes
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Quotes;

use TradeSheetStudio\Database\Schema;
use TradeSheetStudio\Packets\PacketRepository;
use TradeSheetStudio\Packets\PacketStateMachine;
use TradeSheetStudio\Pricing\PricingEngine;
use TradeSheetStudio\Settings\MerchantSettingsService;
use TradeSheetStudio\Support\EventLogger;
use TradeSheetStudio\Support\Money;
use TradeSheetStudio\Support\Notifier;
use TradeSheetStudio\Support\Utils;
use TradeSheetStudio\Support\ValidationException;

final class QuoteRepository {

        public const REQUESTED  = 'requested';
        public const IN_REVIEW  = 'in_review';
        public const REVISED    = 'revised';
        public const APPROVED   = 'approved';
        public const ACCEPTED   = 'accepted';
        public const ORDERED    = 'ordered';
        public const REJECTED   = 'rejected';
        public const SUPERSEDED = 'superseded';
        public const EXPIRED    = 'expired';
        public const CANCELLED  = 'cancelled';

        private const TRANSITIONS = array(
                self::REQUESTED  => array( self::IN_REVIEW, self::APPROVED, self::REVISED, self::REJECTED, self::SUPERSEDED, self::CANCELLED, self::EXPIRED ),
                self::IN_REVIEW  => array( self::APPROVED, self::REVISED, self::REJECTED, self::SUPERSEDED, self::CANCELLED, self::EXPIRED ),
                self::REVISED    => array( self::ACCEPTED, self::REJECTED, self::SUPERSEDED, self::CANCELLED, self::EXPIRED ),
                self::APPROVED   => array( self::ACCEPTED, self::REJECTED, self::SUPERSEDED, self::CANCELLED, self::EXPIRED ),
                self::ACCEPTED   => array( self::ORDERED, self::CANCELLED, self::EXPIRED ),
                self::ORDERED    => array(),
                self::REJECTED   => array(),
                self::SUPERSEDED => array(),
                self::EXPIRED    => array(),
                self::CANCELLED  => array(),
        );

        /** States that block a brand-new request for the same packet. */
        private const ACTIVE_STATES = array( self::REQUESTED, self::IN_REVIEW, self::REVISED, self::APPROVED, self::ACCEPTED );

        public function __construct(
                private PacketRepository $packets,
                private MerchantSettingsService $settings,
                private EventLogger $events,
                private QuoteIntegrityEngine $integrity
        ) {
        }

        /**
         * Buyer-side submission. Quantities are the ONLY buyer input honored.
         *
         * @param list<array{line_id:int,qty:int}> $quantities
         * @return array<string,mixed> The created quote row.
         */
        public function createRequest( int $packetId, array $quantities, string $notes = '', ?int $actorId = null ): array {
                global $wpdb;

                $packet = $this->packets->get( $packetId );
                if ( null === $packet ) {
                        throw new \RuntimeException( esc_html__( 'Packet not found.', 'tradesheet-studio' ) );
                }

                if ( ! PacketStateMachine::isLive( (string) $packet['status'] ) ) {
                        throw new ValidationException( __( 'This packet is no longer active.', 'tradesheet-studio' ) );
                }

                $version = $this->packets->currentVersion( $packetId );
                if ( null === $version ) {
                        throw new ValidationException( __( 'This packet has not been published.', 'tradesheet-studio' ) );
                }

                if ( null !== $version['expires_at'] && (string) $version['expires_at'] < Utils::nowDb() ) {
                        throw new ValidationException( __( 'This offer version has expired.', 'tradesheet-studio' ) );
                }

                // One active quote chain per packet.
                foreach ( $this->forPacket( $packetId ) as $existing ) {
                        if ( in_array( (string) $existing['status'], self::ACTIVE_STATES, true ) ) {
                                throw new ValidationException( __( 'A quote request for this packet is already open. Its current revision must be accepted, rejected or cancelled first.', 'tradesheet-studio' ) );
                        }
                }

                $linesById = array();
                foreach ( $this->packets->versionLines( (int) $version['id'] ) as $line ) {
                        $linesById[ (int) $line['id'] ] = $line;
                }

                $chosen = array();

                foreach ( $quantities as $entry ) {
                        $lineId = (int) ( $entry['line_id'] ?? 0 );
                        $qty    = (int) ( $entry['qty'] ?? 0 );

                        if ( ! isset( $linesById[ $lineId ] ) ) {
                                throw new ValidationException( __( 'A submitted line does not belong to this packet version.', 'tradesheet-studio' ) );
                        }

                        if ( $qty < 0 || $qty > Money::MAX_QTY ) {
                                throw new ValidationException( __( 'A submitted quantity is out of range.', 'tradesheet-studio' ) );
                        }

                        if ( 0 === $qty ) {
                                continue;
                        }

                        $line = $linesById[ $lineId ];

                        if ( $qty < (int) $line['qty_min'] ) {
                                throw new ValidationException(
                                        sprintf(
                                                /* translators: 1: product name, 2: minimum qty. */
                                                __( 'Minimum quantity for "%1$s" is %2$d.', 'tradesheet-studio' ),
                                                (string) $line['name'],
                                                (int) $line['qty_min']
                                        )
                                );
                        }

                        if ( 0 !== ( $qty % max( 1, (int) $line['qty_step'] ) ) ) {
                                throw new ValidationException(
                                        sprintf(
                                                /* translators: 1: product name, 2: step. */
                                                __( 'Quantity for "%1$s" must be a multiple of %2$d.', 'tradesheet-studio' ),
                                                (string) $line['name'],
                                                (int) $line['qty_step']
                                        )
                                );
                        }

                        $breaks = json_decode( (string) ( $line['qty_breaks'] ?? '' ), true );
                        $breaks = is_array( $breaks ) ? $breaks : array();

                        $chosen[] = array(
                                'packet_line' => $line,
                                'qty'         => $qty,
                                'unit_price'  => PricingEngine::effectiveUnitPrice( (string) $line['unit_price'], $breaks, $qty ),
                        );
                }

                if ( $chosen === array() ) {
                        throw new ValidationException( __( 'Select at least one product quantity before requesting a quote.', 'tradesheet-studio' ) );
                }

                // Integrity report computed BEFORE persistence (server-side prices only).
                $checkLines = array();
                foreach ( $chosen as $pick ) {
                        $checkLines[] = array(
                                'row_id'       => 'L' . (string) $pick['packet_line']['id'],
                                'product_id'   => (int) $pick['packet_line']['product_id'],
                                'variation_id' => (int) $pick['packet_line']['variation_id'],
                                'qty'          => $pick['qty'],
                                'unit_price'   => $pick['unit_price'],
                                'sku'          => (string) $pick['packet_line']['sku'],
                        );
                }

                $report = $this->integrity->check( $version, $checkLines );

                $now     = Utils::nowDb();
                $validity = max( 1, (int) $this->settings->get( 'quote_validity_days' ) );

                $ref = '';
                for ( $attempt = 0; $attempt < 6; $attempt++ ) {
                        $candidate = Utils::newQuoteRef();
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                        $exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Schema::tableName( 'tss_quotes' ) . ' WHERE ref = %s', $candidate ) );
                        if ( 0 === $exists ) {
                                $ref = $candidate;
                                break;
                        }
                }

                $wpdb->insert(
                        Schema::tableName( 'tss_quotes' ),
                        array(
                                'ref'                => $ref,
                                'packet_id'          => $packetId,
                                'version_id'         => (int) $version['id'],
                                'revision_no'        => 1,
                                'parent_quote_id'    => null,
                                'customer_id'        => (int) $packet['customer_id'],
                                'status'             => self::REQUESTED,
                                'requested_at'       => $now,
                                'expires_at'         => gmdate( 'Y-m-d H:i:s', strtotime( '+' . $validity . ' days', current_time( 'timestamp' ) ) ),
                                'notes_buyer'        => Utils::sanitizeText( $notes, 2000 ),
                                'discrepancy_report' => Utils::jsonEncode( $report ),
                                'created_at'         => $now,
                                'updated_at'         => $now,
                        ),
                        array( '%s', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
                );

                $quoteId = (int) $wpdb->insert_id;

                foreach ( $chosen as $pick ) {
                        $line = $pick['packet_line'];

                        $wpdb->insert(
                                Schema::tableName( 'tss_quote_lines' ),
                                array(
                                        'quote_id'     => $quoteId,
                                        'packet_line_id' => (int) $line['id'],
                                        'product_id'   => (int) $line['product_id'],
                                        'variation_id' => (int) $line['variation_id'],
                                        'sku'          => (string) $line['sku'],
                                        'name'         => (string) $line['name'],
                                        'variation_summary' => (string) ( $line['variation_summary'] ?? '' ),
                                        'qty'          => $pick['qty'],
                                        'unit_price'   => $pick['unit_price'],
                                        'price_source' => (string) $line['price_source'],
                                        'line_status'  => 'ok',
                                ),
                                array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
                        );
                }

                if ( PacketStateMachine::ISSUED === (string) $packet['status'] ) {
                        $this->packets->setStatus( $packetId, PacketStateMachine::SUBMITTED, $actorId );
                }

                $this->events->log(
                        'quote',
                        $quoteId,
                        'requested',
                        $actorId,
                        array(
                                'packet'   => $packet['ref'],
                                'version'  => (int) $version['version_no'],
                                'lines'    => count( $chosen ),
                                'blocking' => (int) $report['blocking_count'],
                                'warnings' => (int) $report['warning_count'],
                        )
                );

                Notifier::quoteRequested( (int) $packet['customer_id'], (string) $packet['ref'], $ref );

                $quote = $this->get( $quoteId );
                assert( is_array( $quote ) );

                return $quote;
        }

        /**
         * @return array<string,mixed>|null
         */
        public function get( int $id ): ?array {
                global $wpdb;

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_quotes' ) . ' WHERE id = %d', $id ), ARRAY_A );

                return is_array( $row ) ? $row : null;
        }

        /**
         * @return list<array<string,mixed>>
         */
        public function forPacket( int $packetId ): array {
                global $wpdb;

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_quotes' ) . ' WHERE packet_id = %d ORDER BY revision_no ASC', $packetId ), ARRAY_A );

                return is_array( $rows ) ? $rows : array();
        }

        /**
         * Latest non-superseded revision of a packet (the only acceptable one).
         *
         * @return array<string,mixed>|null
         */
        public function latestForPacket( int $packetId ): ?array {
                $latest = null;

                foreach ( $this->forPacket( $packetId ) as $quote ) {
                        if ( self::SUPERSEDED === (string) $quote['status'] ) {
                                continue;
                        }
                        if ( null === $latest || (int) $quote['revision_no'] > (int) $latest['revision_no'] ) {
                                $latest = $quote;
                        }
                }

                return $latest;
        }

        /**
         * @return list<array<string,mixed>>
         */
        public function listQuotes( ?string $status = null, int $page = 1, int $perPage = 20 ): array {
                global $wpdb;

                $perPage = max( 1, min( 100, $perPage ) );
                $offset  = ( max( 1, $page ) - 1 ) * $perPage;

                if ( null !== $status && '' !== $status ) {
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_quotes' ) . ' WHERE status = %s ORDER BY updated_at DESC LIMIT %d OFFSET %d', $status, $perPage, $offset ), ARRAY_A );
                } else {
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_quotes' ) . ' ORDER BY updated_at DESC LIMIT %d OFFSET %d', $perPage, $offset ), ARRAY_A );
                }

                return is_array( $rows ) ? $rows : array();
        }

        /**
         * @return list<array<string,mixed>>
         */
        public function lines( int $quoteId ): array {
                global $wpdb;

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_quote_lines' ) . ' WHERE quote_id = %d ORDER BY id ASC', $quoteId ), ARRAY_A );

                if ( ! is_array( $rows ) ) {
                        return array();
                }

                foreach ( $rows as $index => $row ) {
                        $rows[ $index ]['unit_price'] = Money::fromDb( $row['unit_price'] ?? '0' );
                }

                return $rows;
        }

        public function setStatus( int $id, string $to, ?int $actorId = null, array $context = array() ): string {
                global $wpdb;

                $quote = $this->get( $id );
                if ( null === $quote ) {
                        throw new \RuntimeException( esc_html__( 'Quote not found.', 'tradesheet-studio' ) );
                }

                $from = (string) $quote['status'];
                if ( ! isset( self::TRANSITIONS[ $from ] ) || ! in_array( $to, self::TRANSITIONS[ $from ], true ) ) {
                        throw new ValidationException(
                                sprintf(
                                        /* translators: 1: from state, 2: to state. */
                                        __( 'Illegal quote state transition "%1$s" → "%2$s".', 'tradesheet-studio' ),
                                        $from,
                                        $to
                                )
                        );
                }

                $now = Utils::nowDb();

                $data = array(
                        'status'     => $to,
                        'updated_at' => $now,
                );

                switch ( $to ) {
                        case self::IN_REVIEW:
                                $data['reviewed_at'] = $now;
                                break;
                        case self::APPROVED:
                                $data['approved_at'] = $now;
                                break;
                        case self::ACCEPTED:
                                $data['accepted_at'] = $now;
                                break;
                }

                $wpdb->update( Schema::tableName( 'tss_quotes' ), $data, array( 'id' => $id ), null, array( '%d' ) );

                $this->events->log( 'quote', $id, $to, $actorId, $context );

                /**
                 * Fires after a quote state transition is persisted.
                 *
                 * @param int    $id      Quote id.
                 * @param string $from    Previous state.
                 * @param string $to      New state.
                 * @param int|null $actorId Acting user id.
                 */
                do_action( 'tss_quote_state_changed', $id, $from, $to, $actorId );

                return $to;
        }

        /**
         * Merchant approval (honoring the quoted terms as-is).
         */
        public function approve( int $id, string $notes = '', ?int $actorId = null ): array {
                $quote = $this->get( $id );
                if ( null === $quote ) {
                        throw new \RuntimeException( esc_html__( 'Quote not found.', 'tradesheet-studio' ) );
                }

                $this->assertNotExpired( $quote );

                if ( ! in_array( (string) $quote['status'], array( self::REQUESTED, self::IN_REVIEW ), true ) ) {
                        throw new ValidationException( __( 'Only pending requests can be approved.', 'tradesheet-studio' ) );
                }

                global $wpdb;
                if ( '' !== $notes ) {
                        $wpdb->update( Schema::tableName( 'tss_quotes' ), array( 'notes_merchant' => Utils::sanitizeText( $notes, 2000 ) ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
                }

                $this->setStatus( $id, self::APPROVED, $actorId, array( 'decision' => 'honor' ) );

                $packetId = (int) $quote['packet_id'];
                $packet   = $this->packets->get( $packetId );
                if ( null !== $packet && in_array( (string) $packet['status'], array( PacketStateMachine::SUBMITTED, PacketStateMachine::IN_REVIEW ), true ) ) {
                        // Two-step transition keeps the state machine authoritative:
                        // submitted → in_review → approved.
                        if ( PacketStateMachine::SUBMITTED === (string) $packet['status'] ) {
                                $this->packets->setStatus( $packetId, PacketStateMachine::IN_REVIEW, $actorId );
                        }
                        $this->packets->setStatus( $packetId, PacketStateMachine::APPROVED, $actorId );
                }

                Notifier::quoteApproved( (int) $quote['customer_id'], (string) ( $packet['ref'] ?? '' ), (string) $quote['ref'] );

                return (array) $this->get( $id );
        }

        /**
         * Buyer acceptance. Only the latest, non-superseded, non-expired
         * revision in an acceptable state may be accepted.
         */
        public function accept( int $id, ?int $actorId = null ): array {
                $quote = $this->get( $id );
                if ( null === $quote ) {
                        throw new \RuntimeException( esc_html__( 'Quote not found.', 'tradesheet-studio' ) );
                }

                $this->assertNotExpired( $quote );

                if ( ! in_array( (string) $quote['status'], array( self::APPROVED, self::REVISED ), true ) ) {
                        throw new ValidationException( __( 'This quotation cannot be accepted in its current state.', 'tradesheet-studio' ) );
                }

                $latest = $this->latestForPacket( (int) $quote['packet_id'] );
                if ( null === $latest || (int) $latest['id'] !== $id ) {
                        throw new ValidationException( __( 'This revision has been superseded; only the current revision can be accepted.', 'tradesheet-studio' ) );
                }

                $packet = $this->packets->get( (int) $quote['packet_id'] );
                if ( null === $packet || ! PacketStateMachine::isLive( (string) $packet['status'] ) ) {
                        throw new ValidationException( __( 'The originating packet is expired or revoked.', 'tradesheet-studio' ) );
                }

                $this->setStatus( $id, self::ACCEPTED, $actorId, array( 'by' => 'buyer' ) );

                $packetId = (int) $quote['packet_id'];
                if ( in_array( (string) $packet['status'], array( PacketStateMachine::SUBMITTED, PacketStateMachine::IN_REVIEW, PacketStateMachine::APPROVED ), true ) ) {
                        // Two-step transitions keep the machine authoritative for the
                        // revision path (in_review → approved → accepted).
                        if ( PacketStateMachine::IN_REVIEW === (string) $packet['status'] ) {
                                $this->packets->setStatus( $packetId, PacketStateMachine::APPROVED, $actorId );
                        }
                        $this->packets->setStatus( $packetId, PacketStateMachine::ACCEPTED, $actorId );
                }

                return (array) $this->get( $id );
        }

        public function reject( int $id, string $notes = '', ?int $actorId = null ): array {
                $quote = $this->get( $id );
                if ( null === $quote ) {
                        throw new \RuntimeException( esc_html__( 'Quote not found.', 'tradesheet-studio' ) );
                }

                global $wpdb;
                if ( '' !== $notes ) {
                        $wpdb->update( Schema::tableName( 'tss_quotes' ), array( 'notes_merchant' => Utils::sanitizeText( $notes, 2000 ) ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
                }

                $this->setStatus( $id, self::REJECTED, $actorId, array( 'notes' => Utils::sanitizeText( $notes, 200 ) ) );

                $packetId = (int) $quote['packet_id'];
                $packet   = $this->packets->get( $packetId );
                if ( null !== $packet && in_array( (string) $packet['status'], array( PacketStateMachine::SUBMITTED, PacketStateMachine::IN_REVIEW, PacketStateMachine::APPROVED ), true ) ) {
                        $this->packets->setStatus( $packetId, PacketStateMachine::ISSUED, $actorId );
                }

                return (array) $this->get( $id );
        }

        /**
         * Called by the order bridge after successful conversion.
         */
        public function markOrdered( int $id, int $orderId, ?int $actorId = null ): void {
                global $wpdb;

                $wpdb->update(
                        Schema::tableName( 'tss_quotes' ),
                        array(
                                'status'             => self::ORDERED,
                                'converted_order_id' => $orderId,
                                'updated_at'         => Utils::nowDb(),
                        ),
                        array( 'id' => $id ),
                        array( '%s', '%d', '%s' ),
                        array( '%d' )
                );

                $this->events->log( 'quote', $id, 'ordered', $actorId, array( 'order_id' => $orderId ) );

                $quote  = $this->get( $id );
                $packet = null === $quote ? null : $this->packets->get( (int) $quote['packet_id'] );

                if ( null !== $packet && in_array( (string) $packet['status'], array( PacketStateMachine::ACCEPTED ), true ) ) {
                        $this->packets->setStatus( (int) $quote['packet_id'], PacketStateMachine::ORDERED, $actorId );
                }
        }

        public function markCancelled( int $id, ?int $actorId = null ): array {
                $this->setStatus( $id, self::CANCELLED, $actorId );

                return (array) $this->get( $id );
        }

        /**
         * Sweep: expire quotes whose validity window passed.
         *
         * @return list<int>
         */
        public function expireDue(): array {
                global $wpdb;

                $now = Utils::nowDb();

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from internal fixed lists.
                $rows = $wpdb->get_results(
                        $wpdb->prepare(
                                'SELECT id, status FROM ' . Schema::tableName( 'tss_quotes' ) .
                                ' WHERE status IN (%s, %s, %s, %s) AND expires_at IS NOT NULL AND expires_at < %s',
                                self::REQUESTED,
                                self::IN_REVIEW,
                                self::REVISED,
                                self::APPROVED,
                                $now
                        ),
                        ARRAY_A
                );

                $expired = array();
                if ( is_array( $rows ) ) {
                        foreach ( $rows as $row ) {
                                try {
                                        $this->setStatus( (int) $row['id'], self::EXPIRED );
                                        $expired[] = (int) $row['id'];
                                } catch ( \Throwable $e ) {
                                        continue;
                                }
                        }
                }

                return $expired;
        }

        private function assertNotExpired( array $quote ): void {
                $expiresAt = (string) ( $quote['expires_at'] ?? '' );

                if ( '' !== $expiresAt && $expiresAt < Utils::nowDb() && ! in_array( (string) $quote['status'], array( self::ORDERED, self::ACCEPTED ), true ) ) {
                        // Normalize the state before reporting.
                        try {
                                $this->setStatus( (int) $quote['id'], self::EXPIRED );
                        } catch ( \Throwable $e ) {
                                // Illegal transition (already terminal) — continue.
                        }

                        throw new ValidationException( __( 'This quotation has expired.', 'tradesheet-studio' ) );
                }
        }
}
