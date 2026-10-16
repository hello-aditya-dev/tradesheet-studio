<?php
/**
 * System endpoints: dashboard metrics, settings, diagnostics.
 *
 * @package TradeSheetStudio\Api
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Api;

use TradeSheetStudio\Core\Installer;
use TradeSheetStudio\Core\Plugin;
use TradeSheetStudio\Documents\PdfRenderer;
use TradeSheetStudio\Security\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class SystemController {

	public function __construct( private Plugin $plugin ) {
	}

	public function register(): void {
		$manage = array( Capabilities::class, 'restCanManage' );

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/dashboard',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'dashboard' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'getSettings' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'updateSettings' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/diagnostics',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'diagnostics' ),
					'permission_callback' => $manage,
				),
			)
		);
	}

	public function dashboard(): WP_REST_Response {
		$metrics = $this->plugin->packets()->dashboardMetrics();
		$events  = $this->plugin->events()->recent( 25 );

		return new WP_REST_Response(
			array(
				'metrics' => $metrics,
				'events'  => array_map( array( $this, 'serializeEvent' ), $events ),
			),
			200
		);
	}

	public function getSettings(): WP_REST_Response {
		$settings = $this->plugin->settings()->all();

		return new WP_REST_Response(
			array(
				'settings'        => $settings,
				'currency'        => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
				'currency_symbol' => function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol() ) : '',
				'templates'       => PdfRenderer::TEMPLATES,
			),
			200
		);
	}

	public function updateSettings( WP_REST_Request $request ) {
		$values = $request->get_json_params();
		$values = is_array( $values ) ? $values : $request->get_params();

		$settings = $this->plugin->settings()->update( $values );
		$this->plugin->events()->log( 'settings', 0, 'updated', get_current_user_id(), array( 'keys' => array_keys( $values ) ) );

		return new WP_REST_Response( array( 'settings' => $settings ), 200 );
	}

	/**
	 * Real environment checks — no fabricated results.
	 */
	public function diagnostics(): WP_REST_Response {
		$checks = array();

		$checks[] = array(
			'name'   => 'php_version',
			'label'  => __( 'PHP version', 'tradesheet-studio' ),
			'ok'     => PHP_VERSION_ID >= 80100,
			'value'  => PHP_VERSION,
			'advice' => __( 'PHP 8.1 or newer is required (8.2+ recommended).', 'tradesheet-studio' ),
		);

		$checks[] = array(
			'name'   => 'wordpress_version',
			'label'  => __( 'WordPress version', 'tradesheet-studio' ),
			'ok'     => version_compare( (string) get_bloginfo( 'version' ), '6.4', '>=' ),
			'value'  => (string) get_bloginfo( 'version' ),
			'advice' => __( 'WordPress 6.4 or newer is required.', 'tradesheet-studio' ),
		);

		$wcVersion = class_exists( 'WooCommerce' ) ? (string) WC()->version : '';
		$checks[]  = array(
			'name'   => 'woocommerce',
			'label'  => __( 'WooCommerce', 'tradesheet-studio' ),
			'ok'     => '' !== $wcVersion && version_compare( $wcVersion, '8.0', '>=' ),
			'value'  => '' !== $wcVersion ? $wcVersion : __( 'inactive', 'tradesheet-studio' ),
			'advice' => __( 'WooCommerce 8.0 or newer must be active.', 'tradesheet-studio' ),
		);

		$hpos = get_option( 'woocommerce_custom_orders_table_enabled', null );
		$checks[] = array(
			'name'   => 'hpos',
			'label'  => __( 'High-Performance Order Storage', 'tradesheet-studio' ),
			'ok'     => true, // Compatible with both modes; informational.
			'value'  => null === $hpos ? __( 'unknown', 'tradesheet-studio' ) : ( $hpos ? __( 'enabled', 'tradesheet-studio' ) : __( 'disabled (legacy storage)', 'tradesheet-studio' ) ),
			'advice' => __( 'The plugin declares HPOS compatibility and works with both order storages.', 'tradesheet-studio' ),
		);

		$asAvailable = function_exists( 'as_schedule_single_action' );
		$checks[] = array(
			'name'   => 'action_scheduler',
			'label'  => __( 'Action Scheduler', 'tradesheet-studio' ),
			'ok'     => $asAvailable,
			'value'  => $asAvailable ? __( 'available', 'tradesheet-studio' ) : __( 'missing — WP-Cron fallback active', 'tradesheet-studio' ),
			'advice' => __( 'Background jobs run through WooCommerce\'s Action Scheduler when available.', 'tradesheet-studio' ),
		);

		$cronDisabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$sweepScheduled = false !== wp_next_scheduled( 'tss_hourly_sweep' );
		$checks[] = array(
			'name'   => 'task_scheduling',
			'label'  => __( 'Task scheduling health', 'tradesheet-studio' ),
			'ok'     => $sweepScheduled && ! ( $cronDisabled && ! $asAvailable ),
			'value'  => sprintf(
				/* translators: 1: sweep state, 2: cron state. */
				__( 'Hourly sweep %1$s; WP-Cron %2$s', 'tradesheet-studio' ),
				$sweepScheduled ? __( 'scheduled', 'tradesheet-studio' ) : __( 'missing', 'tradesheet-studio' ),
				$cronDisabled ? __( 'disabled', 'tradesheet-studio' ) : __( 'enabled', 'tradesheet-studio' )
			),
			'advice' => __( 'If WP-Cron is disabled, ensure a system cron triggers wp-cron.php, or rely on Action Scheduler.', 'tradesheet-studio' ),
		);

		$storageDir = Installer::storageDir();
		$checks[]   = array(
			'name'   => 'document_storage',
			'label'  => __( 'Private document storage', 'tradesheet-studio' ),
			'ok'     => is_dir( $storageDir ) && wp_is_writable( $storageDir ),
			'value'  => $storageDir,
			'advice' => __( 'The uploads directory must be writable; documents are served through permission-checked streaming only.', 'tradesheet-studio' ),
		);

		$checks[] = array(
			'name'   => 'pdf_renderer',
			'label'  => __( 'PDF renderer', 'tradesheet-studio' ),
			'ok'     => $this->plugin->documents()->isRendererAvailable(),
			'value'  => $this->plugin->documents()->isRendererAvailable() ? __( 'Dompdf bundled', 'tradesheet-studio' ) : __( 'missing', 'tradesheet-studio' ),
			'advice' => __( 'Reinstall the plugin if the bundled PDF renderer is missing.', 'tradesheet-studio' ),
		);

		foreach ( array( 'mbstring', 'dom', 'zip', 'gd' ) as $extension ) {
			$checks[] = array(
				'name'   => 'ext_' . $extension,
				'label'  => sprintf( /* translators: %s: extension */ __( 'PHP extension: %s', 'tradesheet-studio' ), $extension ),
				'ok'     => extension_loaded( $extension ),
				'value'  => extension_loaded( $extension ) ? __( 'loaded', 'tradesheet-studio' ) : __( 'missing', 'tradesheet-studio' ),
				'advice' => __( 'mbstring and dom are required; zip and gd enable bundled ZIPs and image handling.', 'tradesheet-studio' ),
			);
		}

		$freeBytes = function_exists( 'disk_free_space' ) ? (int) @disk_free_space( dirname( $storageDir ) ) : -1;
		$checks[]  = array(
			'name'   => 'disk_space',
			'label'  => __( 'Free disk space', 'tradesheet-studio' ),
			'ok'     => -1 === $freeBytes || $freeBytes > 104857600,
			'value'  => -1 === $freeBytes ? __( 'unknown', 'tradesheet-studio' ) : sprintf( '%.1f MB', $freeBytes / 1048576 ),
			'advice' => __( 'Large catalogs with images need headroom for PDF generation.', 'tradesheet-studio' ),
		);

		$metrics = $this->plugin->packets()->dashboardMetrics();
		$checks[] = array(
			'name'   => 'failed_jobs',
			'label'  => __( 'Failed background jobs', 'tradesheet-studio' ),
			'ok'     => 0 === (int) $metrics['failed_jobs'],
			'value'  => (string) (int) $metrics['failed_jobs'],
			'advice' => __( 'Failed jobs can be inspected and retried from the Jobs screen.', 'tradesheet-studio' ),
		);

		return new WP_REST_Response(
			array(
				'checks'         => $checks,
				'schema_version' => (string) get_option( 'tss_schema_version', '' ),
				'plugin_version' => TSS_VERSION,
			),
			200
		);
	}

	/**
	 * @param array<string,mixed> $event
	 * @return array<string,mixed>
	 */
	private function serializeEvent( array $event ): array {
		return array(
			'id'          => (int) $event['id'],
			'object_type' => (string) $event['object_type'],
			'object_id'   => (int) $event['object_id'],
			'action'      => (string) $event['action'],
			'actor_id'    => $event['actor_id'] ? (int) $event['actor_id'] : null,
			'details'     => json_decode( (string) ( $event['details'] ?? '' ), true ) ?: array(),
			'created_at'  => (string) $event['created_at'],
		);
	}
}
