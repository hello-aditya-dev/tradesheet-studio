<?php
/**
 * Result of a price resolution, carrying the winning price, the rule that
 * produced it (for merchant transparency) and quantity constraints.
 *
 * @package TradeSheetStudio\Pricing
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Pricing;

final class PriceResolution {

        public function __construct(
                public readonly string $unitPrice,
                public readonly string $source,
                public readonly ?int $ruleId,
                public readonly int $minQty,
                public readonly int $qtyStep,
                public readonly string $explanation,
                public readonly array $warnings = array(),
                public readonly array $qtyBreaks = array()
        ) {
        }

        /**
         * @return array<string,mixed>
         */
        public function toArray(): array {
                return array(
                        'unit_price'  => $this->unitPrice,
                        'source'      => $this->source,
                        'rule_id'     => $this->ruleId,
                        'min_qty'     => $this->minQty,
                        'qty_step'    => $this->qtyStep,
                        'explanation' => $this->explanation,
                        'warnings'    => $this->warnings,
                        'qty_breaks'  => $this->qtyBreaks,
                );
        }
}
