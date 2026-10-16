<?php
/**
 * Protected buyer portal.
 *
 * Served at /tradesheet/packet/{REF}/ — accessible only through:
 *   1. A valid, non-revoked, non-expired high-entropy token (?t=...), or
 *   2. The packet's customer being logged in (ownership check), or
 *   3. A merchant with manage_woocommerce.
 *
 * Anti-enumeration: missing packets and denied access both return a generic
 * 404-style response. All responses are no-cache, no-index, no-referrer.
 * State-changing actions are rate limited; CSV uploads are size-limited and
 * fully re-validated server-side.
 *
 * @package TradeSheetStudio\Buyers
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Buyers;

use TradeSheetStudio\Core\Plugin;
use TradeSheetStudio\Packets\PacketStateMachine;
use TradeSheetStudio\Security\RateLimiter;
use TradeSheetStudio\Support\Utils;
use TradeSheetStudio\Support\ValidationException;

final class BuyerPortal {

	private const MAX_CSV_BYTES = 2097152; // 2 MiB.

	public function __construct( private Plugin $plugin ) {
	}

	public function register(): void {
		add_action(
			'init',
			function (): void {
				add_rewrite_rule( '^tradesheet/packet/([A-Za-z0-9\-]+)/?$', 'index.php?tss_packet_ref=$matches[1]', 'top' );
			}
		);

		add_filter(
			'query_vars',
			static function ( array $vars ): array {
				$vars[] = 'tss_packet_ref';

				return $vars;
			}
		);

		add_action( 'template_redirect', array( $this, 'handle' ) );
	}

	public function handle(): void {
		$ref = strtoupper( trim( (string) get_query_var( 'tss_packet_ref' ) ) );

		if ( '' === $ref ) {
			return;
		}

		$packet = $this->plugin->packets()->getByRef( $ref );

		if ( null === $packet ) {
			$this->deny();

			return;
		}

		$token = '';
		if ( isset( $_GET['t'] ) && is_string( $_GET['t'] ) ) {
			$token = trim( $_GET['t'] );
		} elseif ( isset( $_POST['t'] ) && is_string( $_POST['t'] ) ) {
			$token = trim( $_POST['t'] );
		}

		$mode = $this->plugin->buyerAccess()->accessFor( $packet, $token );

		if ( null === $mode ) {
			$this->deny();

			return;
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: no-referrer' );

		$packetId = (int) $packet['id'];

		// ---------------------------------------------------------- //
		// Document downloads (authorization already verified above).
		// ---------------------------------------------------------- //
		if ( isset( $_GET['download'] ) && is_string( $_GET['download'] ) ) {
			$this->handleDownload( $packet, sanitize_key( $_GET['download'] ) );

			return;
		}

		// ---------------------------------------------------------- //
		// State-changing POST actions.
		// ---------------------------------------------------------- //
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) && isset( $_POST['tss_action'] ) && is_string( $_POST['tss_action'] ) ) {
			$this->handleAction( $packet, $token, $mode );

			return; // handleAction always redirects or renders.
		}

		$this->render( $packet, $token, $mode );
	}

	private function handleAction( array $packet, string $token, string $mode ): void {
		$packetId = (int) $packet['id'];

		// CSRF: token possession is the bearer capability for token access;
		// logged-in owner/merchant requests require the WordPress nonce.
		if ( 'token' !== $mode ) {
			$nonce = isset( $_POST['tss_nonce'] ) && is_string( $_POST['tss_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['tss_nonce'] ) ) : '';

			if ( false === wp_verify_nonce( $nonce, 'tss_buyer_' . $packetId ) ) {
				$this->redirect( $packet, $token, 'security' );
			}
		}

		$action  = sanitize_key( (string) $_POST['tss_action'] );
		$limitKey = $mode === 'token' ? hash( 'sha256', $token ) : 'user-' . get_current_user_id();

		switch ( $action ) {
			case 'submit_quote':
				if ( ! RateLimiter::allow( 'submit|' . $limitKey, 10, HOUR_IN_SECONDS ) ) {
					$this->redirect( $packet, $token, 'rate_limited' );
				}

				$quantities = $this->readQuantities();
				$notes      = isset( $_POST['tss_notes'] ) && is_string( $_POST['tss_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tss_notes'] ) ) : '';

				try {
					$this->plugin->quotes()->createRequest( $packetId, $quantities, $notes );
					$this->redirect( $packet, $token, 'quote_submitted' );
				} catch ( ValidationException $e ) {
					$this->render( $packet, $token, $mode, array( 'error' => $e->getMessage() ) );
				}
				break;

			case 'upload_csv':
				if ( ! RateLimiter::allow( 'upload|' . $limitKey, 10, HOUR_IN_SECONDS ) ) {
					$this->redirect( $packet, $token, 'rate_limited' );
				}

				$result = $this->parseUpload( (int) $packet['id'] );

				if ( '' !== (string) $result['error'] ) {
					$this->render( $packet, $token, $mode, array( 'error' => (string) $result['error'] ) );
				}

				$this->redirect( $packet, $token, 'csv_preview', array( 'tss_preview' => $this->storePreview( $limitKey, $packetId, (array) $result['rows'] ) ) );
				break;

			case 'confirm_csv':
				if ( ! RateLimiter::allow( 'submit|' . $limitKey, 10, HOUR_IN_SECONDS ) ) {
					$this->redirect( $packet, $token, 'rate_limited' );
				}

				$rows = $this->loadPreview( $limitKey, $packetId );

				if ( null === $rows ) {
					$this->redirect( $packet, $token, 'csv_expired' );
				}

				$quantities = array();
				foreach ( $rows as $row ) {
					if ( (int) ( $row['qty'] ?? 0 ) > 0 ) {
						$quantities[] = array(
							'line_id' => (int) ( $row['line_id'] ?? 0 ),
							'qty'     => (int) $row['qty'],
						);
					}
				}

				try {
					$this->plugin->quotes()->createRequest( $packetId, $quantities, '' );
					$this->deletePreview( $limitKey, $packetId );
					$this->redirect( $packet, $token, 'quote_submitted' );
				} catch ( ValidationException $e ) {
					$this->render( $packet, $token, $mode, array( 'error' => $e->getMessage() ) );
				}
				break;

			case 'accept_quote':
				if ( ! RateLimiter::allow( 'accept|' . $limitKey, 20, HOUR_IN_SECONDS ) ) {
					$this->redirect( $packet, $token, 'rate_limited' );
				}

				$quoteId = isset( $_POST['tss_quote_id'] ) ? absint( $_POST['tss_quote_id'] ) : 0;
				$quote   = $this->plugin->quotes()->get( $quoteId );

				if ( null === $quote || (int) $quote['packet_id'] !== $packetId ) {
					$this->redirect( $packet, $token, 'invalid_quote' );
				}

				try {
					$this->plugin->quotes()->accept( $quoteId, $this->plugin->actorId() );

					$converted = $this->plugin->orders()->convert( $quoteId, $this->plugin->actorId() );

					if ( ! empty( $converted['pending'] ) ) {
						$this->redirect( $packet, $token, 'conversion_pending' );
					}

					$this->redirect( $packet, $token, 'order_created', array( 'tss_order' => (string) ( $converted['order_id'] ?? '' ) ) );
				} catch ( ValidationException $e ) {
					$this->render( $packet, $token, $mode, array( 'error' => $e->getMessage() ) );
				} catch ( \Throwable $e ) {
					$this->render( $packet, $token, $mode, array( 'error' => $e->getMessage() ) );
				}
				break;

			default:
				$this->redirect( $packet, $token, 'invalid_action' );
		}
	}

	private function handleDownload( array $packet, string $type ): void {
		$version = $this->plugin->packets()->currentVersion( (int) $packet['id'] );

		if ( null === $version ) {
			$this->deny();

			return;
		}

		foreach ( $this->plugin->documents()->documentsForVersion( (int) $version['id'] ) as $doc ) {
			if ( (string) $doc['type'] === $type ) {
				$this->plugin->documents()->stream( (int) $doc['id'] );
			}
		}

		$this->deny();
	}

	/**
	 * @return list<array{line_id:int,qty:int}>
	 */
	private function readQuantities(): array {
		$raw = isset( $_POST['tss_qty'] ) && is_array( $_POST['tss_qty'] ) ? wp_unslash( $_POST['tss_qty'] ) : array();

		$quantities = array();

		foreach ( $raw as $lineId => $qty ) {
			$lineId = absint( $lineId );

			if ( is_numeric( $qty ) ) {
				$quantities[] = array(
					'line_id' => $lineId,
					'qty'     => max( 0, min( 100000, (int) $qty ) ),
				);
			}
		}

		return $quantities;
	}

	/**
	 * @return array{error:string,rows:list<array<string,mixed>>}
	 */
	private function parseUpload( int $packetId ): array {
		if ( empty( $_FILES['tss_csv'] ) || ! is_array( $_FILES['tss_csv'] ) ) {
			return array( 'error' => __( 'No file was uploaded.', 'tradesheet-studio' ), 'rows' => array() );
		}

		$file = $_FILES['tss_csv'];

		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return array( 'error' => __( 'The upload failed. Please try again.', 'tradesheet-studio' ), 'rows' => array() );
		}

		if ( (int) $file['size'] > self::MAX_CSV_BYTES || (int) $file['size'] < 12 ) {
			return array( 'error' => __( 'The file must be a CSV between 12 bytes and 2 MB.', 'tradesheet-studio' ), 'rows' => array() );
		}

		$content = is_uploaded_file( (string) $file['tmp_name'] ) ? file_get_contents( (string) $file['tmp_name'] ) : false;

		if ( false === $content || strlen( $content ) > self::MAX_CSV_BYTES ) {
			return array( 'error' => __( 'The uploaded file could not be read.', 'tradesheet-studio' ), 'rows' => array() );
		}

		$parse = $this->plugin->csv()->parse( $content );

		if ( ! $parse['ok'] ) {
			$messages = array_map(
				static function ( array $error ): string {
					return (string) $error['message'];
				},
				$parse['errors']
			);

			return array( 'error' => implode( ' ', $messages ), 'rows' => array() );
		}

		$version = $this->plugin->packets()->currentVersion( $packetId );

		if ( null === $version ) {
			return array( 'error' => __( 'This packet has no published version.', 'tradesheet-studio' ), 'rows' => array() );
		}

		$validation = $this->plugin->csv()->validateAgainstVersion( $parse['rows'], (int) $version['id'] );

		if ( $validation['errors'] !== array() ) {
			$messages = array_map(
				static function ( array $error ): string {
					return (string) $error['message'];
				},
				$validation['errors']
			);

			return array( 'error' => implode( ' ', $messages ), 'rows' => array() );
		}

		return array( 'error' => '', 'rows' => $validation['rows'] );
	}

	private function previewKey( string $limitKey, int $packetId ): string {
		return 'tss_csvp_' . md5( $limitKey . '|' . $packetId );
	}

	private function storePreview( string $limitKey, int $packetId, array $rows ): string {
		$key = $this->previewKey( $limitKey, $packetId );

		set_transient( $key, $rows, 30 * MINUTE_IN_SECONDS );

		return $key;
	}

	/**
	 * @return list<array<string,mixed>>|null
	 */
	private function loadPreview( string $limitKey, int $packetId ): ?array {
		$rows = get_transient( $this->previewKey( $limitKey, $packetId ) );

		return is_array( $rows ) ? $rows : null;
	}

	private function deletePreview( string $limitKey, int $packetId ): void {
		delete_transient( $this->previewKey( $limitKey, $packetId ) );
	}

	private function redirect( array $packet, string $token, string $message, array $extra = array() ): void {
		$args = array( 'tss_msg' => $message );

		if ( '' !== $token ) {
			$args['t'] = $token;
		}

		foreach ( $extra as $key => $value ) {
			$args[ sanitize_key( (string) $key ) ] = rawurlencode( (string) $value );
		}

		wp_safe_redirect( add_query_arg( $args, home_url( '/tradesheet/packet/' . rawurlencode( (string) $packet['ref'] ) . '/' ) ) );
		exit;
	}

	private function render( array $packet, string $token, string $mode, array $overrides = array() ): void {
		$packetId = (int) $packet['id'];
		$version  = $this->plugin->packets()->currentVersion( $packetId );

		if ( null === $version ) {
			$this->renderMessage( __( 'This offer has not been published yet.', 'tradesheet-studio' ) );

			return;
		}

		$status = (string) $packet['status'];

		if ( ! PacketStateMachine::isLive( $status ) ) {
			switch ( $status ) {
				case PacketStateMachine::EXPIRED:
					$message = __( 'This offer has expired. Ask the merchant for a current version.', 'tradesheet-studio' );
					break;
				case PacketStateMachine::REVOKED:
					$message = __( 'This offer has been revoked by the merchant.', 'tradesheet-studio' );
					break;
				case PacketStateMachine::ORDERED:
					$message = __( 'This offer has already been converted into an order.', 'tradesheet-studio' );
					break;
				default:
					$message = __( 'This offer is no longer active.', 'tradesheet-studio' );
			}

			$this->renderMessage( $message );

			return;
		}

		$now = Utils::nowDb();
		$expired = null !== $version['expires_at'] && (string) $version['expires_at'] < $now;

		if ( $expired ) {
			$this->renderMessage( __( 'This offer version has passed its validity date.', 'tradesheet-studio' ) );

			return;
		}

		$lines = array();
		foreach ( $this->plugin->packets()->versionLines( (int) $version['id'] ) as $line ) {
			$lines[] = array(
				'line_id'          => (int) $line['id'],
				'row_id'           => 'L' . (string) $line['id'],
				'sku'              => (string) $line['sku'],
				'name'             => (string) $line['name'],
				'variation_summary' => (string) ( $line['variation_summary'] ?? '' ),
				'description'      => (string) ( $line['description'] ?? '' ),
				'qty_min'          => (int) $line['qty_min'],
				'qty_step'         => (int) $line['qty_step'],
				'unit_price'       => (string) $line['unit_price'],
				'qty_breaks'       => json_decode( (string) ( $line['qty_breaks'] ?? '' ), true ) ?: array(),
			);
		}

		$documents = array();
		foreach ( $this->plugin->documents()->documentsForVersion( (int) $version['id'] ) as $doc ) {
			$documents[] = array(
				'type' => (string) $doc['type'],
				'size' => (int) $doc['size_bytes'],
			);
		}

		$snapshot = json_decode( (string) $version['snapshot'], true );
		$snapshot = is_array( $snapshot ) ? $snapshot : array();

		$quote = $this->plugin->quotes()->latestForPacket( $packetId );

		$quoteData = null;
		if ( null !== $quote ) {
			$quoteLines = array();
			$total      = '0.0000';

			foreach ( $this->plugin->quotes()->lines( (int) $quote['id'] ) as $line ) {
				$lineTotal = \TradeSheetStudio\Support\Money::mul( (string) $line['unit_price'], (int) $line['qty'] );
				$total     = \TradeSheetStudio\Support\Money::add( $total, $lineTotal );

				$quoteLines[] = array(
					'row_id'   => 'L' . (string) $line['packet_line_id'],
					'name'     => (string) $line['name'],
					'qty'      => (int) $line['qty'],
					'unit_price' => (string) $line['unit_price'],
					'line_total' => $lineTotal,
					'merchant_note' => (string) ( $line['merchant_note'] ?? '' ),
				);
			}

			$report = json_decode( (string) ( $quote['discrepancy_report'] ?? '' ), true );

			$quoteData = array(
				'id'          => (int) $quote['id'],
				'ref'         => (string) $quote['ref'],
				'revision_no' => (int) $quote['revision_no'],
				'status'      => (string) $quote['status'],
				'expires_at'  => (string) ( $quote['expires_at'] ?? '' ),
				'notes_merchant' => (string) ( $quote['notes_merchant'] ?? '' ),
				'notes_buyer' => (string) ( $quote['notes_buyer'] ?? '' ),
				'lines'       => $quoteLines,
				'total'       => $total,
				'report'      => is_array( $report ) ? $report : array(),
				'order_id'    => $quote['converted_order_id'] ? (int) $quote['converted_order_id'] : null,
			);
		}

		$customerName = '';
		$user         = get_userdata( (int) $packet['customer_id'] );
		if ( false !== $user ) {
			$billingCompany = (string) get_user_meta( (int) $packet['customer_id'], 'billing_company', true );
			$customerName   = trim( $billingCompany . ( '' !== $billingCompany ? ' — ' : '' ) . $user->display_name );
		}

		$currency = (string) ( $snapshot['packet']['currency'] ?? $packet['currency'] );
		$symbol   = $currency;
		if ( function_exists( 'get_woocommerce_currency_symbol' ) && '' !== $currency ) {
			$symbol = html_entity_decode( get_woocommerce_currency_symbol( $currency ), ENT_QUOTES, 'UTF-8' );
		}

		$csvPreview = null;
		$previewKey = isset( $_GET['tss_preview'] ) && is_string( $_GET['tss_preview'] ) ? sanitize_key( $_GET['tss_preview'] ) : '';
		if ( '' !== $previewKey && 0 === strpos( $previewKey, 'tss_csvp_' ) ) {
			$rows = get_transient( $previewKey );
			if ( is_array( $rows ) ) {
				$csvPreview = $rows;
			}
		}

		$data = array(
			'packet'       => array(
				'ref'         => (string) $packet['ref'],
				'title'       => (string) $packet['title'],
				'status'      => $status,
				'customer'    => $customerName,
				'valid_until' => (string) ( $version['expires_at'] ?? '' ),
				'issued_at'   => (string) ( $version['issued_at'] ?? '' ),
				'version_no'  => (int) $version['version_no'],
				'currency'    => $currency,
				'symbol'      => $symbol,
				'checksum'    => substr( (string) $version['content_checksum'], 0, 12 ),
			),
			'company'      => $this->plugin->settings()->companyPresentation(),
			'prices_include_tax' => (bool) ( $snapshot['tax_context']['prices_include_tax'] ?? false ),
			'lines'        => $lines,
			'documents'    => $documents,
			'quote'        => $quoteData,
			'mode'         => $mode,
			'token'        => $token,
			'nonce'        => 'token' === $mode ? '' : wp_create_nonce( 'tss_buyer_' . $packetId ),
			'action_url'   => home_url( '/tradesheet/packet/' . rawurlencode( (string) $packet['ref'] ) . '/' ) . ( '' !== $token ? '?t=' . rawurlencode( $token ) : '' ),
			'message'      => $this->flashMessage(),
			'error'        => (string) ( $overrides['error'] ?? '' ),
			'csv_preview'  => $csvPreview,
			'assets_url'   => TSS_PLUGIN_URL . 'assets/',
			'order_id'     => isset( $_GET['tss_order'] ) && is_string( $_GET['tss_order'] ) ? absint( $_GET['tss_order'] ) : 0,
		);

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template include with explicit data array.
		include TSS_PLUGIN_DIR . 'templates/buyer/portal.php';
		exit;
	}

	private function flashMessage(): string {
		$code = isset( $_GET['tss_msg'] ) && is_string( $_GET['tss_msg'] ) ? sanitize_key( $_GET['tss_msg'] ) : '';

		$map = array(
			'quote_submitted'    => __( 'Your quotation request was submitted. The merchant will review it and you will be notified.', 'tradesheet-studio' ),
			'rate_limited'       => __( 'Too many attempts. Please wait a while before trying again.', 'tradesheet-studio' ),
			'csv_expired'        => __( 'Your CSV preview expired. Please upload the file again.', 'tradesheet-studio' ),
			'csv_preview'        => __( 'File validated. Review the quantities below and confirm to submit your request.', 'tradesheet-studio' ),
			'conversion_pending' => __( 'Your order is being created. Refresh this page in a moment.', 'tradesheet-studio' ),
			'invalid_action'     => __( 'Unknown action.', 'tradesheet-studio' ),
			'invalid_quote'      => __( 'That quotation does not belong to this offer.', 'tradesheet-studio' ),
			'security'           => __( 'Security check failed. Please reload the page and try again.', 'tradesheet-studio' ),
		);

		return $map[ $code ] ?? '';
	}

	private function renderMessage( string $message ): void {
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );

		printf(
			'<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>%1$s</title><style>body{font-family:system-ui,sans-serif;background:#f4f6f5;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}main{max-width:420px;padding:32px;background:#fff;border:1px solid #dfe5e2;border-radius:10px;text-align:center}h1{font-size:16px;color:#0f5e57;margin:0 0 8px}p{font-size:14px;color:#4b555c;margin:0}</style></head><body><main><h1>%1$s</h1><p>%2$s</p></main></body></html>',
			esc_html__( 'TradeSheet Studio', 'tradesheet-studio' ),
			esc_html( $message )
		);
		exit;
	}

	private function deny(): void {
		status_header( 404 );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );

		$this->renderMessage( __( 'Page not found.', 'tradesheet-studio' ) );
	}
}
