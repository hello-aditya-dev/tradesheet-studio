<?php
/**
 * Database schema definitions (dbDelta-compatible formatting).
 *
 * All tables are prefixed with {$wpdb->prefix}tss_ and created through
 * dbDelta() so upgrades are additive and versioned via the
 * `tss_schema_version` option. All access goes through $wpdb->prepare();
 * WooCommerce's own tables are never modified.
 *
 * Money columns use DECIMAL(19,4); see \TradeSheetStudio\Support\Money for
 * the exact decimal-string arithmetic used in application code.
 *
 * @package TradeSheetStudio\Database
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Database;

final class Schema {

	public static function version(): string {
		return TSS_SCHEMA_VERSION;
	}

	/**
	 * @return array<string, string> table name => CREATE TABLE statement.
	 */
	public static function tables(): array {
		global $wpdb;

		$prefix   = $wpdb->prefix;
		$collate  = $wpdb->get_charset_collate();

		$tables = array();

		$tables[ $prefix . 'tss_pricebooks' ] = "
			CREATE TABLE {$prefix}tss_pricebooks (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				name VARCHAR(191) NOT NULL DEFAULT '',
				description TEXT NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'active',
				currency CHAR(3) NOT NULL DEFAULT '',
				priority SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				valid_from DATETIME NULL DEFAULT NULL,
				valid_to DATETIME NULL DEFAULT NULL,
				created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				PRIMARY KEY  (id),
				KEY status (status)
			) $collate;";

		$tables[ $prefix . 'tss_price_rules' ] = "
			CREATE TABLE {$prefix}tss_price_rules (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				pricebook_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				customer_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				variation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				price_override DECIMAL(19,4) NULL DEFAULT NULL,
				percent_off DECIMAL(9,2) NULL DEFAULT NULL,
				min_qty INT UNSIGNED NOT NULL DEFAULT 1,
				qty_step INT UNSIGNED NOT NULL DEFAULT 1,
				qty_breaks LONGTEXT NULL,
				priority SMALLINT NOT NULL DEFAULT 0,
				valid_from DATETIME NULL DEFAULT NULL,
				valid_to DATETIME NULL DEFAULT NULL,
				created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				PRIMARY KEY  (id),
				UNIQUE KEY rule_scope (pricebook_id, customer_id, product_id, variation_id),
				KEY product_lookup (product_id, variation_id),
				KEY customer_lookup (customer_id)
			) $collate;";

		$tables[ $prefix . 'tss_packets' ] = "
			CREATE TABLE {$prefix}tss_packets (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				ref VARCHAR(32) NOT NULL DEFAULT '',
				customer_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				title VARCHAR(191) NOT NULL DEFAULT '',
				status VARCHAR(20) NOT NULL DEFAULT 'draft',
				currency CHAR(3) NOT NULL DEFAULT '',
				pricebook_id BIGINT UNSIGNED NULL DEFAULT NULL,
				current_version_id BIGINT UNSIGNED NULL DEFAULT NULL,
				valid_until DATETIME NULL DEFAULT NULL,
				terms LONGTEXT NULL,
				created_by BIGINT UNSIGNED NULL DEFAULT NULL,
				created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				PRIMARY KEY  (id),
				UNIQUE KEY packet_ref (ref),
				KEY customer (customer_id),
				KEY status (status)
			) $collate;";

		$tables[ $prefix . 'tss_packet_versions' ] = "
			CREATE TABLE {$prefix}tss_packet_versions (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				packet_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				version_no INT UNSIGNED NOT NULL DEFAULT 1,
				status VARCHAR(20) NOT NULL DEFAULT 'issued',
				snapshot LONGTEXT NULL,
				content_checksum CHAR(64) NOT NULL DEFAULT '',
				issued_at DATETIME NULL DEFAULT NULL,
				expires_at DATETIME NULL DEFAULT NULL,
				issued_by BIGINT UNSIGNED NULL DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY packet_version (packet_id, version_no),
				KEY checksum (content_checksum)
			) $collate;";

		$tables[ $prefix . 'tss_packet_lines' ] = "
			CREATE TABLE {$prefix}tss_packet_lines (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				packet_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				version_id BIGINT UNSIGNED NULL DEFAULT NULL,
				product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				variation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				sku VARCHAR(100) NOT NULL DEFAULT '',
				name TEXT NULL,
				variation_summary TEXT NULL,
				description TEXT NULL,
				image_id BIGINT UNSIGNED NULL DEFAULT NULL,
				qty_min INT UNSIGNED NOT NULL DEFAULT 1,
				qty_step INT UNSIGNED NOT NULL DEFAULT 1,
				unit_price DECIMAL(19,4) NOT NULL DEFAULT 0,
				price_source VARCHAR(40) NOT NULL DEFAULT 'base',
				qty_breaks LONGTEXT NULL,
				sort_order INT UNSIGNED NOT NULL DEFAULT 0,
				line_meta LONGTEXT NULL,
				created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				PRIMARY KEY  (id),
				KEY version_lines (version_id, sort_order),
				KEY packet_lines (packet_id, version_id),
				KEY product_lookup (product_id, variation_id)
			) $collate;";

		$tables[ $prefix . 'tss_documents' ] = "
			CREATE TABLE {$prefix}tss_documents (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				version_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				packet_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				type VARCHAR(20) NOT NULL DEFAULT 'pdf',
				template VARCHAR(40) NOT NULL DEFAULT '',
				file_path VARCHAR(255) NOT NULL DEFAULT '',
				file_hash CHAR(64) NOT NULL DEFAULT '',
				size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
				generated_at DATETIME NULL DEFAULT NULL,
				generated_by BIGINT UNSIGNED NULL DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY version_doc (version_id, type, template),
				KEY packet_docs (packet_id)
			) $collate;";

		$tables[ $prefix . 'tss_buyer_tokens' ] = "
			CREATE TABLE {$prefix}tss_buyer_tokens (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				packet_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				token_hash CHAR(64) NOT NULL DEFAULT '',
				created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				expires_at DATETIME NULL DEFAULT NULL,
				revoked_at DATETIME NULL DEFAULT NULL,
				last_used_at DATETIME NULL DEFAULT NULL,
				use_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY token_hash (token_hash),
				KEY packet_tokens (packet_id)
			) $collate;";

		$tables[ $prefix . 'tss_quotes' ] = "
			CREATE TABLE {$prefix}tss_quotes (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				ref VARCHAR(32) NOT NULL DEFAULT '',
				packet_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				version_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				revision_no INT UNSIGNED NOT NULL DEFAULT 1,
				parent_quote_id BIGINT UNSIGNED NULL DEFAULT NULL,
				customer_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				status VARCHAR(24) NOT NULL DEFAULT 'requested',
				requested_at DATETIME NULL DEFAULT NULL,
				reviewed_at DATETIME NULL DEFAULT NULL,
				approved_at DATETIME NULL DEFAULT NULL,
				accepted_at DATETIME NULL DEFAULT NULL,
				expires_at DATETIME NULL DEFAULT NULL,
				converted_order_id BIGINT UNSIGNED NULL DEFAULT NULL,
				notes_buyer TEXT NULL,
				notes_merchant TEXT NULL,
				discrepancy_report LONGTEXT NULL,
				created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				PRIMARY KEY  (id),
				UNIQUE KEY quote_ref (ref),
				KEY packet (packet_id),
				KEY customer (customer_id),
				KEY status (status),
				KEY version (version_id)
			) $collate;";

		$tables[ $prefix . 'tss_quote_lines' ] = "
			CREATE TABLE {$prefix}tss_quote_lines (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				quote_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				packet_line_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				variation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				sku VARCHAR(100) NOT NULL DEFAULT '',
				name TEXT NULL,
				variation_summary TEXT NULL,
				qty INT UNSIGNED NOT NULL DEFAULT 0,
				unit_price DECIMAL(19,4) NOT NULL DEFAULT 0,
				price_source VARCHAR(40) NOT NULL DEFAULT 'base',
				line_status VARCHAR(20) NOT NULL DEFAULT 'ok',
				merchant_note VARCHAR(255) NULL DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY quote_lines (quote_id),
				KEY packet_line (packet_line_id)
			) $collate;";

		$tables[ $prefix . 'tss_order_links' ] = "
			CREATE TABLE {$prefix}tss_order_links (
				quote_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				order_id BIGINT UNSIGNED NULL DEFAULT NULL,
				conversion_key CHAR(64) NOT NULL DEFAULT '',
				status VARCHAR(20) NOT NULL DEFAULT 'converting',
				attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				error LONGTEXT NULL,
				created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				completed_at DATETIME NULL DEFAULT NULL,
				PRIMARY KEY  (quote_id),
				UNIQUE KEY conversion_key (conversion_key)
			) $collate;";

		$tables[ $prefix . 'tss_events' ] = "
			CREATE TABLE {$prefix}tss_events (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				object_type VARCHAR(30) NOT NULL DEFAULT '',
				object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				action VARCHAR(40) NOT NULL DEFAULT '',
				actor_id BIGINT UNSIGNED NULL DEFAULT NULL,
				details LONGTEXT NULL,
				created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				PRIMARY KEY  (id),
				KEY object (object_type, object_id),
				KEY created (created_at)
			) $collate;";

		$tables[ $prefix . 'tss_jobs' ] = "
			CREATE TABLE {$prefix}tss_jobs (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				type VARCHAR(40) NOT NULL DEFAULT '',
				payload LONGTEXT NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'queued',
				attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3,
				scheduled_at DATETIME NULL DEFAULT NULL,
				started_at DATETIME NULL DEFAULT NULL,
				finished_at DATETIME NULL DEFAULT NULL,
				error LONGTEXT NULL,
				checkpoint LONGTEXT NULL,
				created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
				PRIMARY KEY  (id),
				KEY status (status),
				KEY type_status (type, status)
			) $collate;";

		return $tables;
	}

	/**
	 * @return list<string> unprefixed table identifiers ("tss_...").
	 */
	public static function tableIds(): array {
		return array(
			'tss_pricebooks',
			'tss_price_rules',
			'tss_packets',
			'tss_packet_versions',
			'tss_packet_lines',
			'tss_documents',
			'tss_buyer_tokens',
			'tss_quotes',
			'tss_quote_lines',
			'tss_order_links',
			'tss_events',
			'tss_jobs',
		);
	}

	public static function tableName( string $id ): string {
		global $wpdb;
		return $wpdb->prefix . $id;
	}

	/**
	 * Uninstall-time drop. Called only from uninstall.php when the merchant
	 * has explicitly opted into full data removal.
	 */
	public static function dropTables(): void {
		global $wpdb;

		foreach ( self::tableIds() as $id ) {
			$table = $wpdb->prefix . $id;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from a fixed internal list.
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		}
	}
}
