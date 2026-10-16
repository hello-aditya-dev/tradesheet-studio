<?php
/**
 * Product adapter over WooCommerce CRUD APIs.
 *
 * Wraps wc_get_product / WC_Product_Query. No WooCommerce internal tables
 * are read directly. Only supported types are exposed for packet lines:
 * simple products and product variations (variable parents are used for
 * browsing and variation selection only).
 *
 * @package TradeSheetStudio\Products
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Products;

use TradeSheetStudio\Support\Money;

final class ProductAdapter {

        public const SUPPORTED_TYPES = array( 'simple', 'variation' );

        public function get( int $id ): ?\WC_Product {
                $product = wc_get_product( $id );

                return ( $product instanceof \WC_Product ) ? $product : null;
        }

        public function getForLine( int $productId, int $variationId ): ?\WC_Product {
                $id = $variationId > 0 ? $variationId : $productId;
                $product = $this->get( $id );

                if ( null === $product ) {
                        return null;
                }

                if ( ! in_array( $product->get_type(), self::SUPPORTED_TYPES, true ) ) {
                        return null;
                }

                return $product;
        }

        public function supportedType( \WC_Product $product ): bool {
                return in_array( $product->get_type(), self::SUPPORTED_TYPES, true );
        }

        /**
         * Active (sale-aware) WooCommerce price as a canonical decimal string.
         */
        public function priceFor( int $productId, int $variationId ): string {
                $product = $this->getForLine( $productId, $variationId );

                if ( null === $product ) {
                        return '0.0000';
                }

                $price = trim( (string) $product->get_price() );

                if ( '' === $price || ! preg_match( '/^\d+(\.\d{1,4})?$/', $price ) ) {
                        if ( is_numeric( $price ) ) {
                                $price = number_format( (float) $price, 4, '.', '' );
                        } else {
                                return '0.0000';
                        }
                }

                return Money::round( $price, 4 );
        }

        /**
         * Availability snapshot used by preflight and integrity checks.
         *
         * @return array{available:bool,reason:string,stock:string,purchasable:bool,managed:bool,qty:?int}
         */
        public function availability( int $productId, int $variationId ): array {
                $product = $this->getForLine( $productId, $variationId );

                if ( null === $product ) {
                        return array(
                                'available'   => false,
                                'reason'      => 'missing',
                                'stock'       => 'outofstock',
                                'purchasable' => false,
                                'managed'     => false,
                                'qty'         => null,
                        );
                }

                $managed = $product->get_manage_stock();
                $qty     = $product->get_stock_quantity();

                return array(
                        'available'   => 'publish' === $product->get_status() && $product->is_purchasable(),
                        'reason'      => 'publish' === $product->get_status() ? ( $product->is_purchasable() ? 'ok' : 'not_purchasable' ) : 'not_published',
                        'stock'       => (string) $product->get_stock_status(),
                        'purchasable' => $product->is_purchasable(),
                        'managed'     => $managed,
                        'qty'         => is_numeric( $qty ) ? (int) $qty : null,
                );
        }

        /**
         * Presentation summary for packet lines and documents.
         *
         * @return array<string,mixed>
         */
        public function summary( int $productId, int $variationId ): array {
                $product = $this->getForLine( $productId, $variationId );

                if ( null === $product ) {
                        return array();
                }

                $parentId = $product->get_parent_id();
                $parent   = $parentId > 0 ? $this->get( $parentId ) : null;

                $name = (string) $product->get_name();
                if ( '' === $name && null !== $parent ) {
                        $name = (string) $parent->get_name();
                }

                $variationSummary = '';
                if ( 'variation' === $product->get_type() && null !== $parent ) {
                        $variationSummary = $this->describeVariation( $product );
                }

                $shortDescription = '';
                $sourceProduct    = ( null !== $parent ) ? $parent : $product;
                $rawShort         = (string) $sourceProduct->get_short_description();
                if ( '' === $rawShort ) {
                        $rawShort = (string) $sourceProduct->get_description();
                }
                $shortDescription = wp_strip_all_tags( $rawShort );
                $shortDescription = mb_substr( trim( $shortDescription ), 0, 1200 );

                $imageId = (int) $product->get_image_id();
                if ( 0 === $imageId && null !== $parent ) {
                        $imageId = (int) $parent->get_image_id();
                }

                $sku = (string) $product->get_sku();
                if ( '' === $sku && null !== $parent ) {
                        $sku = (string) $parent->get_sku();
                }

                return array(
                        'product_id'        => $productId,
                        'variation_id'      => $variationId,
                        'name'              => $name,
                        'sku'               => $sku,
                        'variation_summary' => $variationSummary,
                        'short_description' => $shortDescription,
                        'image_id'          => $imageId,
                        'type'              => (string) $product->get_type(),
                        'stock_status'      => (string) $product->get_stock_status(),
                        'attributes'        => $this->visibleAttributes( $sourceProduct ),
                );
        }

        /**
         * Human-readable variation summary, e.g. "Colour: Red · Size: L".
         */
        public function describeVariation( \WC_Product_Variation $variation ): string {
                $attributes = $variation->get_variation_attributes();
                $labels     = array();

                foreach ( $attributes as $taxonomy => $value ) {
                        if ( '' === (string) $value ) {
                                continue;
                        }

                        $label = str_replace( 'attribute_pa_', 'attribute_', (string) $taxonomy );
                        $label = str_replace( 'attribute_', '', $label );
                        $label = str_replace( array( '-', '_' ), ' ', $label );
                        $label = ucwords( $label );

                        $term = get_term_by( 'slug', (string) $value, (string) $taxonomy );
                        $display = is_object( $term ) && isset( $term->name ) ? (string) $term->name : str_replace( array( '-', '_' ), ' ', (string) $value );

                        $labels[] = $label . ': ' . ucwords( $display );
                }

                return implode( ' · ', $labels );
        }

        /**
         * Selected visible attributes for technical documents.
         *
         * @return list<array{label:string,value:string}>
         */
        public function visibleAttributes( \WC_Product $product ): array {
                $out = array();

                if ( ! $product->has_attributes() ) {
                        return $out;
                }

                foreach ( $product->get_attributes() as $attribute ) {
                        if ( ! $attribute instanceof \WC_Product_Attribute ) {
                                continue;
                        }
                        if ( ! $attribute->get_visible() ) {
                                continue;
                        }

                        $values = $attribute->get_options();
                        if ( is_array( $values ) ) {
                                $label = str_replace( array( '-', '_' ), ' ', (string) $attribute->get_name() );
                                $label = ucwords( $label );

                                $displayTexts = array();
                                foreach ( $values as $value ) {
                                        if ( is_numeric( $value ) ) {
                                                $term = get_term_by( 'term_id', (int) $value, (string) $attribute->get_name() );
                                                if ( is_object( $term ) && isset( $term->name ) ) {
                                                        $displayTexts[] = (string) $term->name;
                                                        continue;
                                                }
                                        }
                                        $displayTexts[] = (string) $value;
                                }

                                $out[] = array( 'label' => $label, 'value' => implode( ', ', $displayTexts ) );
                        }
                }

                return $out;
        }

        /**
         * Admin product search for the packet composer.
         *
         * @return array{items:list<array<string,mixed>>,total:int}
         */
        public function search( string $term, int $page, int $perPage, int $categoryId = 0 ): array {
                $perPage = max( 1, min( 100, $perPage ) );
                $page    = max( 1, $page );

                $args = array(
                        'status' => array( 'publish' ),
                        'type'   => array( 'simple', 'variable' ),
                        'limit'  => $perPage,
                        'page'   => $page,
                        'orderby' => 'title',
                        'order'  => 'ASC',
                        'return' => 'objects',
                );

                if ( '' !== $term ) {
                        $args['search'] = $term;
                }

                if ( $categoryId > 0 ) {
                        $args['category'] = array( $categoryId );
                }

                $query = new \WC_Product_Query( $args );
                $products = $query->get_products();

                $total = 0;
                if ( method_exists( $query, 'get_total' ) ) {
                        $total = (int) $query->get_total();
                }

                if ( 0 === $total ) {
                        // Fallback total for older WooCommerce versions.
                        $countArgs = $args;
                        unset( $countArgs['limit'], $countArgs['page'], $countArgs['orderby'], $countArgs['order'] );
                        $countQuery = new \WC_Product_Query( array_merge( $countArgs, array( 'limit' => 500, 'return' => 'ids' ) ) );
                        $total = count( $countQuery->get_products() );
                }

                $items = array();
                foreach ( $products as $product ) {
                        if ( ! $product instanceof \WC_Product ) {
                                continue;
                        }

                        $items[] = array(
                                'id'          => (int) $product->get_id(),
                                'name'        => (string) $product->get_name(),
                                'sku'         => (string) $product->get_sku(),
                                'type'        => (string) $product->get_type(),
                                'price'       => $this->priceFor( (int) $product->get_id(), 0 ),
                                'image_id'    => (int) $product->get_image_id(),
                                'stock_status' => (string) $product->get_stock_status(),
                                'categories'  => array_map( 'intval', $product->get_category_ids() ),
                        );
                }

                return array( 'items' => $items, 'total' => $total );
        }

        /**
         * Variations of a variable product for selection.
         *
         * @return list<array<string,mixed>>
         */
        public function variations( int $productId ): array {
                $product = $this->get( $productId );

                if ( null === $product || ! $product instanceof \WC_Product_Variable ) {
                        return array();
                }

                $out = array();
                foreach ( $product->get_children() as $variationId ) {
                        $variation = $this->get( (int) $variationId );
                        if ( null === $variation || ! $variation instanceof \WC_Product_Variation ) {
                                continue;
                        }
                        if ( 'publish' !== $variation->get_status() ) {
                                continue;
                        }

                        $out[] = array(
                                'variation_id' => (int) $variation->get_id(),
                                'product_id'   => $productId,
                                'sku'          => (string) $variation->get_sku(),
                                'summary'      => $this->describeVariation( $variation ),
                                'price'        => $this->priceFor( $productId, (int) $variation->get_id() ),
                                'stock_status' => (string) $variation->get_stock_status(),
                                'image_id'     => (int) $variation->get_image_id(),
                        );
                }

                return $out;
        }

        /**
         * Tax presentation context recorded in snapshots so quote/order checks
         * can detect material tax-setting changes.
         *
         * @return array{prices_include_tax:bool,shipping_tax_class:string,hash:string}
         */
        public function taxContext(): array {
                $include = false;
                if ( function_exists( 'wc_prices_include_tax' ) ) {
                        $include = wc_prices_include_tax();
                }

                $shippingClass = '';
                if ( function_exists( 'get_option' ) ) {
                        $shippingClass = (string) get_option( 'woocommerce_shipping_tax_class', '' );
                }

                $context = array(
                        'prices_include_tax' => $include,
                        'shipping_tax_class' => $shippingClass,
                );

                $context['hash'] = md5( \TradeSheetStudio\Support\Utils::jsonEncode( $context ) );

                return $context;
        }
}
