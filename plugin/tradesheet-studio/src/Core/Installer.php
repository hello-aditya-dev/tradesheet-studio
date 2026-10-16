<?php
/**
 * Activation, deactivation and schema upgrades.
 *
 * Deactivation preserves all data (safe by default). Full removal —
 * including tables and generated documents — happens only through
 * uninstall.php when the merchant has enabled "delete all data" in settings.
 *
 * @package TradeSheetStudio\Core
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Core;

use TradeSheetStudio\Database\Schema;
use TradeSheetStudio\Support\Utils;

final class Installer {

	public static function activate(): void {
		self::createTables();
		self::ensureStorage();

		update_option( 'tss_schema_version', Schema::version(), false );
		update_option( 'tss_version', TSS_VERSION, false );
		update_option( 'tss_installed_at', Utils::nowDb(), false );

		if ( ! class_exists( 'WooCommerce' ) ) {
			set_transient( 'tss_activation_missing_woocommerce', 1, 10 * MINUTE_IN_SECONDS );
		}

		self::scheduleSweeps();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'tss_hourly_sweep' );

		// Intentionally NO data deletion on deactivation.
	}

	/**
	 * Forward migrations run on admin requests when the schema version
	 * changes (additive dbDelta upgrades).
	 */
	public function maybeUpgrade(): void {
		$current = (string) get_option( 'tss_schema_version', '' );

		if ( $current !== Schema::version() ) {
			self::createTables();
			update_option( 'tss_schema_version', Schema::version(), false );
		}

		if ( get_option( 'tss_version' ) !== TSS_VERSION ) {
			update_option( 'tss_version', TSS_VERSION, false );
		}
	}

	private static function createTables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( Schema::tables() as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Private document storage under uploads/tradesheet-studio-docs.
	 * Direct web access is denied (.htaccess + index.php) and files are
	 * additionally delivered only through authenticated, permission-checked
	 * streaming endpoints — defence in depth.
	 */
	private static function ensureStorage(): void {
		$dir = self::storageDir();

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		if ( ! file_exists( $dir . '.htaccess' ) ) {
			@file_put_contents( $dir . '.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n" );
		}

		if ( ! file_exists( $dir . 'index.php' ) ) {
			@file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" );
		}
	}

	public static function storageDir(): string {
		$uploads = wp_upload_dir();

		return trailingslashit( $uploads['basedir'] ) . 'tradesheet-studio-docs/';
	}

	private static function scheduleSweeps(): void {
		if ( ! wp_next_scheduled( 'tss_hourly_sweep' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', 'tss_hourly_sweep' );
		}
	}

	/**
	 * Removal of generated files (uninstall path only).
	 */
	public static function removeStorage(): void {
		$dir = self::storageDir();

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
