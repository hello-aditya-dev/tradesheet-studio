<?php
/**
 * Price rule value object with strict validation.
 *
 * A rule row is one deterministic scope: (price book, customer, product,
 * variation). A rule must define EITHER a fixed price override OR a
 * percentage discount — never both — and may additionally carry quantity
 * constraints (min, step) and absolute quantity-break tiers.
 *
 * Quantity-break format (JSON, ascending by qty):
 *   [{"qty":10,"price":"8.5000"},{"qty":50,"price":"8.2000"}]
 *
 * @package TradeSheetStudio\Pricing
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Pricing;

use TradeSheetStudio\Support\Money;
use TradeSheetStudio\Support\Utils;
use TradeSheetStudio\Support\ValidationException;

final class PriceRule {

	public function __construct(
		public readonly ?int $id,
		public readonly int $pricebookId,
		public readonly int $customerId,
		public readonly int $productId,
		public readonly int $variationId,
		public readonly ?string $priceOverride,
		public readonly ?string $percentOff,
		public readonly int $minQty,
		public readonly int $qtyStep,
		public readonly array $qtyBreaks,
		public readonly int $priority,
		public readonly ?string $validFrom,
		public readonly ?string $validTo
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray( array $data ): self {
		$pricebookId = Utils::intOr( $data['pricebook_id'] ?? 0, 0 );
		if ( $pricebookId < 1 ) {
			throw new ValidationException( __( 'A price rule requires a valid price book.', 'tradesheet-studio' ) );
		}

		$customerId  = max( 0, Utils::intOr( $data['customer_id'] ?? 0, 0 ) );
		$productId   = max( 0, Utils::intOr( $data['product_id'] ?? 0, 0 ) );
		$variationId = max( 0, Utils::intOr( $data['variation_id'] ?? 0, 0 ) );

		if ( $variationId > 0 && $productId < 1 ) {
			throw new ValidationException( __( 'A variation-scoped rule also requires a product reference.', 'tradesheet-studio' ) );
		}

		$overrideRaw = isset( $data['price_override'] ) ? trim( (string) $data['price_override'] ) : '';
		$percentRaw  = isset( $data['percent_off'] ) ? trim( (string) $data['percent_off'] ) : '';

		if ( '' !== $overrideRaw && '' !== $percentRaw ) {
			throw new ValidationException( __( 'A rule cannot define both a fixed price override and a percentage discount.', 'tradesheet-studio' ) );
		}

		$priceOverride = null;
		if ( '' !== $overrideRaw ) {
			$normalized = Money::round( Utils::sanitizeFloatish( $overrideRaw ), 4 );
			if ( ! Money::isValid( $normalized ) || Money::cmp( $normalized, '0.0000' ) < 0 ) {
				throw new ValidationException( __( 'The fixed price override must be a non-negative amount with at most 4 decimals.', 'tradesheet-studio' ) );
			}
			$priceOverride = $normalized;
		}

		$percentOff = null;
		if ( '' !== $percentRaw ) {
			$normalized = Money::round( Utils::sanitizeFloatish( $percentRaw ), 2 );
			if ( ! Money::isValid( $normalized ) || Money::cmp( $normalized, '0.00' ) < 0 || Money::cmp( $normalized, '100.00' ) > 0 ) {
				throw new ValidationException( __( 'The percentage discount must be between 0 and 100.', 'tradesheet-studio' ) );
			}
			$percentOff = $normalized;
		}

		$minQty = Utils::intOr( $data['min_qty'] ?? 1, 1 );
		if ( $minQty < 1 || $minQty > Money::MAX_QTY ) {
			throw new ValidationException( __( 'The minimum quantity must be between 1 and 100000.', 'tradesheet-studio' ) );
		}

		$qtyStep = Utils::intOr( $data['qty_step'] ?? 1, 1 );
		if ( $qtyStep < 1 || $qtyStep > 10000 ) {
			throw new ValidationException( __( 'The quantity step must be between 1 and 10000.', 'tradesheet-studio' ) );
		}

		$priority = Utils::intOr( $data['priority'] ?? 0, 0 );
		if ( $priority < -99 || $priority > 99 ) {
			throw new ValidationException( __( 'The rule priority must be between -99 and 99.', 'tradesheet-studio' ) );
		}

		$qtyBreaks = self::validateBreaks( $data['qty_breaks'] ?? array() );

		$validFrom = self::validateDate( $data['valid_from'] ?? null );
		$validTo   = self::validateDate( $data['valid_to'] ?? null );

		return new self(
			null === ( $data['id'] ?? null ) ? null : Utils::intOr( $data['id'], 0 ),
			$pricebookId,
			$customerId,
			$productId,
			$variationId,
			$priceOverride,
			$percentOff,
			$minQty,
			$qtyStep,
			$qtyBreaks,
			$priority,
			$validFrom,
			$validTo
		);
	}

	/**
	 * @param list<array<string,mixed>> $breaks
	 * @return list<array{qty:int,price:string}>
	 */
	private static function validateBreaks( array $breaks ): array {
		$clean = array();

		foreach ( $breaks as $break ) {
			if ( ! is_array( $break ) ) {
				throw new ValidationException( __( 'Invalid quantity break entry.', 'tradesheet-studio' ) );
			}

			$qty = Utils::intOr( $break['qty'] ?? 0, 0 );
			if ( $qty < 2 || $qty > Money::MAX_QTY ) {
				throw new ValidationException( __( 'Quantity break thresholds must be between 2 and 100000.', 'tradesheet-studio' ) );
			}

			$price = Money::round( Utils::sanitizeFloatish( (string) ( $break['price'] ?? '' ) ), 4 );
			if ( ! Money::isValid( $price ) || Money::cmp( $price, '0.0000' ) < 0 ) {
				throw new ValidationException( __( 'Quantity break prices must be non-negative amounts with at most 4 decimals.', 'tradesheet-studio' ) );
			}

			$clean[] = array( 'qty' => $qty, 'price' => $price );
		}

		usort(
			$clean,
			static function ( array $a, array $b ): int {
				return $a['qty'] <=> $b['qty'];
			}
		);

		$lastQty = 0;
		foreach ( $clean as $break ) {
			if ( $break['qty'] === $lastQty ) {
				throw new ValidationException( __( 'Quantity break thresholds must be unique.', 'tradesheet-studio' ) );
			}
			$lastQty = $break['qty'];
		}

		return $clean;
	}

	private static function validateDate( mixed $value ): ?string {
		if ( null === $value || '' === $value ) {
			return null;
		}

		$value = trim( (string) $value );
		$dt    = \DateTime::createFromFormat( 'Y-m-d H:i:s', $value );
		if ( false === $dt ) {
			$dt = \DateTime::createFromFormat( 'Y-m-d', $value );
			if ( false === $dt ) {
				throw new ValidationException( __( 'Validity dates must use the YYYY-MM-DD or YYYY-MM-DD HH:MM:SS format.', 'tradesheet-studio' ) );
			}
			$value = $dt->format( 'Y-m-d 00:00:00' );
		}

		return $value;
	}

	/**
	 * @param array<string,mixed> $row
	 */
	public static function fromRow( array $row ): self {
		$breaks = array();
		if ( ! empty( $row['qty_breaks'] ) ) {
			$decoded = json_decode( (string) $row['qty_breaks'], true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $break ) {
					if ( is_array( $break ) && isset( $break['qty'], $break['price'] ) ) {
						$breaks[] = array( 'qty' => (int) $break['qty'], 'price' => (string) $break['price'] );
					}
				}
			}
		}

		return new self(
			(int) ( $row['id'] ?? 0 ),
			(int) ( $row['pricebook_id'] ?? 0 ),
			(int) ( $row['customer_id'] ?? 0 ),
			(int) ( $row['product_id'] ?? 0 ),
			(int) ( $row['variation_id'] ?? 0 ),
			null === ( $row['price_override'] ?? null ) ? null : (string) $row['price_override'],
			null === ( $row['percent_off'] ?? null ) ? null : (string) $row['percent_off'],
			(int) ( $row['min_qty'] ?? 1 ),
			(int) ( $row['qty_step'] ?? 1 ),
			$breaks,
			(int) ( $row['priority'] ?? 0 ),
			$row['valid_from'] ?? null,
			$row['valid_to'] ?? null
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function toRow(): array {
		return array(
			'pricebook_id'  => $this->pricebookId,
			'customer_id'   => $this->customerId,
			'product_id'    => $this->productId,
			'variation_id'  => $this->variationId,
			'price_override'=> $this->priceOverride,
			'percent_off'   => $this->percentOff,
			'min_qty'       => $this->minQty,
			'qty_step'      => $this->qtyStep,
			'qty_breaks'    => $this->qtyBreaksJson(),
			'priority'      => $this->priority,
			'valid_from'    => $this->validFrom,
			'valid_to'      => $this->validTo,
		);
	}

	public function qtyBreaksJson(): string {
		return Utils::jsonEncode( $this->qtyBreaks );
	}
}
