<?php
/**
 * Publication and revision orchestration — the immutability boundary.
 *
 * publish() runs the preflight validator server-side, refreshes product
 * presentation data, freezes a canonical JSON snapshot with a SHA-256
 * content checksum, promotes draft lines into an immutable version, marks
 * prior versions superseded, and issues a buyer token.
 *
 * revise() copies the current version's lines back into an editable draft;
 * publishing again creates version N+1. Issued snapshots are NEVER edited
 * in place.
 *
 * @package TradeSheetStudio\Packets
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Packets;

use TradeSheetStudio\Buyers\BuyerAccessService;
use TradeSheetStudio\Database\Schema;
use TradeSheetStudio\Pricing\PriceBookRepository;
use TradeSheetStudio\Products\ProductAdapter;
use TradeSheetStudio\Settings\MerchantSettingsService;
use TradeSheetStudio\Support\EventLogger;
use TradeSheetStudio\Support\Utils;
use TradeSheetStudio\Support\ValidationException;

final class PacketSnapshotService {

        public function __construct(
                private PacketRepository $packets,
                private ProductAdapter $products,
                private PriceBookRepository $books,
                private MerchantSettingsService $settings,
                private BuyerAccessService $buyerAccess,
                private EventLogger $events
        ) {
        }

        /**
         * Preflight report for the composer's review stage.
         *
         * @return array{errors:list<array<string,mixed>>,warnings:list<array<string,mixed>>}
         */
        public function preflight( int $packetId ): array {
                $packet = $this->packets->get( $packetId );

                if ( null === $packet ) {
                        return array(
                                'errors'   => array(
                                        array(
                                                'code'    => 'PACKET_MISSING',
                                                'message' => __( 'Packet not found.', 'tradesheet-studio' ),
                                        ),
                                ),
                                'warnings' => array(),
                        );
                }

                $lines = $this->packets->draftLines( $packetId );

                return ( new PreflightValidator( $this->products, $this->books ) )->validate( $packet, $lines );
        }

        /**
         * Publish the current draft as a new immutable version.
         *
         * @return array{version_id:int,version_no:int,checksum:string,token:string,share_url:string,warnings:list<array<string,mixed>>}
         */
        public function publish( int $packetId, ?int $actorId ): array {
                global $wpdb;

                $packet = $this->packets->get( $packetId );
                if ( null === $packet ) {
                        throw new \RuntimeException( esc_html__( 'Packet not found.', 'tradesheet-studio' ) );
                }

                if ( PacketStateMachine::DRAFT !== (string) $packet['status'] ) {
                        throw new ValidationException( __( 'Only draft packets can be published.', 'tradesheet-studio' ) );
                }

                $lines    = $this->packets->draftLines( $packetId );
                $findings = ( new PreflightValidator( $this->products, $this->books ) )->validate( $packet, $lines );

                if ( $findings['errors'] !== array() ) {
                        $messages = implode( ' ', array_map(
                                static function ( array $error ): string {
                                        return (string) $error['message'];
                                },
                                $findings['errors']
                        ) );

                        throw new ValidationException(
                                __( 'Publication blocked by preflight errors: ', 'tradesheet-studio' ) . $messages
                        );
                }

                $storeCurrency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
                $currency      = strtoupper( (string) $packet['currency'] );
                if ( '' === $currency ) {
                        $currency = $storeCurrency;
                }

                $validityDays = max( 1, (int) $this->settings->get( 'default_validity_days' ) );
                $validUntil   = (string) ( $packet['valid_until'] ?? '' );
                if ( '' === $validUntil ) {
                        $validUntil = gmdate( 'Y-m-d H:i:s', strtotime( '+' . $validityDays . ' days', current_time( 'timestamp' ) ) );
                }

                $existingVersions = $this->packets->versions( $packetId );
                $versionNo        = 1;
                foreach ( $existingVersions as $version ) {
                        $versionNo = max( $versionNo, (int) $version['version_no'] + 1 );
                }

                $now       = Utils::nowDb();
                $company   = $this->settings->companyPresentation();
                $taxContext = $this->products->taxContext();
                $terms     = json_decode( (string) ( $packet['terms'] ?? '{}' ), true );
                $terms     = is_array( $terms ) ? $terms : array();

                $snapshotLines = array();
                foreach ( $lines as $line ) {
                        $summary = $this->products->summary( (int) $line['product_id'], (int) $line['variation_id'] );

                        $snapshotLines[] = array(
                                'line_id'           => (int) $line['id'],
                                'row_id'            => 'L' . (string) $line['id'],
                                'product_id'        => (int) $line['product_id'],
                                'variation_id'      => (int) $line['variation_id'],
                                'sku'               => (string) ( $summary['sku'] ?? $line['sku'] ),
                                'name'              => (string) ( $summary['name'] ?? $line['name'] ),
                                'variation_summary' => (string) ( $summary['variation_summary'] ?? '' ),
                                'description'       => (string) ( $summary['short_description'] ?? '' ),
                                'image_id'          => (int) ( $summary['image_id'] ?? 0 ),
                                'attributes'        => is_array( $summary['attributes'] ?? null ) ? $summary['attributes'] : array(),
                                'qty_min'           => (int) $line['qty_min'],
                                'qty_step'          => (int) $line['qty_step'],
                                'unit_price'        => (string) $line['unit_price'],
                                'price_source'      => (string) $line['price_source'],
                                'qty_breaks'        => json_decode( (string) ( $line['qty_breaks'] ?? '' ), true ) ?: array(),
                        );
                }

                $snapshot = array(
                        'packet'     => array(
                                'id'           => (int) $packet['id'],
                                'ref'          => (string) $packet['ref'],
                                'title'        => (string) $packet['title'],
                                'customer_id'  => (int) $packet['customer_id'],
                                'currency'     => $currency,
                                'pricebook_id' => (int) ( $packet['pricebook_id'] ?? 0 ),
                                'valid_until'  => $validUntil,
                                'terms'        => $terms,
                                'issued_at'    => $now,
                                'version_no'   => $versionNo,
                        ),
                        'company'    => $company,
                        'tax_context' => $taxContext,
                        'lines'      => $snapshotLines,
                );

                $canonical = Utils::canonicalJson( $snapshot );
                $checksum  = Utils::checksum( $canonical );

                // 1. Create the immutable version row.
                $wpdb->insert(
                        Schema::tableName( 'tss_packet_versions' ),
                        array(
                                'packet_id'        => $packetId,
                                'version_no'       => $versionNo,
                                'status'           => 'issued',
                                'snapshot'         => $canonical,
                                'content_checksum' => $checksum,
                                'issued_at'        => $now,
                                'expires_at'       => $validUntil,
                                'issued_by'        => $actorId,
                        ),
                        array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d' )
                );
                $versionId = (int) $wpdb->insert_id;

                if ( $versionId < 1 ) {
                        throw new \RuntimeException( esc_html__( 'The packet version could not be stored.', 'tradesheet-studio' ) );
                }

                // 2. Promote draft lines into the version with refreshed presentation data.
                foreach ( $lines as $index => $line ) {
                        $snap = $snapshotLines[ $index ];

                        $wpdb->update(
                                Schema::tableName( 'tss_packet_lines' ),
                                array(
                                        'version_id'       => $versionId,
                                        'sku'              => $snap['sku'],
                                        'name'             => $snap['name'],
                                        'variation_summary' => $snap['variation_summary'],
                                        'description'      => $snap['description'],
                                        'image_id'         => $snap['image_id'] > 0 ? $snap['image_id'] : null,
                                ),
                                array( 'id' => (int) $line['id'] ),
                                array( '%d', '%s', '%s', '%s', '%s', '%d' ),
                                array( '%d' )
                        );
                }

                // 3. Mark prior issued versions superseded.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $wpdb->query( $wpdb->prepare( 'UPDATE ' . Schema::tableName( 'tss_packet_versions' ) . " SET status = 'superseded' WHERE packet_id = %d AND status = 'issued' AND id <> %d", $packetId, $versionId ) );

                // 4. Update packet header fields, then transition state through the machine.
                $wpdb->update(
                        Schema::tableName( 'tss_packets' ),
                        array(
                                'currency'           => $currency,
                                'valid_until'        => $validUntil,
                                'current_version_id' => $versionId,
                                'updated_at'         => $now,
                        ),
                        array( 'id' => $packetId ),
                        array( '%s', '%s', '%d', '%s' ),
                        array( '%d' )
                );

                $this->packets->setStatus( $packetId, PacketStateMachine::ISSUED, $actorId );

                // 5. Fresh buyer token (previous ones revoked).
                $token = $this->buyerAccess->issueForPacket( $packetId );

                $share = add_query_arg(
                        't',
                        $token,
                        home_url( '/tradesheet/packet/' . rawurlencode( (string) $packet['ref'] ) . '/' )
                );

                $this->events->log(
                        'packet',
                        $packetId,
                        'published',
                        $actorId,
                        array(
                                'version_no' => $versionNo,
                                'checksum'   => $checksum,
                                'line_count' => count( $lines ),
                                'warnings'   => array_map(
                                        static function ( array $warning ): string {
                                                return (string) $warning['code'];
                                        },
                                        $findings['warnings']
                                ),
                        )
                );

                /**
                 * Fires after a packet version is published.
                 *
                 * @param int $packetId  Packet id.
                 * @param int $versionId Version id.
                 */
                do_action( 'tss_packet_published', $packetId, $versionId );

                return array(
                        'version_id' => $versionId,
                        'version_no' => $versionNo,
                        'checksum'   => $checksum,
                        'token'      => $token,
                        'share_url'  => $share,
                        'warnings'   => $findings['warnings'],
                );
        }

        /**
         * Start a revision: seed a fresh editable draft from the current version.
         */
        public function revise( int $packetId, ?int $actorId ): bool {
                global $wpdb;

                $packet = $this->packets->get( $packetId );
                if ( null === $packet ) {
                        throw new \RuntimeException( esc_html__( 'Packet not found.', 'tradesheet-studio' ) );
                }

                $status = (string) $packet['status'];
                if ( ! in_array( $status, array( PacketStateMachine::ISSUED, PacketStateMachine::SUBMITTED, PacketStateMachine::IN_REVIEW, PacketStateMachine::APPROVED ), true ) ) {
                        throw new ValidationException( __( 'Only issued packets (not yet accepted or terminal) can be revised.', 'tradesheet-studio' ) );
                }

                $version = $this->packets->currentVersion( $packetId );
                if ( null === $version ) {
                        throw new ValidationException( __( 'This packet has no issued version to revise.', 'tradesheet-studio' ) );
                }

                // Remove any stale draft lines (e.g. a previous abandoned revision).
                $wpdb->delete(
                        Schema::tableName( 'tss_packet_lines' ),
                        array(
                                'packet_id'  => $packetId,
                                'version_id' => null,
                        ),
                        array( '%d', '%s' )
                );

                $now = Utils::nowDb();

                foreach ( $this->packets->versionLines( (int) $version['id'] ) as $line ) {
                        $wpdb->insert(
                                Schema::tableName( 'tss_packet_lines' ),
                                array(
                                        'packet_id'        => $packetId,
                                        'version_id'       => null,
                                        'product_id'       => (int) $line['product_id'],
                                        'variation_id'     => (int) $line['variation_id'],
                                        'sku'              => (string) $line['sku'],
                                        'name'             => (string) $line['name'],
                                        'variation_summary' => (string) ( $line['variation_summary'] ?? '' ),
                                        'description'      => (string) ( $line['description'] ?? '' ),
                                        'image_id'         => $line['image_id'] ?? null,
                                        'qty_min'          => (int) $line['qty_min'],
                                        'qty_step'         => (int) $line['qty_step'],
                                        'unit_price'       => (string) $line['unit_price'],
                                        'price_source'     => (string) $line['price_source'],
                                        'qty_breaks'       => (string) ( $line['qty_breaks'] ?? '' ),
                                        'sort_order'       => (int) $line['sort_order'],
                                        'created_at'       => $now,
                                ),
                                array( '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
                        );
                }

                $this->packets->setStatus( $packetId, PacketStateMachine::DRAFT, $actorId );

                $this->events->log(
                        'packet',
                        $packetId,
                        'revision_started',
                        $actorId,
                        array( 'from_version_no' => (int) $version['version_no'] )
                );

                return true;
        }
}
