<?php
/**
 * TradeSheet Studio uninstall.
 *
 * SAFE BY DEFAULT: data is preserved unless the merchant explicitly enabled
 * "Delete all TradeSheet Studio data on uninstall" in Settings.
 *
 * @package TradeSheetStudio
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$tss_settings = get_option( 'tss_settings', array() );

if ( empty( $tss_settings['delete_data_on_uninstall'] ) ) {
	// Preserve all commercial records and documents by default.
	return;
}

// Full removal was explicitly requested.
$tss_plugin_dir = plugin_dir_path( __FILE__ );

require_once $tss_plugin_dir . 'src/Database/Schema.php';

\TradeSheetStudio\Database\Schema::dropTables();

// Private document files.
$tss_uploads = wp_upload_dir();
$tss_doc_dir = trailingslashit( $tss_uploads['basedir'] ) . 'tradesheet-studio-docs/';

if ( is_dir( $tss_doc_dir ) ) {
	$tss_iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $tss_doc_dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $tss_iterator as $tss_file ) {
		if ( $tss_file->isDir() ) {
			@rmdir( $tss_file->getPathname() );
		} else {
			@unlink( $tss_file->getPathname() );
		}
	}

	@rmdir( $tss_doc_dir );
}

// Options and scheduled events.
delete_option( 'tss_settings' );
delete_option( 'tss_schema_version' );
delete_option( 'tss_version' );
delete_option( 'tss_installed_at' );
delete_option( 'tss_last_sweep_at' );
delete_transient( 'tss_activation_missing_woocommerce' );

wp_clear_scheduled_hook( 'tss_hourly_sweep' );

// Customer assignments (user meta).
$tss_users = get_users(
	array(
		'meta_key' => '_tss_pricebook_id',
		'fields'   => array( 'ID' ),
		'number'   => 100000,
	)
);

foreach ( $tss_users as $tss_user ) {
	delete_user_meta( (int) $tss_user->ID, '_tss_pricebook_id' );
}
