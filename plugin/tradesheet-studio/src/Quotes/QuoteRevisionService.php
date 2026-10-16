<?php
/**
 * Quote revision service — merchant-side modification flow.
 *
 * A material seller modification NEVER becomes an accepted buyer agreement
 * implicitly: revising creates a new immutable revision with status
 * "revised" that the BUYER must explicitly accept (or reject). The
 * superseded revision can no longer be accepted.
 *
 * @package TradeSheetStudio\Quotes
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Quotes;

use TradeSheetStudio\Database\Schema;
use TradeSheetStudio\Packets\PacketRepository;
use TradeSheetStudio\Packets\PacketStateMachine;
use TradeSheetStudio\Support\EventLogger;
use TradeSheetStudio\Support\Money;
use TradeSheetStudio\Support\Notifier;
use TradeSheetStudio\Support\Utils;
use TradeSheetStudio\Support\ValidationException;

final class QuoteRevisionService {

        public function __construct(
                private QuoteRepository $quotes,
                private PacketRepository $packets,
                private QuoteIntegrityEngine $integrity,
                private EventLogger $events
        ) {
        }

        /**
         * Issue a merchant revision of a pending quote request.
         *
         * @param int $quoteId  Quote being revised (requested|in_review).
         * @param list<array{quote_line_id?:int,line_id?:int,unit_price?:string,qty?:int,remove?:bool,note?:string}> $changes
         * @param string $notes Merchant note shown to the buyer.
         * @return array<string,mixed> The new revision row.
         */
        public function revise( int $quoteId, array $changes, string $notes, ?int $actorId ): array {
                global $wpdb;

                $quote = $this->quotes->get( $quoteId );
                if ( null === $quote ) {
                        throw new \RuntimeException( esc_html__( 'Quote not found.', 'tradesheet-studio' ) );
                }

                if ( ! in_array( (string) $quote['status'], array( QuoteRepository::REQUESTED, QuoteRepository::IN_REVIEW ), true ) ) {
                        throw new ValidationException( __( 'Only pending quote requests can be revised.', 'tradesheet-studio' ) );
                }

                $expiresAt = (string) ( $quote['expires_at'] ?? '' );
                if ( '' !== $expiresAt && $expiresAt < Utils::nowDb() ) {
                        throw new ValidationException( __( 'This quotation has expired.', 'tradesheet-studio' ) );
                }

                $packet = $this->packets->get( (int) $quote['packet_id'] );
                if ( null === $packet || ! PacketStateMachine::isLive( (string) $packet['status'] ) ) {
                        throw new ValidationException( __( 'The originating packet is no longer active.', 'tradesheet-studio' ) );
                }

                // Packet lines (authoritative quantity rules for revision validation).
                $packetLines = array();
                foreach ( $this->packets->versionLines( (int) $quote['version_id'] ) as $line ) {
                        $packetLines[ (int) $line['id'] ] = $line;
                }

                $oldLines = array();
                foreach ( $this->quotes->lines( $quoteId ) as $line ) {
                        $oldLines[ (int) $line['id'] ] = $line;
                }

                $changesByLine = array();
                foreach ( $changes as $change ) {
                        $lineKey = (int) ( $change['quote_line_id'] ?? $change['line_id'] ?? 0 );
                        if ( $lineKey < 1 || ! isset( $oldLines[ $lineKey ] ) ) {
                                throw new ValidationException( __( 'A revision change references a line that does not belong to this quote.', 'tradesheet-studio' ) );
                        }
                        $changesByLine[ $lineKey ] = $change;
                }

                $newLines  = array();
                $summary   = array( 'changed_prices' => 0, 'changed_qty' => 0, 'removed' => 0 );

                foreach ( $oldLines as $lineId => $line ) {
                        $change = $changesByLine[ $lineId ] ?? array();

                        if ( ! empty( $change['remove'] ) ) {
                                ++$summary['removed'];
                                continue;
                        }

                        $unitPrice = (string) $line['unit_price'];
                        $qty       = (int) $line['qty'];

                        if ( isset( $change['unit_price'] ) && null !== $change['unit_price'] && '' !== (string) $change['unit_price'] ) {
                                $normalized = Money::round( Utils::sanitizeFloatish( (string) $change['unit_price'] ), 4 );
                                if ( ! Money::isValid( $normalized ) || Money::cmp( $normalized, '0.0000' ) < 0 ) {
                                        throw new ValidationException( __( 'A revised price is invalid.', 'tradesheet-studio' ) );
                                }
                                if ( Money::cmp( $normalized, $unitPrice ) !== 0 ) {
                                        ++$summary['changed_prices'];
                                }
                                $unitPrice = $normalized;
                        }

                        if ( isset( $change['qty'] ) && null !== $change['qty'] ) {
                                $newQty = (int) $change['qty'];
                                $packetLine = $packetLines[ (int) $line['packet_line_id'] ] ?? null;

                                if ( $newQty < 0 || $newQty > Money::MAX_QTY ) {
                                        throw new ValidationException( __( 'A revised quantity is out of range.', 'tradesheet-studio' ) );
                                }
                                if ( $newQty > 0 && null !== $packetLine && $newQty < (int) $packetLine['qty_min'] ) {
                                        throw new ValidationException(
                                                sprintf(
                                                        /* translators: 1: product name, 2: minimum qty. */
                                                        __( 'Revised quantity for "%1$s" is below the packet minimum of %2$d.', 'tradesheet-studio' ),
                                                        (string) $line['name'],
                                                        (int) $packetLine['qty_min']
                                                )
                                        );
                                }
                                if ( $newQty > 0 && null !== $packetLine && 0 !== ( $newQty % max( 1, (int) $packetLine['qty_step'] ) ) ) {
                                        throw new ValidationException( __( 'A revised quantity violates the packet quantity step.', 'tradesheet-studio' ) );
                                }
                                if ( $newQty !== $qty ) {
                                        ++$summary['changed_qty'];
                                }
                                $qty = $newQty;
                        }

                        if ( 0 === $qty ) {
                                ++$summary['removed'];
                                continue;
                        }

                        $priceChanged = isset( $change['unit_price'] ) && Money::cmp( $unitPrice, (string) $line['unit_price'] ) !== 0;

                        $newLines[] = array(
                                'line'       => $line,
                                'qty'        => $qty,
                                'unit_price' => $unitPrice,
                                'note'       => isset( $change['note'] ) ? Utils::sanitizeText( (string) $change['note'], 250 ) : '',
                                'price_source' => $priceChanged ? 'merchant_revision' : (string) $line['price_source'],
                        );
                }

                if ( $newLines === array() ) {
                        throw new ValidationException( __( 'A revision must retain at least one product line.', 'tradesheet-studio' ) );
                }

                // Fresh integrity report for the revised terms.
                $checkLines = array();
                foreach ( $newLines as $entry ) {
                        $checkLines[] = array(
                                'row_id'       => 'L' . (string) $entry['line']['packet_line_id'],
                                'product_id'   => (int) $entry['line']['product_id'],
                                'variation_id' => (int) $entry['line']['variation_id'],
                                'qty'          => $entry['qty'],
                                'unit_price'   => $entry['unit_price'],
                                'sku'          => (string) $entry['line']['sku'],
                        );
                }

                $version = $this->packets->version( (int) $quote['version_id'] );
                $report  = null === $version ? array() : $this->integrity->check( $version, $checkLines );

                // Create the new immutable revision row.
                $now        = Utils::nowDb();
                $settings   = new \TradeSheetStudio\Settings\MerchantSettingsService();
                $validity   = max( 1, (int) $settings->get( 'quote_validity_days' ) );
                $revisionNo = (int) $quote['revision_no'] + 1;

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
                                'packet_id'          => (int) $quote['packet_id'],
                                'version_id'         => (int) $quote['version_id'],
                                'revision_no'        => $revisionNo,
                                'parent_quote_id'    => $quoteId,
                                'customer_id'        => (int) $quote['customer_id'],
                                'status'             => QuoteRepository::REVISED,
                                'requested_at'       => (string) $quote['requested_at'],
                                'reviewed_at'        => $now,
                                'expires_at'         => gmdate( 'Y-m-d H:i:s', strtotime( '+' . $validity . ' days', current_time( 'timestamp' ) ) ),
                                'notes_buyer'        => (string) $quote['notes_buyer'],
                                'notes_merchant'     => Utils::sanitizeText( $notes, 2000 ),
                                'discrepancy_report' => Utils::jsonEncode( $report ),
                                'created_at'         => $now,
                                'updated_at'         => $now,
                        ),
                        array( '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
                );

                $newQuoteId = (int) $wpdb->insert_id;

                foreach ( $newLines as $entry ) {
                        $line = $entry['line'];

                        $wpdb->insert(
                                Schema::tableName( 'tss_quote_lines' ),
                                array(
                                        'quote_id'     => $newQuoteId,
                                        'packet_line_id' => (int) $line['packet_line_id'],
                                        'product_id'   => (int) $line['product_id'],
                                        'variation_id' => (int) $line['variation_id'],
                                        'sku'          => (string) $line['sku'],
                                        'name'         => (string) $line['name'],
                                        'variation_summary' => (string) ( $line['variation_summary'] ?? '' ),
                                        'qty'          => $entry['qty'],
                                        'unit_price'   => $entry['unit_price'],
                                        'price_source' => $entry['price_source'],
                                        'line_status'  => 'ok',
                                        'merchant_note' => '' !== $entry['note'] ? $entry['note'] : null,
                                ),
                                array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
                        );
                }

                // The original request becomes superseded (can no longer be accepted).
                $this->quotes->setStatus( $quoteId, QuoteRepository::SUPERSEDED, $actorId, array( 'replaced_by' => $ref ) );

                $packetId = (int) $quote['packet_id'];
                if ( PacketStateMachine::SUBMITTED === (string) $packet['status'] ) {
                        $this->packets->setStatus( $packetId, PacketStateMachine::IN_REVIEW, $actorId );
                }

                $this->events->log(
                        'quote',
                        $newQuoteId,
                        'revision_issued',
                        $actorId,
                        array(
                                'parent'  => (string) $quote['ref'],
                                'packet'  => (string) $packet['ref'],
                                'summary' => $summary,
                        )
                );

                Notifier::quoteRevised( (int) $quote['customer_id'], (string) $packet['ref'], $ref );

                return (array) $this->quotes->get( $newQuoteId );
        }
}
