<?php
/**
 * Capability model. Merchant capabilities ride on WooCommerce's
 * manage_woocommerce (administrators and shop managers) — filtered for
 * site-specific customization.
 *
 * @package TradeSheetStudio\Security
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Security;

final class Capabilities {

	public const MANAGE_CAPABILITY = 'manage_woocommerce';

	public static function canManage(): bool {
		$capability = apply_filters( 'tss_manage_capability', self::MANAGE_CAPABILITY );

		return current_user_can( is_string( $capability ) ? $capability : self::MANAGE_CAPABILITY );
	}

	/**
	 * REST permission callback for merchant routes. Cookie-authenticated
	 * requests additionally require a valid X-WP-Nonce, enforced by the REST
	 * API infrastructure.
	 */
	public static function restCanManage(): bool {
		return self::canManage();
	}
}
