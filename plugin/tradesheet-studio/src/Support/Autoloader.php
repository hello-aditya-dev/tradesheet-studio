<?php
/**
 * Minimal PSR-4 autoloader for the TradeSheetStudio namespace.
 *
 * The production plugin ships without a Composer autoloader for its own code;
 * bundled third-party libraries (PDF renderer) keep their own vendor
 * autoloader, loaded on demand by the document renderer.
 *
 * @package TradeSheetStudio\Support
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Support;

final class Autoloader {

	private const PREFIX = 'TradeSheetStudio\\';

	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	public static function load( string $class ): void {
		if ( ! str_starts_with( $class, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class, strlen( self::PREFIX ) );
		$file     = TSS_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_file( $file ) ) {
			require_once $file;
		}
	}
}
