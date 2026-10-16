<?php
/**
 * Quote integrity engine — the product's centerpiece.
 *
 * Compares an immutable packet snapshot's commercial terms against the LIVE
 * WooCommerce state at quotation time and produces a structured discrepancy
 * report. Nothing is silently substituted: blocking findings require a
 * merchant decision (honor / revise / reject) before buyer acceptance can
 * proceed.
 *
 * Discrepancy types: PRICE_CHANGED, PRODUCT_REMOVED, VARIATION_REMOVED,
 * SKU_CHANGED, PRODUCT_UNAVAILABLE, QUANTITY_RULE_VIOLATED,
 * TAX_CONTEXT_CHANGED, CURRENCY_MISMATCH, OFFER_EXPIRED,
 * INSUFFICIENT_STOCK (warning), QUOTE_SUPERSEDED (checked at acceptance).
 *
 * @package TradeSheetStudio\Quotes
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Quotes;

use TradeSheetStudio\Packets\PacketRepository;
use TradeSheetStudio\Packets\PacketStateMachine;
use TradeSheetStudio\Pricing\PricingEngine;
use TradeSheetStudio\Products\ProductAdapter;
use TradeSheetStudio\Support\Money;
use TradeSheetStudio\Support\Utils;

final class QuoteIntegrityEngine {

	public function __construct(
		private PacketRepository $packets,
		private ProductAdapter $products,
		private PricingEngine $pricing
	) {
	}

	/**
	 * @param array<string,mixed> $version
	 * @param list<array<string,mixed>> $quoteLines Each: product_id, variation_id, qty, unit_price, sku, row_id.
	 * @return array<string,mixed> Structured discrepancy report.
	 */
	public function check( array $version, array $quoteLines ): array {
		$items    = array();
		$packetId = (int) $version['packet_id'];
		$packet   = $this->packets->get( $packetId );

		if ( null === $packet ) {
			return $this->report( array(
				array(
					'type'     => 'PRODUCT_REMOVED',
					'severity' => 'blocking',
					'row_id'   => '',
					'message'  => __( 'The originating packet no longer exists.', 'tradesheet-studio' ),
				),
			) );
		}

		$now        = Utils::nowDb();
		$expiresAt  = (string) ( $version['expires_at'] ?? '' );
		$packetLive = PacketStateMachine::isLive( (string) $packet['status'] );

		if ( '' !== $expiresAt && $expiresAt < $now ) {
			$items[] = array(
				'type'     => 'OFFER_EXPIRED',
				'severity' => 'blocking',
				'row_id'   => '',
				'message'  => __( 'The offer version has passed its validity date.', 'tradesheet-studio' ),
			);
		} elseif ( ! $packetLive ) {
			$items[] = array(
				'type'     => 'OFFER_EXPIRED',
				'severity' => 'blocking',
				'row_id'   => '',
				'message'  => sprintf(
					/* translators: %s: packet state. */
					__( 'The packet is no longer active (state: %s).', 'tradesheet-studio' ),
					(string) $packet['status']
				),
			);
		}

		$snapshot  = json_decode( (string) ( $version['snapshot'] ?? '' ), true );
		$snapshot  = is_array( $snapshot ) ? $snapshot : array();
		$currency  = (string) ( $snapshot['packet']['currency'] ?? $packet['currency'] ?? '' );
		$storeCur  = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';

		if ( '' !== $storeCur && '' !== $currency && $currency !== $storeCur ) {
			$items[] = array(
				'type'     => 'CURRENCY_MISMATCH',
				'severity' => 'blocking',
				'row_id'   => '',
				'message'  => sprintf(
					/* translators: 1: quote currency, 2: store currency. */
					__( 'Store currency changed since the offer was issued (%1$s → %2$s).', 'tradesheet-studio' ),
					$currency,
					$storeCur
				),
			);
		}

		$taxHashStored = (string) ( $snapshot['tax_context']['hash'] ?? '' );
		if ( '' !== $taxHashStored && $taxHashStored !== $this->products->taxContext()['hash'] ) {
			$items[] = array(
				'type'     => 'TAX_CONTEXT_CHANGED',
				'severity' => 'warning',
				'row_id'   => '',
				'message'  => __( 'Store tax settings changed since the offer was issued; taxes will follow the store\'s current configuration.', 'tradesheet-studio' ),
			);
		}

		$customerId = (int) ( $packet['customer_id'] ?? 0 );

		foreach ( $quoteLines as $line ) {
			$productId   = (int) ( $line['product_id'] ?? 0 );
			$variationId = (int) ( $line['variation_id'] ?? 0 );
			$qty         = (int) ( $line['qty'] ?? 0 );
			$quotedPrice = (string) ( $line['unit_price'] ?? '0.0000' );
			$rowId       = (string) ( $line['row_id'] ?? '' );

			$product = $this->products->get( $variationId > 0 ? $variationId : $productId );

			if ( null === $product ) {
				$items[] = array(
					'type'     => 'PRODUCT_REMOVED',
					'severity' => 'blocking',
					'row_id'   => $rowId,
					'message'  => sprintf(
						/* translators: %s: row id. */
						__( 'Row %s: the product was removed from the store.', 'tradesheet-studio' ),
						$rowId
					),
				);
				continue;
			}

			if ( $variationId > 0 && ! $product instanceof \WC_Product_Variation ) {
				$items[] = array(
					'type'     => 'VARIATION_REMOVED',
					'severity' => 'blocking',
					'row_id'   => $rowId,
					'message'  => sprintf(
						/* translators: %s: row id. */
						__( 'Row %s: the product variation no longer exists.', 'tradesheet-studio' ),
						$rowId
					),
				);
				continue;
			}

			if ( 'publish' !== $product->get_status() || ! $product->is_purchasable() ) {
				$items[] = array(
					'type'     => 'PRODUCT_UNAVAILABLE',
					'severity' => 'blocking',
					'row_id'   => $rowId,
					'message'  => sprintf(
						/* translators: %s: row id. */
						__( 'Row %s: the product is unpublished or not purchasable.', 'tradesheet-studio' ),
						$rowId
					),
				);
				continue;
			}

			if ( '' !== (string) $line['sku'] && (string) $product->get_sku() !== (string) $line['sku'] ) {
				$items[] = array(
					'type'     => 'SKU_CHANGED',
					'severity' => 'warning',
					'row_id'   => $rowId,
					'message'  => sprintf(
						/* translators: 1: row id, 2: quoted sku, 3: current sku. */
						__( 'Row %1$s SKU changed ("%2$s" → "%3$s"); the quoted row keeps its snapshot SKU.', 'tradesheet-studio' ),
						$rowId,
						(string) $line['sku'],
						(string) $product->get_sku()
					),
				);
			}

			$availability = $this->products->availability( $productId, $variationId );
			if ( $availability['managed'] && is_int( $availability['qty'] ) && $availability['qty'] < $qty ) {
				$items[] = array(
					'type'     => 'INSUFFICIENT_STOCK',
					'severity' => 'warning',
					'row_id'   => $rowId,
					'message'  => sprintf(
						/* translators: 1: row id, 2: requested qty, 3: stock qty. */
						__( 'Row %1$s requests %2$d units but only %3$d are in stock (store stock policy applies at order time).', 'tradesheet-studio' ),
						$rowId,
						$qty,
						$availability['qty']
					),
				);
			}

			// Authoritative current price, resolved server-side for this buyer.
			$resolution = $this->pricing->resolveForCustomer( $customerId, $productId, $variationId, $qty );
			$current    = PricingEngine::effectiveUnitPrice( $resolution->unitPrice, array(), $qty );

			if ( Money::cmp( $current, $quotedPrice ) !== 0 ) {
				$delta = Money::sub( $current, $quotedPrice );

				$items[] = array(
					'type'     => 'PRICE_CHANGED',
					'severity' => 'warning',
					'row_id'   => $rowId,
					'message'  => sprintf(
						/* translators: 1: row id, 2: quoted price, 3: current price. */
						__( 'Row %1$s price changed (quoted %2$s → currently %3$s).', 'tradesheet-studio' ),
						$rowId,
						Money::format( $quotedPrice, 2, '.', ',' ),
						Money::format( $current, 2, '.', ',' )
					),
					'quoted'   => $quotedPrice,
					'current'  => $current,
					'delta'    => $delta,
				);
			}
		}

		return $this->report( $items );
	}

	/**
	 * @param list<array<string,mixed>> $items
	 * @return array<string,mixed>
	 */
	private function report( array $items ): array {
		$blocking = 0;
		$warnings = 0;

		foreach ( $items as $item ) {
			if ( ( $item['severity'] ?? 'warning' ) === 'blocking' ) {
				++$blocking;
			} else {
				++$warnings;
			}
		}

		return array(
			'checked_at'       => Utils::nowDb(),
			'items'            => $items,
			'blocking_count'   => $blocking,
			'warning_count'    => $warnings,
			'has_discrepancies' => $items !== array(),
		);
	}
}
