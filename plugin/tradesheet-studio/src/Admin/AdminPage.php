<?php
/**
 * Admin screen: WooCommerce submenu page hosting the compiled React app.
 *
 * @package TradeSheetStudio\Admin
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Admin;

use TradeSheetStudio\Core\Plugin;
use TradeSheetStudio\Security\Capabilities;

final class AdminPage {

	public function __construct( private Plugin $plugin ) {
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'TradeSheet Studio', 'tradesheet-studio' ),
			__( 'TradeSheet Studio', 'tradesheet-studio' ),
			'manage_woocommerce',
			'tradesheet-studio',
			array( $this, 'render' )
		);
	}

	public function assets( string $hook ): void {
		if ( 'woocommerce_page_tradesheet-studio' !== $hook ) {
			return;
		}

		if ( ! file_exists( TSS_PLUGIN_DIR . 'assets/admin.js' ) ) {
			return; // adminNotices() explains the missing compiled bundle.
		}

		wp_enqueue_script(
			'tss-admin',
			TSS_PLUGIN_URL . 'assets/admin.js',
			array( 'wp-element', 'wp-i18n', 'wp-api-fetch', 'wp-components' ),
			TSS_VERSION,
			true
		);

		wp_enqueue_style(
			'tss-admin',
			TSS_PLUGIN_URL . 'assets/admin.css',
			array( 'wp-components' ),
			TSS_VERSION
		);

		wp_set_script_translations( 'tss-admin', 'tradesheet-studio' );

		$bootstrap = array(
			'restUrl'        => esc_url_raw( \TradeSheetStudio\Api\Routes::url() ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'version'        => TSS_VERSION,
			'currency'       => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'currencySymbol' => function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol() ) : '',
			'adminUrl'       => admin_url(),
			'homeUrl'        => home_url( '/' ),
			'pdfRenderer'    => $this->plugin->documents()->isRendererAvailable(),
		);

		wp_add_inline_script(
			'tss-admin',
			'window.TSS_ADMIN = ' . \TradeSheetStudio\Support\Utils::jsonEncode( $bootstrap ) . ';',
			'before'
		);
	}

	public function render(): void {
		if ( ! Capabilities::canManage() ) {
			wp_die( esc_html__( 'You do not have permission to access TradeSheet Studio.', 'tradesheet-studio' ) );
		}

		printf(
			'<div class="wrap"><div id="tss-admin-root" class="tss-admin-root"></div></div>'
		);
	}
}
