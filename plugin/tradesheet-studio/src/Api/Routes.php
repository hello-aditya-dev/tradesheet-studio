<?php
/**
 * REST route registrar. Every route declares a permission_callback; the
 * WordPress REST API enforces the X-WP-Nonce for cookie-authenticated
 * state-changing browser requests on top of the capability checks.
 *
 * @package TradeSheetStudio\Api
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Api;

use TradeSheetStudio\Core\Plugin;

final class Routes {

	public const NAMESPACE_V1 = 'tradesheet-studio/v1';

	public static function register( Plugin $plugin ): void {
		( new CatalogController( $plugin ) )->register();
		( new PriceBooksController( $plugin ) )->register();
		( new PacketsController( $plugin ) )->register();
		( new QuotesController( $plugin ) )->register();
		( new SystemController( $plugin ) )->register();
	}

	public static function url( string $path = '' ): string {
		return rest_url( self::NAMESPACE_V1 . ( '' === $path ? '' : '/' . ltrim( $path, '/' ) ) );
	}
}
