<?php
/**
 * Generated-document storage and delivery.
 *
 * Files live under uploads/tradesheet-studio-docs/packet-{id}/version-{id}/
 * (web access denied by .htaccess) and are streamed ONLY through
 * permission-checked endpoints with no-cache/no-index headers. Every
 * delivered document prints its issue date, version and validity window —
 * downloaded copies cannot be retroactively revoked.
 *
 * @package TradeSheetStudio\Documents
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Documents;

use TradeSheetStudio\Core\Installer;
use TradeSheetStudio\Database\Schema;
use TradeSheetStudio\Packets\PacketRepository;
use TradeSheetStudio\Settings\MerchantSettingsService;
use TradeSheetStudio\Support\EventLogger;
use TradeSheetStudio\Support\Utils;

final class DocumentService {

        public function __construct(
                private PacketRepository $packets,
                private PdfRenderer $pdf,
                private CsvOrderSheetService $csv,
                private MerchantSettingsService $settings,
                private EventLogger $events
        ) {
        }

        public function isRendererAvailable(): bool {
                return $this->pdf->isAvailable();
        }

        /**
         * Generate (or regenerate) PDF + CSV + ZIP for a version.
         *
         * @return array{documents:list<int>,template:string}
         */
        public function generateForVersion( int $versionId, ?int $actorId, ?string $templateOverride = null ): array {
                $version = $this->packets->version( $versionId );
                if ( null === $version ) {
                        throw new \RuntimeException( esc_html__( 'Packet version not found.', 'tradesheet-studio' ) );
                }

                $packetId = (int) $version['packet_id'];
                $template = $templateOverride ?? (string) $this->settings->get( 'default_template' );
                if ( ! in_array( $template, PdfRenderer::TEMPLATES, true ) ) {
                        $template = 'trade_table';
                }

                $ids = array();

                $pdfBytes = $this->pdf->render( $versionId, $template );
                $ids[] = $this->store( $versionId, $packetId, 'pdf', $template, $pdfBytes, $actorId );

                $csvBytes = $this->csv->export( $versionId );
                $ids[] = $this->store( $versionId, $packetId, 'csv', '', $csvBytes, $actorId );

                // ZIP bundle of everything available for this version.
                if ( class_exists( \ZipArchive::class ) ) {
                        $zipBytes = $this->buildZip( $versionId, $packetId );
                        if ( '' !== $zipBytes ) {
                                $ids[] = $this->store( $versionId, $packetId, 'zip', '', $zipBytes, $actorId );
                        }
                }

                $this->events->log(
                        'document',
                        $versionId,
                        'generated',
                        $actorId,
                        array(
                                'types'    => array( 'pdf', 'csv', 'zip' ),
                                'template' => $template,
                        )
                );

                return array(
                        'documents' => $ids,
                        'template'  => $template,
                );
        }

        private function store( int $versionId, int $packetId, string $type, string $template, string $bytes, ?int $actorId ): int {
                global $wpdb;

                $version = $this->packets->version( $versionId );
                $versionNo = null === $version ? 1 : (int) $version['version_no'];
                $packet = $this->packets->get( $packetId );
                $ref = null === $packet ? 'packet' : (string) $packet['ref'];

                $dir = self::versionDir( $packetId, $versionId );
                if ( ! is_dir( $dir ) ) {
                        wp_mkdir_p( $dir );
                }

                $extension = $type;
                $basename  = strtoupper( preg_replace( '/[^A-Za-z0-9\-]/', '', $ref ) ) . '-v' . $versionNo;
                if ( '' !== $template ) {
                        $basename .= '-' . str_replace( '_', '-', $template );
                }
                $basename .= '.' . $extension;

                $path = $dir . $basename;

                if ( false === @file_put_contents( $path, $bytes ) ) {
                        throw new \RuntimeException( esc_html__( 'The document could not be written to private storage.', 'tradesheet-studio' ) );
                }

                // Regeneration replaces: remove stale row + file of the same slot.
                $this->deleteDocumentRow( $versionId, $type, $template );

                $relative = $this->relativePath( $path );

                $wpdb->insert(
                        Schema::tableName( 'tss_documents' ),
                        array(
                                'version_id'   => $versionId,
                                'packet_id'    => $packetId,
                                'type'         => $type,
                                'template'     => $template,
                                'file_path'    => $relative,
                                'file_hash'    => hash( 'sha256', $bytes ),
                                'size_bytes'   => strlen( $bytes ),
                                'generated_at' => Utils::nowDb(),
                                'generated_by' => $actorId,
                        ),
                        array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d' )
                );

                return (int) $wpdb->insert_id;
        }

        private function buildZip( int $versionId, int $packetId ): string {
                $documents = $this->documentsForVersion( $versionId );

                if ( $documents === array() ) {
                        return '';
                }

                $tmp = tempnam( get_temp_dir(), 'tsszip' );
                if ( false === $tmp ) {
                        return '';
                }

                $zip = new \ZipArchive();
                if ( true !== $zip->open( $tmp, \ZipArchive::OVERWRITE ) ) {
                        @unlink( $tmp );

                        return '';
                }

                foreach ( $documents as $doc ) {
                        if ( 'zip' === (string) $doc['type'] ) {
                                continue;
                        }
                        $path = $this->absolutePath( (string) $doc['file_path'] );
                        if ( '' !== $path ) {
                                $zip->addFile( $path, basename( $path ) );
                        }
                }

                $zip->close();

                $bytes = (string) @file_get_contents( $tmp );
                @unlink( $tmp );

                return $bytes;
        }

        /**
         * @return list<array<string,mixed>>
         */
        public function documentsForVersion( int $versionId ): array {
                global $wpdb;

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_documents' ) . ' WHERE version_id = %d ORDER BY type ASC, template ASC', $versionId ), ARRAY_A );

                return is_array( $rows ) ? $rows : array();
        }

        /**
         * @return array<string,mixed>|null
         */
        public function document( int $documentId ): ?array {
                global $wpdb;

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_documents' ) . ' WHERE id = %d', $documentId ), ARRAY_A );

                return is_array( $row ) ? $row : null;
        }

        /**
         * Stream a stored document (caller has ALREADY verified authorization).
         * Path traversal is blocked by realpath containment checks.
         */
        public function stream( int $documentId ): void {
                $doc = $this->document( $documentId );

                if ( null === $doc ) {
                        wp_die( esc_html__( 'Document not found.', 'tradesheet-studio' ), '', array( 'response' => 404 ) );
                }

                $path = $this->absolutePath( (string) $doc['file_path'] );

                if ( '' === $path || ! is_file( $path ) ) {
                        wp_die( esc_html__( 'Document file is missing from private storage.', 'tradesheet-studio' ), '', array( 'response' => 404 ) );
                }

                $mime = array(
                        'pdf' => 'application/pdf',
                        'csv' => 'text/csv; charset=utf-8',
                        'zip' => 'application/zip',
                );

                nocache_headers();
                header( 'X-Robots-Tag: noindex, nofollow' );
                header( 'Content-Type: ' . ( $mime[ (string) $doc['type'] ] ?? 'application/octet-stream' ) );
                header( 'Content-Disposition: attachment; filename="' . basename( $path ) . '"' );
                header( 'Content-Length: ' . (string) filesize( $path ) );
                header( 'Referrer-Policy: no-referrer' );

                readfile( $path );
                exit;
        }

        private function deleteDocumentRow( int $versionId, string $type, string $template ): void {
                global $wpdb;

                $wpdb->delete(
                        Schema::tableName( 'tss_documents' ),
                        array(
                                'version_id' => $versionId,
                                'type'       => $type,
                                'template'   => $template,
                        ),
                        array( '%d', '%s', '%s' )
                );
        }

        public function deleteForVersion( int $versionId ): void {
                foreach ( $this->documentsForVersion( $versionId ) as $doc ) {
                        $path = $this->absolutePath( (string) $doc['file_path'] );
                        if ( '' !== $path ) {
                                @unlink( $path );
                        }
                }

                global $wpdb;
                $wpdb->delete( Schema::tableName( 'tss_documents' ), array( 'version_id' => $versionId ), array( '%d' ) );
        }

        public function deleteForPacket( int $packetId ): void {
                global $wpdb;

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $versionIds = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . Schema::tableName( 'tss_packet_versions' ) . ' WHERE packet_id = %d', $packetId ) );

                if ( is_array( $versionIds ) ) {
                        foreach ( $versionIds as $versionId ) {
                                $this->deleteForVersion( (int) $versionId );
                        }
                }

                self::removeDirectory( self::packetDir( $packetId ) );
        }

        /**
         * Retention sweep (0 = keep forever).
         */
        public function retentionCleanup(): void {
                global $wpdb;

                $days = (int) $this->settings->get( 'file_retention_days' );
                if ( $days < 1 ) {
                        return;
                }

                $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days', current_time( 'timestamp' ) ) );

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, version_id, file_path FROM ' . Schema::tableName( 'tss_documents' ) . ' WHERE generated_at < %s', $cutoff ), ARRAY_A );

                if ( is_array( $rows ) ) {
                        foreach ( $rows as $row ) {
                                $path = $this->absolutePath( (string) $row['file_path'] );
                                if ( '' !== $path ) {
                                        @unlink( $path );
                                }
                                $wpdb->delete( Schema::tableName( 'tss_documents' ), array( 'id' => (int) $row['id'] ), array( '%d' ) );
                        }
                }
        }

        public static function packetDir( int $packetId ): string {
                return Installer::storageDir() . 'packet-' . (int) $packetId . '/';
        }

        public static function versionDir( int $packetId, int $versionId ): string {
                return self::packetDir( $packetId ) . 'version-' . (int) $versionId . '/';
        }

        private function relativePath( string $absolute ): string {
                $base = Installer::storageDir();

                return ltrim( str_replace( $base, '', $absolute ), '/' );
        }

        /**
         * Resolve a stored relative path to an absolute path, enforcing storage
         * containment (path traversal protection). Returns '' when invalid.
         */
        private function absolutePath( string $relative ): string {
                $base = realpath( Installer::storageDir() );
                if ( false === $base ) {
                        return '';
                }

                $candidate = realpath( Installer::storageDir() . $relative );

                if ( false === $candidate || ! str_starts_with( $candidate, $base ) ) {
                        return '';
                }

                return $candidate;
        }

        private static function removeDirectory( string $dir ): void {
                if ( ! is_dir( $dir ) ) {
                        return;
                }

                $iterator = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
                        \RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ( $iterator as $file ) {
                        if ( $file->isDir() ) {
                                @rmdir( $file->getPathname() );
                        } else {
                                @unlink( $file->getPathname() );
                        }
                }

                @rmdir( $dir );
        }
}
