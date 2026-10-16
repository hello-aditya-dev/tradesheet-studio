<?php
/**
 * Quote-to-order bridge.
 *
 * Creates the WooCommerce order from an ACCEPTED quote at the NEGOTIATED
 * line prices (never the current public price), using WooCommerce CRUD and
 * letting WooCommerce's native tax engine compute taxes from those values.
 *
 * IDEMPOTENCY: tss_order_links has a PRIMARY KEY on quote_id — exactly one
 * conversion row can ever exist per quote. Concurrent/duplicate submissions
 * either observe an existing COMPLETE link (return the same order) or a
 * CONVERTING link (return "in progress"). A recovery sweep reconciles
 * interrupted conversions: it searches WooCommerce for an order carrying the
 * quote reference meta before allowing a retry.
 *
 * @package TradeSheetStudio\Orders
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Orders;

use TradeSheetStudio\Database\Schema;
use TradeSheetStudio\Packets\PacketRepository;
use TradeSheetStudio\Quotes\QuoteRepository;
use TradeSheetStudio\Support\EventLogger;
use TradeSheetStudio\Support\Money;
use TradeSheetStudio\Support\Notifier;
use TradeSheetStudio\Support\Utils;
use TradeSheetStudio\Support\ValidationException;

final class WooCommerceOrderBridge {

        private const STALE_SECONDS = 600; // 10 minutes.

        public function __construct(
                private PacketRepository $packets,
                private QuoteRepository $quotes,
                private EventLogger $events
        ) {
        }

        /**
         * Convert an accepted quote into a WooCommerce order (idempotent).
         *
         * @return array{order_id?:int,already?:bool,pending?:bool,message?:string,pay_url?:string}
         */
        public function convert( int $quoteId, ?int $actorId = null ): array {
                global $wpdb;

                $quote = $this->quotes->get( $quoteId );
                if ( null === $quote ) {
                        throw new \RuntimeException( esc_html__( 'Quote not found.', 'tradesheet-studio' ) );
                }

                // Idempotent replay: an already-converted quote returns its order.
                if ( QuoteRepository::ORDERED === (string) $quote['status'] ) {
                        $link = $this->linkFor( $quoteId );

                        if ( null !== $link && 'complete' === (string) $link['status'] && (int) $link['order_id'] > 0 ) {
                                return array(
                                        'order_id' => (int) $link['order_id'],
                                        'already'  => true,
                                        'pay_url'  => $this->payUrl( (int) $link['order_id'] ),
                                        'message'  => __( 'This quote was already converted; the existing order is returned.', 'tradesheet-studio' ),
                                );
                        }

                        // Reconcile an interrupted conversion before answering.
                        $orderId = $this->findExistingOrderId( $quoteId );

                        if ( $orderId > 0 ) {
                                $this->completeLink( $quoteId, $orderId );

                                return array(
                                        'order_id' => $orderId,
                                        'already'  => true,
                                        'pay_url'  => $this->payUrl( $orderId ),
                                );
                        }

                        throw new ValidationException( __( 'This quote was already converted but its order reference is missing. Run the recovery process from Diagnostics.', 'tradesheet-studio' ) );
                }

                if ( QuoteRepository::ACCEPTED !== (string) $quote['status'] ) {
                        throw new ValidationException( __( 'Only accepted quotations can be converted into orders.', 'tradesheet-studio' ) );
                }

                $link = $this->linkFor( $quoteId );

                // Idempotent success: return the existing order.
                if ( null !== $link && 'complete' === (string) $link['status'] && (int) $link['order_id'] > 0 ) {
                        return array(
                                'order_id' => (int) $link['order_id'],
                                'already'  => true,
                                'pay_url'  => $this->payUrl( (int) $link['order_id'] ),
                                'message'  => __( 'This quote was already converted; the existing order is returned.', 'tradesheet-studio' ),
                        );
                }

                // Conversion in flight.
                if ( null !== $link && 'converting' === (string) $link['status'] ) {
                        $age = time() - strtotime( (string) $link['created_at'] );

                        if ( $age < self::STALE_SECONDS ) {
                                return array(
                                        'pending' => true,
                                        'message' => __( 'A conversion for this quotation is already in progress.', 'tradesheet-studio' ),
                                );
                        }

                        // Stale: reconcile — did the order actually get created before the failure?
                        $orderId = $this->findExistingOrderId( (int) $quote['id'] );

                        if ( $orderId > 0 ) {
                                $this->completeLink( $quoteId, $orderId );
                                $this->quotes->markOrdered( $quoteId, $orderId, $actorId );

                                return array(
                                        'order_id' => $orderId,
                                        'already'  => true,
                                        'pay_url'  => $this->payUrl( $orderId ),
                                );
                        }

                        // No order exists: release the claim so this attempt can proceed.
                        $wpdb->delete( Schema::tableName( 'tss_order_links' ), array( 'quote_id' => $quoteId ), array( '%d' ) );
                }

                // Atomic claim (PRIMARY KEY on quote_id).
                $inserted = $wpdb->insert(
                        Schema::tableName( 'tss_order_links' ),
                        array(
                                'quote_id'       => $quoteId,
                                'conversion_key' => hash( 'sha256', 'tss-quote-' . $quoteId ),
                                'status'         => 'converting',
                                'created_at'     => Utils::nowDb(),
                        ),
                        array( '%d', '%s', '%s', '%s' )
                );

                if ( false === $inserted ) {
                        // Concurrent claim won the race.
                        return array(
                                'pending' => true,
                                'message' => __( 'A concurrent conversion is in progress; please retry.', 'tradesheet-studio' ),
                        );
                }

                try {
                        $orderId = $this->createOrder( $quote );

                        $this->completeLink( $quoteId, $orderId );
                        $this->quotes->markOrdered( $quoteId, $orderId, $actorId );

                        $payUrl = $this->payUrl( $orderId );

                        Notifier::orderCreated( (int) $quote['customer_id'], $orderId, $payUrl );

                        return array(
                                'order_id' => $orderId,
                                'pay_url'  => $payUrl,
                        );
                } catch ( \Throwable $e ) {
                        global $wpdb;

                        $wpdb->update(
                                Schema::tableName( 'tss_order_links' ),
                                array(
                                        'status' => 'failed',
                                        'error'  => mb_substr( $e->getMessage(), 0, 2000 ),
                                ),
                                array( 'quote_id' => $quoteId ),
                                array( '%s', '%s' ),
                                array( '%d' )
                        );

                        throw $e;
                }
        }

        /**
         * Create the WooCommerce order with negotiated prices.
         *
         * @param array<string,mixed> $quote
         */
        private function createOrder( array $quote ): int {
                if ( ! function_exists( 'wc_create_order' ) ) {
                        throw new \RuntimeException( esc_html__( 'WooCommerce is not available.', 'tradesheet-studio' ) );
                }

                $lines = $this->quotes->lines( (int) $quote['id'] );
                if ( $lines === array() ) {
                        throw new ValidationException( __( 'The quote has no lines to order.', 'tradesheet-studio' ) );
                }

                $packet = $this->packets->get( (int) $quote['packet_id'] );
                $version = $this->packets->version( (int) $quote['version_id'] );
                $snapshot = null === $version ? array() : ( json_decode( (string) $version['snapshot'], true ) ?: array() );
                $currency = (string) ( $snapshot['packet']['currency'] ?? ( null !== $packet ? $packet['currency'] : '' ) );

                // Final revalidation of purchasability (fail closed).
                foreach ( $lines as $line ) {
                        $product = wc_get_product( (int) $line['variation_id'] > 0 ? (int) $line['variation_id'] : (int) $line['product_id'] );

                        if ( ! $product instanceof \WC_Product ) {
                                throw new ValidationException(
                                        sprintf(
                                                /* translators: %s: sku. */
                                                __( 'Order conversion blocked: product "%s" no longer exists. A new quotation revision is required.', 'tradesheet-studio' ),
                                                (string) $line['sku']
                                        )
                                );
                        }

                        if ( 'publish' !== $product->get_status() || ! $product->is_purchasable() ) {
                                throw new ValidationException(
                                        sprintf(
                                                /* translators: %s: sku. */
                                                __( 'Order conversion blocked: product "%s" is not purchasable. A new quotation revision is required.', 'tradesheet-studio' ),
                                                (string) $line['sku']
                                        )
                                );
                        }
                }

                $order = wc_create_order(
                        array(
                                'status'      => 'pending',
                                'customer_id' => (int) $quote['customer_id'],
                                'created_via' => 'tradesheet-studio',
                                'cart_hash'   => '',
                        )
                );

                if ( ! $order instanceof \WC_Order ) {
                        throw new \RuntimeException( esc_html__( 'WooCommerce could not create the order.', 'tradesheet-studio' ) );
                }

                foreach ( $lines as $line ) {
                        $productId   = (int) $line['product_id'];
                        $variationId = (int) $line['variation_id'];
                        $qty         = (int) $line['qty'];
                        $unitPrice   = (string) $line['unit_price'];

                        $product = wc_get_product( $variationId > 0 ? $variationId : $productId );

                        $item = new \WC_Order_Item_Product();
                        $item->set_props(
                                array(
                                        'product'  => $product,
                                        'quantity' => $qty,
                                        'subtotal' => Money::mul( $unitPrice, $qty ),
                                        'total'    => Money::mul( $unitPrice, $qty ),
                                )
                        );

                        // Negotiated price provenance on every line.
                        $item->add_meta_data( '_tss_negotiated_unit_price', $unitPrice, true );
                        $item->add_meta_data( '_tss_row_id', 'L' . (string) $line['packet_line_id'], true );

                        $order->add_item( $item );
                }

                if ( '' !== $currency ) {
                        $order->set_currency( $currency );
                }

                $this->applyCustomerAddresses( $order, (int) $quote['customer_id'] );

                $order->update_meta_data( '_tss_quote_id', (int) $quote['id'] );
                $order->update_meta_data( '_tss_quote_ref', (string) $quote['ref'] );
                $order->update_meta_data( '_tss_packet_ref', null !== $packet ? (string) $packet['ref'] : '' );
                $order->update_meta_data( '_tss_packet_version', null === $version ? 0 : (int) $version['version_no'] );
                $order->update_meta_data( '_tss_snapshot_checksum', null === $version ? '' : (string) $version['content_checksum'] );

                // Taxes and totals via WooCommerce's native engine over negotiated values.
                $order->calculate_totals();
                $order->save();

                $this->events->log(
                        'order',
                        (int) $order->get_id(),
                        'created',
                        null,
                        array(
                                'quote'  => (string) $quote['ref'],
                                'packet' => null !== $packet ? (string) $packet['ref'] : '',
                                'lines'  => count( $lines ),
                        )
                );

                return (int) $order->get_id();
        }

        private function applyCustomerAddresses( \WC_Order $order, int $customerId ): void {
                if ( $customerId < 1 ) {
                        return;
                }

                $mapping = array(
                        'first_name' => 'billing_first_name',
                        'last_name'  => 'billing_last_name',
                        'company'    => 'billing_company',
                        'address_1'  => 'billing_address_1',
                        'address_2'  => 'billing_address_2',
                        'city'       => 'billing_city',
                        'state'      => 'billing_state',
                        'postcode'   => 'billing_postcode',
                        'country'    => 'billing_country',
                        'email'      => 'billing_email',
                        'phone'      => 'billing_phone',
                );

                $billing = array();
                foreach ( $mapping as $field => $metaKey ) {
                        $value = (string) get_user_meta( $customerId, $metaKey, true );
                        if ( '' !== $value ) {
                                $billing[ $field ] = $value;
                        }
                }

                if ( $billing !== array() ) {
                        $order->set_address( $billing, 'billing' );
                        $order->set_address( $billing, 'shipping' );
                }
        }

        private function payUrl( int $orderId ): string {
                $order = wc_get_order( $orderId );

                if ( $order instanceof \WC_Order && method_exists( $order, 'get_checkout_payment_url' ) ) {
                        $payUrl = $order->get_checkout_payment_url();

                        if ( is_string( $payUrl ) && '' !== $payUrl ) {
                                return $payUrl;
                        }
                }

                return '';
        }

        /**
         * @return array<string,mixed>|null
         */
        private function linkFor( int $quoteId ): ?array {
                global $wpdb;

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_order_links' ) . ' WHERE quote_id = %d', $quoteId ), ARRAY_A );

                return is_array( $row ) ? $row : null;
        }

        /**
         * WooCommerce-side reconciliation: find an order carrying this quote's
         * reference meta (HPOS-safe via wc_get_orders).
         */
        public function findExistingOrderId( int $quoteId ): int {
                if ( ! function_exists( 'wc_get_orders' ) ) {
                        return 0;
                }

                $orders = wc_get_orders(
                        array(
                                'limit'      => 1,
                                'meta_key'   => '_tss_quote_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- required for reconciliation
                                'meta_value' => (string) $quoteId, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- required for reconciliation
                        )
                );

                if ( is_array( $orders ) && isset( $orders[0] ) && $orders[0] instanceof \WC_Order ) {
                        return (int) $orders[0]->get_id();
                }

                return 0;
        }

        private function completeLink( int $quoteId, int $orderId ): void {
                global $wpdb;

                $wpdb->replace(
                        Schema::tableName( 'tss_order_links' ),
                        array(
                                'quote_id'     => $quoteId,
                                'order_id'     => $orderId,
                                'conversion_key' => hash( 'sha256', 'tss-quote-' . $quoteId ),
                                'status'       => 'complete',
                                'completed_at' => Utils::nowDb(),
                        ),
                        array( '%d', '%d', '%s', '%s', '%s' )
                );
        }

        /**
         * Hourly recovery sweep for interrupted conversions.
         *
         * @return array{reconciled:int,failed:int}
         */
        public function recoverySweep(): array {
                global $wpdb;

                $cutoff = gmdate( 'Y-m-d H:i:s', time() - self::STALE_SECONDS );

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_order_links' ) . " WHERE status = 'converting' AND created_at < %s", $cutoff ), ARRAY_A );

                $reconciled = 0;
                $failed     = 0;

                if ( is_array( $rows ) ) {
                        foreach ( $rows as $row ) {
                                $quoteId = (int) $row['quote_id'];
                                $orderId = $this->findExistingOrderId( $quoteId );

                                if ( $orderId > 0 ) {
                                        $this->completeLink( $quoteId, $orderId );
                                        $this->quotes->markOrdered( $quoteId, $orderId );
                                        ++$reconciled;
                                } else {
                                        $wpdb->update(
                                                Schema::tableName( 'tss_order_links' ),
                                                array( 'status' => 'failed' ),
                                                array( 'quote_id' => $quoteId ),
                                                array( '%s' ),
                                                array( '%d' )
                                        );
                                        ++$failed;
                                }
                        }
                }

                return array( 'reconciled' => $reconciled, 'failed' => $failed );
        }
}
