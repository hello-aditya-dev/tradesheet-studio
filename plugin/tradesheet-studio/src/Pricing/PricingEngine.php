<?php
/**
 * Deterministic wholesale price resolution.
 *
 * PRECEDENCE (documented, no silent stacking):
 *   1. Packet line price override (applied by the snapshot service).
 *   2. Customer-specific rule (customer_id > 0) in the customer's assigned
 *      price book.
 *   3. Price-book rule (customer_id = 0) in that book.
 *   4. WooCommerce base (active) price.
 *
 * Within levels 2–3, specificity ranks: variation-scoped > product-scoped >
 * book-wide, then the explicit rule priority, then the lowest rule id —
 * exactly ONE rule wins and is applied. The winner's quantity-break tier
 * replaces the rule price when the requested quantity reaches a threshold.
 *
 * resolveFromData() is intentionally pure (no WordPress calls) so the whole
 * model is covered by standalone unit tests.
 *
 * @package TradeSheetStudio\Pricing
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Pricing;

use TradeSheetStudio\Support\Money;

final class PricingEngine {

        public function __construct(
                private PriceBookRepository $books,
                private \TradeSheetStudio\Products\ProductAdapter $products
        ) {
        }

        /**
         * Full WooCommerce-aware resolution for one product/variation/quantity.
         */
        public function resolveForCustomer( int $customerId, int $productId, int $variationId, int $qty ): PriceResolution {
                $base = $this->products->priceFor( $productId, $variationId );

                $bookId = 0;
                if ( $customerId > 0 ) {
                        $bookId = $this->books->assignedBookId( $customerId );
                }

                $rules = array();
                if ( $bookId > 0 && $this->books->isUsable( $bookId ) ) {
                        $rules = $this->books->candidateRules( $bookId, $customerId, $productId, $variationId );
                }

                return self::resolveFromData( $base, $rules, $qty );
        }

        /**
         * Pure resolution core.
         *
         * @param string                $base  Base price as a canonical decimal string.
         * @param list<array<string,mixed>> $rules Candidate rule rows.
         */
        public static function resolveFromData( string $base, array $rules, int $qty ): PriceResolution {
                $warnings = array();

                if ( '' === $base ) {
                        $base = '0.0000';
                }

                if ( ! Money::isValid( $base ) ) {
                        $base     = Money::round( $base, 4 );
                        $warnings[] = __( 'The base price was normalized to 4 decimals.', 'tradesheet-studio' );
                }

                if ( Money::cmp( $base, '0.0000' ) === 0 ) {
                        $warnings[] = __( 'The product has no WooCommerce base price.', 'tradesheet-studio' );
                }

                $winner = self::pickWinner( $rules );

                if ( null === $winner ) {
                        return new PriceResolution(
                                $base,
                                'base',
                                null,
                                1,
                                1,
                                __( 'WooCommerce base price (no applicable price-book rule).', 'tradesheet-studio' ),
                                $warnings
                        );
                }

                $source    = ( (int) $winner['customer_id'] > 0 ) ? 'customer_rule' : 'pricebook_rule';
                $ruleId    = (int) $winner['id'];
                $minQty    = max( 1, (int) ( $winner['min_qty'] ?? 1 ) );
                $qtyStep   = max( 1, (int) ( $winner['qty_step'] ?? 1 ) );

                if ( null !== ( $winner['price_override'] ?? null ) && '' !== (string) $winner['price_override'] ) {
                        $price       = (string) $winner['price_override'];
                        $explanation = sprintf(
                                /* translators: %d: rule id. */
                                __( 'Fixed price override from rule #%d.', 'tradesheet-studio' ),
                                $ruleId
                        );
                } elseif ( null !== ( $winner['percent_off'] ?? null ) && '' !== (string) $winner['percent_off'] ) {
                        $price       = Money::percent( $base, (string) $winner['percent_off'] );
                        $explanation = sprintf(
                                /* translators: 1: rule id, 2: percent. */
                                __( 'Base price less %2$s%% discount (rule #%1$d).', 'tradesheet-studio' ),
                                $ruleId,
                                (string) $winner['percent_off']
                        );
                } else {
                        $price       = $base;
                        $explanation = sprintf(
                                /* translators: %d: rule id. */
                                __( 'Quantity constraints from rule #%d (price unchanged).', 'tradesheet-studio' ),
                                $ruleId
                        );
                }

                // Quantity-break tier: the highest threshold reached replaces the price.
                $breaks = array();
                if ( ! empty( $winner['qty_breaks'] ) ) {
                        $decoded = json_decode( (string) $winner['qty_breaks'], true );
                        if ( is_array( $decoded ) ) {
                                foreach ( $decoded as $break ) {
                                        if ( is_array( $break ) && isset( $break['qty'], $break['price'] ) ) {
                                                $breaks[] = array( 'qty' => (int) $break['qty'], 'price' => (string) $break['price'] );
                                        }
                                }
                        }
                }

                if ( $breaks !== array() && $qty > 0 ) {
                        usort(
                                $breaks,
                                static function ( array $a, array $b ): int {
                                        return $a['qty'] <=> $b['qty'];
                                }
                        );

                        $best = null;
                        foreach ( $breaks as $break ) {
                                if ( $qty >= $break['qty'] ) {
                                        $best = $break;
                                }
                        }

                        if ( null !== $best ) {
                                $price       = $best['price'];
                                $source     .= '_qty_break';
                                $explanation = sprintf(
                                        /* translators: 1: threshold qty, 2: rule id. */
                                        __( 'Quantity break at %1$d+ units applied (rule #%2$d).', 'tradesheet-studio' ),
                                        $best['qty'],
                                        $ruleId
                                );
                        }
                }

                return new PriceResolution(
                        $price,
                        $source,
                        $ruleId,
                        $minQty,
                        $qtyStep,
                        $explanation,
                        $warnings,
                        $breaks
                );
        }

        /**
         * Effective unit price for a quantity: the highest reached quantity-break
         * tier replaces the line price; otherwise the line price applies.
         *
         * @param string                   $basePrice Line unit price.
         * @param list<array{qty:int,price:string}> $breaks Quantity breaks.
         * @param int                      $qty       Requested quantity.
         */
        public static function effectiveUnitPrice( string $basePrice, array $breaks, int $qty ): string {
                if ( $breaks === array() || $qty < 2 ) {
                        return $basePrice;
                }

                $best = null;
                foreach ( $breaks as $break ) {
                        if ( is_array( $break ) && isset( $break['qty'], $break['price'] ) && $qty >= (int) $break['qty'] ) {
                                $best = $break;
                        }
                }

                return null === $best ? $basePrice : (string) $best['price'];
        }

        /**
         * Deterministic winner selection. Comparator order:
         * customer-specific > variation-scoped > product-scoped > book-wide,
         * then priority (desc), then lowest rule id (stable tie-break).
         *
         * @param list<array<string,mixed>> $rules
         * @return array<string,mixed>|null
         */
        public static function pickWinner( array $rules ): ?array {
                $best = null;
                $bestRank = null;

                foreach ( $rules as $rule ) {
                        $rank = self::rank( $rule );

                        if ( null === $bestRank || $rank > $bestRank ) {
                                $best      = $rule;
                                $bestRank  = $rank;
                        }
                }

                return $best;
        }

        /**
         * @param array<string,mixed> $rule
         */
        private static function rank( array $rule ): array {
                $customerSpecific = ( (int) ( $rule['customer_id'] ?? 0 ) > 0 ) ? 1 : 0;
                $variationScoped  = ( (int) ( $rule['variation_id'] ?? 0 ) > 0 ) ? 1 : 0;
                $productScoped    = ( (int) ( $rule['product_id'] ?? 0 ) > 0 ) ? 1 : 0;
                $priority         = (int) ( $rule['priority'] ?? 0 );
                $id               = (int) ( $rule['id'] ?? 0 );

                return array( $customerSpecific, $variationScoped, $productScoped, $priority, -$id );
        }
}
