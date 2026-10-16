<?php
/**
 * Packet persistence and queries. All SQL is prepared; WooCommerce tables
 * are never touched.
 *
 * Draft lines have version_id = NULL. Publishing copies them into a version
 * (immutable thereafter). Revising an issued packet seeds a fresh draft from
 * the current version; publishing again creates version N+1 and marks the
 * previous version superseded.
 *
 * @package TradeSheetStudio\Packets
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Packets;

use TradeSheetStudio\Database\Schema;
use TradeSheetStudio\Support\Money;
use TradeSheetStudio\Support\Utils;

final class PacketRepository {

        /**
         * @param array<string,mixed> $terms
         */
        public function create( int $customerId, string $title, ?int $pricebookId, string $currency, ?string $validUntil, array $terms, ?int $actorId ): int {
                global $wpdb;

                $now = Utils::nowDb();
                $ref = '';

                for ( $attempt = 0; $attempt < 6; $attempt++ ) {
                        $candidate = Utils::newPacketRef();
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                        $exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Schema::tableName( 'tss_packets' ) . ' WHERE ref = %s', $candidate ) );
                        if ( 0 === $exists ) {
                                $ref = $candidate;
                                break;
                        }
                }

                $wpdb->insert(
                        Schema::tableName( 'tss_packets' ),
                        array(
                                'ref'           => $ref,
                                'customer_id'   => $customerId,
                                'title'         => mb_substr( sanitize_text_field( $title ), 0, 191 ),
                                'status'        => PacketStateMachine::DRAFT,
                                'currency'      => mb_substr( strtoupper( $currency ), 0, 3 ),
                                'pricebook_id'  => $pricebookId,
                                'valid_until'   => $validUntil,
                                'terms'         => Utils::jsonEncode( $terms ),
                                'created_by'    => $actorId,
                                'created_at'    => $now,
                                'updated_at'    => $now,
                        ),
                        array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
                );

                return (int) $wpdb->insert_id;
        }

        /**
         * @return array<string,mixed>|null
         */
        public function get( int $id ): ?array {
                global $wpdb;

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_packets' ) . ' WHERE id = %d', $id ), ARRAY_A );

                return is_array( $row ) ? $row : null;
        }

        /**
         * @return array<string,mixed>|null
         */
        public function getByRef( string $ref ): ?array {
                global $wpdb;

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_packets' ) . ' WHERE ref = %s', strtoupper( $ref ) ), ARRAY_A );

                return is_array( $row ) ? $row : null;
        }

        public function refFor( int $id ): string {
                $packet = $this->get( $id );

                return null === $packet ? '' : (string) $packet['ref'];
        }

        /**
         * @param array<string,mixed> $fields
         */
        public function update( int $id, array $fields ): bool {
                global $wpdb;

                $packet = $this->get( $id );
                if ( null === $packet ) {
                        return false;
                }

                // Only drafts may have core commercial fields edited.
                if ( PacketStateMachine::DRAFT !== (string) $packet['status'] ) {
                        unset( $fields['customer_id'], $fields['currency'], $fields['terms'] );
                }

                $allowed = array( 'title', 'customer_id', 'currency', 'pricebook_id', 'valid_until', 'terms' );
                $data    = array();
                $format  = array();

                foreach ( $fields as $key => $value ) {
                        if ( ! in_array( $key, $allowed, true ) ) {
                                continue;
                        }
                        switch ( $key ) {
                                case 'title':
                                        $data[ $key ] = mb_substr( sanitize_text_field( (string) $value ), 0, 191 );
                                        $format[]     = '%s';
                                        break;
                                case 'customer_id':
                                case 'pricebook_id':
                                        $data[ $key ] = (int) $value;
                                        $format[]     = '%d';
                                        break;
                                case 'currency':
                                        $data[ $key ] = mb_substr( strtoupper( sanitize_text_field( (string) $value ) ), 0, 3 );
                                        $format[]     = '%s';
                                        break;
                                case 'valid_until':
                                        $data[ $key ] = ( null === $value || '' === $value ) ? null : (string) $value;
                                        $format[]     = '%s';
                                        break;
                                case 'terms':
                                        $data[ $key ] = Utils::jsonEncode( is_array( $value ) ? $value : array() );
                                        $format[]     = '%s';
                                        break;
                        }
                }

                if ( array() === $data ) {
                        return false;
                }

                $data['updated_at'] = Utils::nowDb();
                $format[]           = '%s';

                return false !== $wpdb->update( Schema::tableName( 'tss_packets' ), $data, array( 'id' => $id ), $format, array( '%d' ) );
        }

        /**
         * State transition with machine enforcement. Returns the new state.
         */
        public function setStatus( int $id, string $to, ?int $actorId = null ): string {
                global $wpdb;

                $packet = $this->get( $id );
                if ( null === $packet ) {
                        throw new \RuntimeException( esc_html__( 'Packet not found.', 'tradesheet-studio' ) );
                }

                $from = (string) $packet['status'];
                PacketStateMachine::assert( $from, $to );

                $wpdb->update(
                        Schema::tableName( 'tss_packets' ),
                        array(
                                'status'     => $to,
                                'updated_at' => Utils::nowDb(),
                        ),
                        array( 'id' => $id ),
                        array( '%s', '%s' ),
                        array( '%d' )
                );

                /**
                 * Fires after a packet state transition is persisted.
                 *
                 * @param int    $id   Packet id.
                 * @param string $from Previous state.
                 * @param string $to   New state.
                 * @param int|null $actorId Acting user id.
                 */
                do_action( 'tss_packet_state_changed', $id, $from, $to, $actorId );

                return $to;
        }

        public function deleteDraft( int $id ): bool {
                global $wpdb;

                $packet = $this->get( $id );
                if ( null === $packet || PacketStateMachine::DRAFT !== (string) $packet['status'] ) {
                        return false;
                }

                $wpdb->delete( Schema::tableName( 'tss_packet_lines' ), array( 'packet_id' => $id, 'version_id' => null ), array( '%d', '%s' ) );
                $wpdb->delete( Schema::tableName( 'tss_packets' ), array( 'id' => $id ), array( '%d' ) );

                return true;
        }

        /**
         * @return list<array<string,mixed>>
         */
        public function listPackets( ?string $status = null, ?int $customerId = null, string $search = '', int $page = 1, int $perPage = 20 ): array {
                global $wpdb;

                $perPage = max( 1, min( 100, $perPage ) );
                $page    = max( 1, $page );
                $offset  = ( $page - 1 ) * $perPage;

                $where  = ' WHERE 1=1';
                $params = array();

                if ( null !== $status && '' !== $status ) {
                        $where   .= ' AND status = %s';
                        $params[] = $status;
                }
                if ( null !== $customerId && $customerId > 0 ) {
                        $where   .= ' AND customer_id = %d';
                        $params[] = $customerId;
                }
                if ( '' !== $search ) {
                        $where   .= ' AND (ref LIKE %s OR title LIKE %s)';
                        $like     = '%' . $wpdb->esc_like( $search ) . '%';
                        $params[] = $like;
                        $params[] = $like;
                }

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from internal fixed lists.
                $sql = 'SELECT p.*, v.version_no FROM ' . Schema::tableName( 'tss_packets' ) . ' p LEFT JOIN ' . Schema::tableName( 'tss_packet_versions' ) . ' v ON v.id = p.current_version_id' . $where . ' ORDER BY p.updated_at DESC LIMIT %d OFFSET %d';
                $params[] = $perPage;
                $params[] = $offset;

                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared immediately above.
                $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

                return is_array( $rows ) ? $rows : array();
        }

        /**
         * Dashboard counters derived from real records only.
         *
         * @return array<string,int>
         */
        public function dashboardMetrics(): array {
                global $wpdb;

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $grouped = $wpdb->get_results( 'SELECT status, COUNT(*) AS n FROM ' . Schema::tableName( 'tss_packets' ) . ' GROUP BY status', ARRAY_A );

                $metrics = array(
                        'draft'         => 0,
                        'issued'        => 0,
                        'submitted'     => 0,
                        'in_review'     => 0,
                        'approved'      => 0,
                        'accepted'      => 0,
                        'ordered'       => 0,
                        'expired'       => 0,
                        'revoked'       => 0,
                        'cancelled'     => 0,
                        'archived'      => 0,
                        'awaiting'      => 0,
                        'open_quotes'   => 0,
                        'orders'        => 0,
                        'failed_jobs'   => 0,
                        'expiring'      => 0,
                );

                if ( is_array( $grouped ) ) {
                        foreach ( $grouped as $row ) {
                                $metrics[ (string) $row['status'] ] = (int) $row['n'];
                        }
                }

                $metrics['awaiting'] = $metrics['submitted'] + $metrics['in_review'];

                $now = Utils::nowDb();
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from internal fixed lists.
                $metrics['open_quotes'] = (int) $wpdb->get_var(
                        $wpdb->prepare(
                                'SELECT COUNT(*) FROM ' . Schema::tableName( 'tss_quotes' ) . ' WHERE status IN (%s, %s, %s)',
                                'requested',
                                'in_review',
                                'approved'
                        )
                );

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $metrics['orders'] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::tableName( 'tss_order_links' ) . " WHERE status = 'complete'" );

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $metrics['failed_jobs'] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::tableName( 'tss_jobs' ) . " WHERE status = 'failed'" );

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from internal fixed lists.
                $metrics['expiring'] = (int) $wpdb->get_var(
                        $wpdb->prepare(
                                'SELECT COUNT(*) FROM ' . Schema::tableName( 'tss_packets' ) .
                                " WHERE status IN (%s, %s, %s, %s, %s) AND valid_until IS NOT NULL AND valid_until >= %s AND valid_until <= %s",
                                PacketStateMachine::ISSUED,
                                PacketStateMachine::SUBMITTED,
                                PacketStateMachine::IN_REVIEW,
                                PacketStateMachine::APPROVED,
                                PacketStateMachine::ACCEPTED,
                                $now,
                                gmdate( 'Y-m-d H:i:s', strtotime( $now . ' +7 days' ) )
                        )
                );

                return $metrics;
        }

        /**
         * Sweep: expire live packets whose validity window has passed.
         *
         * @return list<int> expired packet ids.
         */
        public function expireDue(): array {
                global $wpdb;

                $now = Utils::nowDb();

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from internal fixed lists.
                $rows = $wpdb->get_results(
                        $wpdb->prepare(
                                'SELECT id, status FROM ' . Schema::tableName( 'tss_packets' ) .
                                " WHERE status IN (%s, %s, %s, %s, %s) AND valid_until IS NOT NULL AND valid_until < %s",
                                PacketStateMachine::ISSUED,
                                PacketStateMachine::SUBMITTED,
                                PacketStateMachine::IN_REVIEW,
                                PacketStateMachine::APPROVED,
                                PacketStateMachine::ACCEPTED,
                                $now
                        ),
                        ARRAY_A
                );

                $expired = array();
                if ( is_array( $rows ) ) {
                        foreach ( $rows as $row ) {
                                try {
                                        $this->setStatus( (int) $row['id'], PacketStateMachine::EXPIRED );
                                        $expired[] = (int) $row['id'];
                                } catch ( \Throwable $e ) {
                                        // Illegal transitions are skipped; state machine stays authoritative.
                                        continue;
                                }
                        }
                }

                return $expired;
        }

        /**
         * @return list<array<string,mixed>>
         */
        public function draftLines( int $packetId ): array {
                global $wpdb;

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_packet_lines' ) . ' WHERE packet_id = %d AND version_id IS NULL ORDER BY sort_order ASC, id ASC', $packetId ), ARRAY_A );

                return is_array( $rows ) ? array_map( array( $this, 'normalizeLine' ), $rows ) : array();
        }

        public function addDraftLine( int $packetId, int $productId, int $variationId, string $unitPrice, string $priceSource, int $qtyMin, int $qtyStep, array $qtyBreaks, ?string $description = null ): int {
                global $wpdb;

                $existing = $this->draftLines( $packetId );
                $sort     = count( $existing ) + 1;

                foreach ( $existing as $line ) {
                        if ( (int) $line['product_id'] === $productId && (int) $line['variation_id'] === $variationId ) {
                                return (int) $line['id'];
                        }
                }

                $wpdb->insert(
                        Schema::tableName( 'tss_packet_lines' ),
                        array(
                                'packet_id'   => $packetId,
                                'version_id'  => null,
                                'product_id'  => $productId,
                                'variation_id' => $variationId,
                                'unit_price'  => $unitPrice,
                                'price_source' => $priceSource,
                                'qty_min'     => max( 1, $qtyMin ),
                                'qty_step'    => max( 1, $qtyStep ),
                                'qty_breaks'  => Utils::jsonEncode( $qtyBreaks ),
                                'sort_order'  => $sort,
                                'created_at'  => Utils::nowDb(),
                        ),
                        array( '%d', '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%d', '%s' )
                );

                return (int) $wpdb->insert_id;
        }

        /**
         * @param array<string,mixed> $fields
         */
        public function updateDraftLine( int $lineId, array $fields ): bool {
                global $wpdb;

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $line = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_packet_lines' ) . ' WHERE id = %d AND version_id IS NULL', $lineId ), ARRAY_A );

                if ( ! is_array( $line ) ) {
                        return false;
                }

                $data   = array();
                $format = array();

                if ( isset( $fields['qty_min'] ) ) {
                        $data['qty_min'] = max( 1, min( Money::MAX_QTY, (int) $fields['qty_min'] ) );
                        $format[]        = '%d';
                }
                if ( isset( $fields['qty_step'] ) ) {
                        $data['qty_step'] = max( 1, min( 10000, (int) $fields['qty_step'] ) );
                        $format[]         = '%d';
                }
                if ( isset( $fields['unit_price'] ) ) {
                        $data['unit_price'] = Money::round( Utils::sanitizeFloatish( (string) $fields['unit_price'] ), 4 );
                        $format[]           = '%s';
                        $data['price_source'] = 'packet_override';
                        $format[]            = '%s';
                }
                if ( isset( $fields['sort_order'] ) ) {
                        $data['sort_order'] = max( 0, (int) $fields['sort_order'] );
                        $format[]           = '%d';
                }

                if ( array() === $data ) {
                        return false;
                }

                return false !== $wpdb->update( Schema::tableName( 'tss_packet_lines' ), $data, array( 'id' => $lineId ), $format, array( '%d' ) );
        }

        public function removeDraftLine( int $lineId ): bool {
                global $wpdb;

                return false !== $wpdb->delete( Schema::tableName( 'tss_packet_lines' ), array( 'id' => $lineId, 'version_id' => null ), array( '%d', '%s' ) );
        }

        /**
         * @param list<int> $orderedIds
         */
        public function reorderDraftLines( int $packetId, array $orderedIds ): void {
                global $wpdb;

                $sort = 1;
                foreach ( $orderedIds as $lineId ) {
                        $wpdb->update(
                                Schema::tableName( 'tss_packet_lines' ),
                                array( 'sort_order' => $sort++ ),
                                array(
                                        'id'         => (int) $lineId,
                                        'packet_id'  => $packetId,
                                        'version_id' => null,
                                ),
                                array( '%d' ),
                                array( '%d', '%d', '%s' )
                        );
                }
        }

        /**
         * @return array<string,mixed>|null
         */
        public function version( int $versionId ): ?array {
                global $wpdb;

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_packet_versions' ) . ' WHERE id = %d', $versionId ), ARRAY_A );

                return is_array( $row ) ? $row : null;
        }

        /**
         * @return list<array<string,mixed>>
         */
        public function versions( int $packetId ): array {
                global $wpdb;

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_packet_versions' ) . ' WHERE packet_id = %d ORDER BY version_no DESC', $packetId ), ARRAY_A );

                return is_array( $rows ) ? $rows : array();
        }

        /**
         * @return array<string,mixed>|null
         */
        public function currentVersion( int $packetId ): ?array {
                $packet = $this->get( $packetId );
                if ( null === $packet || empty( $packet['current_version_id'] ) ) {
                        return null;
                }

                return $this->version( (int) $packet['current_version_id'] );
        }

        /**
         * @return list<array<string,mixed>>
         */
        public function versionLines( int $versionId ): array {
                global $wpdb;

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_packet_lines' ) . ' WHERE version_id = %d ORDER BY sort_order ASC, id ASC', $versionId ), ARRAY_A );

                return is_array( $rows ) ? array_map( array( $this, 'normalizeLine' ), $rows ) : array();
        }

        /**
         * Canonicalize money fields on database reads (driver-independent).
         *
         * @param array<string,mixed> $line
         * @return array<string,mixed>
         */
        private function normalizeLine( array $line ): array {
                $line['unit_price'] = Money::fromDb( $line['unit_price'] ?? '0' );

                if ( ! empty( $line['qty_breaks'] ) ) {
                        $breaks = json_decode( (string) $line['qty_breaks'], true );
                        if ( is_array( $breaks ) ) {
                                foreach ( $breaks as $index => $break ) {
                                        if ( is_array( $break ) && isset( $break['qty'], $break['price'] ) ) {
                                                $breaks[ $index ] = array( 'qty' => (int) $break['qty'], 'price' => Money::fromDb( $break['price'] ) );
                                        }
                                }
                                $line['qty_breaks'] = Utils::jsonEncode( $breaks );
                        }
                }

                return $line;
        }
}
