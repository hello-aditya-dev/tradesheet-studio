<?php
/**
 * Preflight validation executed before a packet can be published.
 *
 * Findings are classified as blocking errors (publication refused) or
 * actionable warnings (publication allowed, findings recorded in the audit
 * event). The validator is re-run server-side at publication time; the UI
 * merely surfaces the same report earlier.
 *
 * @package TradeSheetStudio\Packets
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Packets;

use TradeSheetStudio\Pricing\PriceBookRepository;
use TradeSheetStudio\Products\ProductAdapter;
use TradeSheetStudio\Support\Money;

final class PreflightValidator {

	public function __construct(
		private ProductAdapter $products,
		private PriceBookRepository $books
	) {
	}

	/**
	 * @param array<string,mixed> $packet
	 * @param list<array<string,mixed>> $lines
	 * @return array{errors:list<array<string,mixed>>,warnings:list<array<string,mixed>>}
	 */
	public function validate( array $packet, array $lines ): array {
		$errors   = array();
		$warnings = array();

		$customerId = (int) ( $packet['customer_id'] ?? 0 );
		if ( $customerId < 1 || false === get_userdata( $customerId ) ) {
			$errors[] = array(
				'code'    => 'CUSTOMER_MISSING',
				'message' => __( 'The selected buyer account no longer exists.', 'tradesheet-studio' ),
			);
		}

		$storeCurrency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
		$currency      = strtoupper( (string) ( $packet['currency'] ?? '' ) );

		if ( '' === $storeCurrency ) {
			$errors[] = array(
				'code'    => 'CURRENCY_CONTEXT_MISSING',
				'message' => __( 'The store currency could not be determined.', 'tradesheet-studio' ),
			);
		} elseif ( '' !== $currency && $currency !== $storeCurrency ) {
			$errors[] = array(
				'code'    => 'CURRENCY_MISMATCH',
				'message' => sprintf(
					/* translators: 1: packet currency, 2: store currency. */
					__( 'The packet currency (%1$s) differs from the store currency (%2$s). Multicurrency is not supported in version 1.', 'tradesheet-studio' ),
					$currency,
					$storeCurrency
				),
			);
		}

		if ( count( $lines ) === 0 ) {
			$errors[] = array(
				'code'    => 'EMPTY_PACKET',
				'message' => __( 'The packet has no product lines.', 'tradesheet-studio' ),
			);
		}

		$validUntil = (string) ( $packet['valid_until'] ?? '' );
		if ( '' !== $validUntil && strtotime( $validUntil ) <= current_time( 'timestamp' ) ) {
			$errors[] = array(
				'code'    => 'VALIDITY_INVALID',
				'message' => __( 'The offer validity date lies in the past.', 'tradesheet-studio' ),
			);
		}

		$pricebookId = (int) ( $packet['pricebook_id'] ?? 0 );
		if ( $pricebookId > 0 && ! $this->books->isUsable( $pricebookId ) ) {
			$warnings[] = array(
				'code'    => 'PRICEBOOK_UNUSABLE',
				'message' => __( 'The assigned price book is inactive or outside its validity window.', 'tradesheet-studio' ),
			);
		}

		$seen = array();

		foreach ( $lines as $line ) {
			$lineId      = (int) ( $line['id'] ?? 0 );
			$productId   = (int) ( $line['product_id'] ?? 0 );
			$variationId = (int) ( $line['variation_id'] ?? 0 );

			$product = $this->products->get( $variationId > 0 ? $variationId : $productId );

			if ( null === $product ) {
				$errors[] = array(
					'code'    => 'PRODUCT_MISSING',
					'line_id' => $lineId,
					'message' => sprintf(
						/* translators: %d: product id. */
						__( 'Product #%d no longer exists in the store.', 'tradesheet-studio' ),
						$productId
					),
				);
				continue;
			}

			if ( ! $this->products->supportedType( $product ) ) {
				$errors[] = array(
					'code'    => 'UNSUPPORTED_PRODUCT_TYPE',
					'line_id' => $lineId,
					'message' => sprintf(
						/* translators: %s: product type. */
						__( 'Product type "%s" is not supported in version 1 (simple and variation products only).', 'tradesheet-studio' ),
						(string) $product->get_type()
					),
				);
				continue;
			}

			if ( $variationId > 0 && ! $product instanceof \WC_Product_Variation ) {
				$errors[] = array(
					'code'    => 'VARIATION_MISSING',
					'line_id' => $lineId,
					'message' => sprintf(
						/* translators: %d: variation id. */
						__( 'Variation #%d no longer exists for this product.', 'tradesheet-studio' ),
						$variationId
					),
				);
				continue;
			}

			$unitPrice = (string) ( $line['unit_price'] ?? '' );
			if ( ! Money::isValid( $unitPrice ) ) {
				$errors[] = array(
					'code'    => 'PRICE_INVALID',
					'line_id' => $lineId,
					'message' => __( 'A line price is malformed.', 'tradesheet-studio' ),
				);
			} elseif ( Money::cmp( $unitPrice, '999999999.9999' ) > 0 ) {
				$errors[] = array(
					'code'    => 'PRICE_OUT_OF_RANGE',
					'line_id' => $lineId,
					'message' => __( 'A line price exceeds the supported maximum.', 'tradesheet-studio' ),
				);
			} elseif ( Money::cmp( $unitPrice, '0.0000' ) === 0 ) {
				$warnings[] = array(
					'code'    => 'PRICE_ZERO',
					'line_id' => $lineId,
					'message' => __( 'A line has a zero price; verify this is intentional.', 'tradesheet-studio' ),
				);
			}

			$qtyMin = (int) ( $line['qty_min'] ?? 1 );
			$qtyStep = (int) ( $line['qty_step'] ?? 1 );
			if ( $qtyMin < 1 || $qtyMin > Money::MAX_QTY || $qtyStep < 1 || $qtyStep > 10000 ) {
				$errors[] = array(
					'code'    => 'QTY_RULE_INVALID',
					'line_id' => $lineId,
					'message' => __( 'A quantity rule (minimum or step) is invalid.', 'tradesheet-studio' ),
				);
			}

			$sku = (string) ( $line['sku'] ?? '' );
			if ( '' === $sku ) {
				$warnings[] = array(
					'code'    => 'SKU_MISSING',
					'line_id' => $lineId,
					'message' => __( 'A line has no SKU; buyers will reference it by row id.', 'tradesheet-studio' ),
				);
			} elseif ( (string) $product->get_sku() !== $sku ) {
				$warnings[] = array(
					'code'    => 'SKU_CHANGED',
					'line_id' => $lineId,
					'message' => sprintf(
						/* translators: 1: line sku, 2: current sku. */
						__( 'A line SKU ("%1$s") differs from the store ("%2$s"). The snapshot will keep the line SKU.', 'tradesheet-studio' ),
						$sku,
						(string) $product->get_sku()
					),
				);
			}

			$availability = $this->products->availability( $productId, $variationId );
			if ( ! $availability['available'] ) {
				$errors[] = array(
					'code'    => 'PRODUCT_UNAVAILABLE',
					'line_id' => $lineId,
					'message' => __( 'A product is unpublished or not purchasable.', 'tradesheet-studio' ),
				);
			} elseif ( 'outofstock' === $availability['stock'] ) {
				$warnings[] = array(
					'code'    => 'OUT_OF_STOCK',
					'line_id' => $lineId,
					'message' => __( 'A product is out of stock; the offer can still be issued with a lead time.', 'tradesheet-studio' ),
				);
			}

			if ( empty( $line['image_id'] ) ) {
				$summary = $this->products->summary( $productId, $variationId );
				if ( empty( $summary['image_id'] ) ) {
					$warnings[] = array(
						'code'    => 'IMAGE_MISSING',
						'line_id' => $lineId,
						'message' => __( 'A product has no image; documents will use the fallback placeholder.', 'tradesheet-studio' ),
					);
				}
			}

			$key = $productId . ':' . $variationId;
			if ( isset( $seen[ $key ] ) ) {
				$warnings[] = array(
					'code'    => 'DUPLICATE_LINE',
					'line_id' => $lineId,
					'message' => __( 'The same product/variation appears more than once in this packet.', 'tradesheet-studio' ),
				);
			}
			$seen[ $key ] = true;
		}

		return array(
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}
}
