<?php
/**
 * TradeSheet Studio — Wholesale sales packets, PDF catalogs, CSV order sheets
 * and quotation management for WooCommerce.
 *
 * "The wholesale catalog that becomes an order."
 *
 * @package TradeSheetStudio
 *
 * Plugin Name:          TradeSheet Studio
 * Plugin URI:           https://dev-aditya.com
 * Description:          Wholesale sales packets, PDF catalogs, CSV order sheets and quotation management for WooCommerce. The wholesale catalog that becomes an order.
 * Version:              1.0.0
 * Requires at least:    6.4
 * Requires PHP:         8.1
 * WC requires at least: 8.0
 * WC tested up to:      11.2
 * Author:               Aditya
 * Author URI:           https://dev-aditya.com
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain:          tradesheet-studio
 * Domain Path:          /languages
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'TSS_VERSION', '1.0.0' );
define( 'TSS_PLUGIN_FILE', __FILE__ );
define( 'TSS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TSS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TSS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'TSS_SCHEMA_VERSION', '2026-10-16-1' );

require_once TSS_PLUGIN_DIR . 'src/Support/Autoloader.php';
\TradeSheetStudio\Support\Autoloader::register();

// Declare WooCommerce feature compatibility (HPOS) before WooCommerce boots.
add_action( 'before_woocommerce_init', static function (): void {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', TSS_PLUGIN_FILE, true );
        }
} );

register_activation_hook( __FILE__, array( \TradeSheetStudio\Core\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \TradeSheetStudio\Core\Installer::class, 'deactivate' ) );

add_action( 'plugins_loaded', static function (): void {
        $requirements = new \TradeSheetStudio\Core\Requirements();
        if ( ! $requirements->check() ) {
                $requirements->notify();
                return;
        }

        ( new \TradeSheetStudio\Core\Plugin() )->boot();
}, 20 );
