<?php
/**
 * PDF catalog renderer (Dompdf).
 *
 * SECURITY:
 *  - Remote resource loading is DISABLED (isRemoteEnabled = false): no SSRF,
 *    no remote image fetching during rendering.
 *  - Images and the merchant logo are embedded as data URIs built from
 *    local attachment files (size- and mime-bounded), never from URLs.
 *  - Product text is plain text (wp_strip_all_tags) and re-escaped in the
 *    templates; templates render NO untrusted HTML.
 *  - Inline PHP is enabled solely for page numbering scripts that live in
 *    the plugin's own templates; the merchant-supplied footer line is
 *    quote-escaped before interpolation.
 *
 * @package TradeSheetStudio\Documents
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Documents;

use TradeSheetStudio\Packets\PacketRepository;
use TradeSheetStudio\Settings\MerchantSettingsService;

final class PdfRenderer {

	public const TEMPLATES = array( 'trade_table', 'visual_catalog', 'technical_sheet' );

	public function __construct(
		private PacketRepository $packets,
		private MerchantSettingsService $settings
	) {
	}

	public function isAvailable(): bool {
		if ( class_exists( \Dompdf\Dompdf::class ) ) {
			return true;
		}

		$autoloader = TSS_PLUGIN_DIR . 'vendor/autoload.php';

		return is_file( $autoloader );
	}

	/**
	 * Render a packet version to PDF bytes.
	 *
	 * @param string $template trade_table | visual_catalog | technical_sheet
	 * @return string Binary PDF content.
	 */
	public function render( int $versionId, string $template ): string {
		if ( ! in_array( $template, self::TEMPLATES, true ) ) {
			$template = 'trade_table';
		}

		$autoloader = TSS_PLUGIN_DIR . 'vendor/autoload.php';
		if ( ! class_exists( \Dompdf\Dompdf::class ) && is_file( $autoloader ) ) {
			require_once $autoloader;
		}

		if ( ! class_exists( \Dompdf\Dompdf::class ) ) {
			throw new \RuntimeException( esc_html__( 'The bundled PDF renderer is missing. Reinstall the plugin.', 'tradesheet-studio' ) );
		}

		$version = $this->packets->version( $versionId );
		if ( null === $version ) {
			throw new \RuntimeException( esc_html__( 'Packet version not found.', 'tradesheet-studio' ) );
		}

		$packet   = $this->packets->get( (int) $version['packet_id'] );
		$snapshot = json_decode( (string) $version['snapshot'], true );
		$snapshot = is_array( $snapshot ) ? $snapshot : array();

		$data = $this->buildTemplateData( $version, null === $packet ? array() : $packet, $snapshot, $template );

		// Render the template HTML (templates escape all dynamic output).
		ob_start();
		include TSS_PLUGIN_DIR . 'templates/pdf/' . $template . '.php';
		$html = (string) ob_get_clean();

		$options = array(
			'isRemoteEnabled'        => false,   // No network access during rendering.
			'defaultFont'            => 'dejavu sans',
			'isPhpEnabled'           => true,    // Page-number scripts in our own templates only.
			'isHtml5ParserEnabled'   => true,
			'isFontSubsettingEnabled' => true,
			'tempDir'                => get_temp_dir(),
		);

		$dompdf = new \Dompdf\Dompdf( $options );

		$size        = 'letter' === (string) $this->settings->get( 'doc_size' ) ? 'letter' : 'a4';
		$orientation = ( 'trade_table' === $template ) ? 'landscape' : 'portrait';

		$dompdf->setPaper( $size, $orientation );
		$dompdf->loadHtml( $html, 'UTF-8' );

		@set_time_limit( 120 );

		$dompdf->render();

		return (string) $dompdf->output();
	}

	/**
	 * @param array<string,mixed> $version
	 * @param array<string,mixed> $packet
	 * @param array<string,mixed> $snapshot
	 * @return array<string,mixed>
	 */
	private function buildTemplateData( array $version, array $packet, array $snapshot, string $template ): array {
		$company    = is_array( $snapshot['company'] ?? null ) ? $snapshot['company'] : array();
		$snapPacket = is_array( $snapshot['packet'] ?? null ) ? $snapshot['packet'] : array();
		$taxContext = is_array( $snapshot['tax_context'] ?? null ) ? $snapshot['tax_context'] : array();

		$currency = (string) ( $snapPacket['currency'] ?? ( $packet['currency'] ?? '' ) );
		$symbol   = $currency;
		if ( function_exists( 'get_woocommerce_currency_symbol' ) && '' !== $currency ) {
			$symbol = html_entity_decode( get_woocommerce_currency_symbol( $currency ), ENT_QUOTES, 'UTF-8' );
		}

		$buyer = '';
		$buyerId = (int) ( $snapPacket['customer_id'] ?? 0 );
		if ( $buyerId > 0 && function_exists( 'get_userdata' ) ) {
			$user = get_userdata( $buyerId );
			if ( false !== $user && isset( $user->display_name ) ) {
				$billingCompany = (string) get_user_meta( $buyerId, 'billing_company', true );
				$buyer = trim( $billingCompany . ( '' !== $billingCompany ? ' — ' : '' ) . $user->display_name );
			}
		}

		$lines = array();
		foreach ( ( is_array( $snapshot['lines'] ?? null ) ? $snapshot['lines'] : array() ) as $line ) {
			$lines[] = array(
				'row_id'            => (string) ( $line['row_id'] ?? '' ),
				'sku'               => (string) ( $line['sku'] ?? '' ),
				'name'              => (string) ( $line['name'] ?? '' ),
				'variation_summary' => (string) ( $line['variation_summary'] ?? '' ),
				'description'       => (string) ( $line['description'] ?? '' ),
				'qty_min'           => (int) ( $line['qty_min'] ?? 1 ),
				'qty_step'          => (int) ( $line['qty_step'] ?? 1 ),
				'unit_price'        => (string) ( $line['unit_price'] ?? '0.0000' ),
				'qty_breaks'        => is_array( $line['qty_breaks'] ?? null ) ? $line['qty_breaks'] : array(),
				'attributes'        => is_array( $line['attributes'] ?? null ) ? $line['attributes'] : array(),
				'image'             => $this->imageDataUri( (int) ( $line['image_id'] ?? 0 ) ),
			);
		}

		return array(
			'template'            => $template,
			'ref'                 => (string) ( $snapPacket['ref'] ?? ( $packet['ref'] ?? '' ) ),
			'title'               => (string) ( $snapPacket['title'] ?? ( $packet['title'] ?? '' ) ),
			'version_no'          => (int) ( $snapPacket['version_no'] ?? ( $version['version_no'] ?? 1 ) ),
			'issued_at'           => (string) ( $snapPacket['issued_at'] ?? ( $version['issued_at'] ?? '' ) ),
			'expires_at'          => (string) ( $snapPacket['valid_until'] ?? ( $version['expires_at'] ?? '' ) ),
			'currency'            => $currency,
			'currency_symbol'     => $symbol,
			'prices_include_tax'  => (bool) ( $taxContext['prices_include_tax'] ?? false ),
			'buyer'               => $buyer,
			'company'             => array(
				'name'    => (string) ( $company['name'] ?? '' ),
				'address' => (string) ( $company['address'] ?? '' ),
				'email'   => (string) ( $company['email'] ?? '' ),
				'phone'   => (string) ( $company['phone'] ?? '' ),
				'footer'  => (string) ( $company['footer'] ?? '' ),
				'logo'    => $this->imageDataUri( (int) ( $company['logo_id'] ?? 0 ) ),
			),
			'terms'               => is_array( $snapPacket['terms'] ?? null ) ? $snapPacket['terms'] : array(),
			'lines'               => $lines,
		);
	}

	/**
	 * Local attachment → bounded data URI (no URLs, no remote fetching).
	 */
	private function imageDataUri( int $attachmentId ): string {
		if ( $attachmentId < 1 || ! function_exists( 'wp_get_attachment_image_src' ) ) {
			return '';
		}

		$mime = (string) get_post_mime_type( $attachmentId );
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/gif' ), true ) ) {
			return '';
		}

		$src = wp_get_attachment_image_src( $attachmentId, 'medium' );
		if ( ! is_array( $src ) || empty( $src[0] ) ) {
			return '';
		}

		$uploads = wp_get_upload_dir();
		$path    = str_replace( $uploads['baseurl'], $uploads['basedir'], (string) $src[0] );

		if ( ! is_file( $path ) ) {
			return '';
		}

		$size = (int) @filesize( $path );
		if ( $size < 1 || $size > 1572864 ) { // 1.5 MiB per image.
			return '';
		}

		$data = @file_get_contents( $path );
		if ( false === $data || '' === $data ) {
			return '';
		}

		return 'data:' . $mime . ';base64,' . base64_encode( $data );
	}
}
