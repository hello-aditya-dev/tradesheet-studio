<?php
/**
 * Integration test harness — REAL end-to-end workflows inside the WordPress
 * test installation (WordPress 7.1.3, WooCommerce 11.2.1, SQLite).
 *
 * Run: wp eval-file tests/integration/golden-path.php
 *
 * Covers the golden path plus failure scenarios:
 *  1. Price resolution through WooCommerce data
 *  2. Packet compose -> preflight -> publish (immutable snapshot + checksum)
 *  3. Document generation (real PDF + CSV in private storage)
 *  4. CSV round-trip with tampered/altered quantities
 *  5. Buyer token issue / validate / revoke; cross-customer denial
 *  6. Quote request -> integrity report -> approve -> accept -> convert
 *  7. Idempotency: second conversion returns the SAME order
 *  8. Price-change discrepancy detection
 *  9. Merchant revision flow: superseded revision cannot be accepted
 * 10. Failure cases: deleted product, qty below min, expired offer,
 *     malformed CSV, formula injection inert, duplicate rows, unknown rows
 * 11. Privacy export/erase
 *
 * NOTE: no strict_types declare — runs via `wp eval-file`.
 *
 * @package TradeSheetStudio\Tests
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['tss_it_pass'] = 0;
$GLOBALS['tss_it_fail'] = 0;
$GLOBALS['tss_it_failures'] = array();

function tss_check( string $label, bool $condition ): void {
        if ( $condition ) {
                ++$GLOBALS['tss_it_pass'];
                echo "  ok: {$label}\n";
        } else {
                ++$GLOBALS['tss_it_fail'];
                $GLOBALS['tss_it_failures'][] = $label;
                echo "  FAIL: {$label}\n";
        }
}

function tss_check_eq( string $label, mixed $expected, mixed $actual ): void {
        tss_check( $label . ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')', $expected === $actual );
}

function tss_throws( string $label, callable $fn ): void {
        try {
                $fn();
                tss_check( $label . ' — threw', false );
        } catch ( \Throwable $e ) {
                tss_check( $label . ' — threw: ' . substr( $e->getMessage(), 0, 90 ), true );
        }
}

function tss_section( string $name ): void {
        echo "== {$name} ==\n";
}

$plugin = \TradeSheetStudio\Core\Plugin::instance();

tss_section( '1. Pricing through WooCommerce data' );

$hbParent   = (int) wc_get_product_id_by_sku( 'VW-LED-HB' );
$hb150      = (int) wc_get_product_id_by_sku( 'VW-LED-HB150' );
$mcbParent  = (int) wc_get_product_id_by_sku( 'VW-MCB' );
$mcbC32     = (int) wc_get_product_id_by_sku( 'VW-MCB-C32' );
$tpParent   = (int) wc_get_product_id_by_sku( 'VW-LED-TP' );
$tp36       = (int) wc_get_product_id_by_sku( 'VW-LED-TP36' );
$krishna    = (int) get_user_by( 'login', 'krishna.elec' )->ID;
$shakti     = (int) get_user_by( 'login', 'shakti.ind' )->ID;

$r = $plugin->pricing()->resolveForCustomer( $krishna, $hbParent, $hb150, 4 );
tss_check_eq( 'customer-specific override', '4399.0000', $r->unitPrice );
tss_check_eq( 'override min qty', 2, $r->minQty );

$r = $plugin->pricing()->resolveForCustomer( $shakti, $hbParent, $hb150, 4 );
tss_check_eq( 'Tier B book-wide 5%', '4939.0500', $r->unitPrice );

tss_section( '2. Compose packet, preflight, publish' );

$packetId = $plugin->packets()->create(
        $krishna,
        'Q4 Restock Offer — Krishna Electricals',
        1,
        'INR',
        gmdate( 'Y-m-d H:i:s', strtotime( '+30 days', current_time( 'timestamp' ) ) ),
        array( 'payment_terms' => '30 days net', 'delivery_terms' => 'Ex-works Pune', 'notes' => 'Demo packet (synthetic data).' ),
        get_current_user_id()
);
tss_check( 'packet created', $packetId > 0 );

$lines = array(
        array( $plugin->packets()->addDraftLine( $packetId, $hbParent, $hb150, '4399.0000', 'resolved', 2, 2, array( array( 'qty' => 50, 'price' => '4150.0000' ) ) ), 'hb' ),
        array( $plugin->packets()->addDraftLine( $packetId, $mcbParent, $mcbC32, '412.0000', 'resolved', 5, 5, array( array( 'qty' => 50, 'price' => '385.0000' ) ) ), 'mcb' ),
        array( $plugin->packets()->addDraftLine( $packetId, $tpParent, $tp36, '1332.0000', 'resolved', 1, 1, array() ), 'tp' ),
);
tss_check( '3 draft lines added', 3 === count( $plugin->packets()->draftLines( $packetId ) ) );

$preflight = $plugin->snapshots()->preflight( $packetId );
tss_check( 'preflight has no blocking errors', $preflight['errors'] === array() );

$published = $plugin->snapshots()->publish( $packetId, get_current_user_id() );
tss_check( 'published as version 1', 1 === $published['version_no'] );
tss_check( 'checksum is 64 hex chars', preg_match( '/^[0-9a-f]{64}$/', $published['checksum'] ) === 1 );
tss_check( 'share token issued', strlen( $published['token'] ) === 64 );

$version = $plugin->packets()->version( (int) $published['version_id'] );
$snapshot = json_decode( (string) $version['snapshot'], true );
tss_check_eq( 'snapshot records negotiated price', '4399.0000', $snapshot['lines'][0]['unit_price'] );
tss_check_eq( 'snapshot currency', 'INR', $snapshot['packet']['currency'] );
tss_check( 'snapshot tax context present', isset( $snapshot['tax_context']['hash'] ) );

// Immutability: publishing a revision must NOT alter version 1.
$checksumV1 = (string) $version['content_checksum'];

tss_section( '3. Documents generated (real files)' );

$docs = $plugin->documents()->generateForVersion( (int) $published['version_id'], get_current_user_id(), 'trade_table' );
$docRows = $plugin->documents()->documentsForVersion( (int) $published['version_id'] );
$pdfRow = null; $csvRow = null; $zipRow = null;
foreach ( $docRows as $doc ) {
        if ( 'pdf' === $doc['type'] ) { $pdfRow = $doc; }
        if ( 'csv' === $doc['type'] ) { $csvRow = $doc; }
        if ( 'zip' === $doc['type'] ) { $zipRow = $doc; }
}
tss_check( 'PDF document row exists', is_array( $pdfRow ) );
tss_check( 'CSV document row exists', is_array( $csvRow ) );

$storage = \TradeSheetStudio\Core\Installer::storageDir();
$pdfPath = $storage . 'packet-' . $packetId . '/version-' . (int) $published['version_id'] . '/' . basename( (string) $pdfRow['file_path'] );
tss_check( 'PDF file exists in private storage', is_file( $pdfPath ) );
if ( is_file( $pdfPath ) ) {
        $head = substr( (string) file_get_contents( $pdfPath ), 0, 5 );
        tss_check_eq( 'PDF magic header', '%PDF-', $head );
        tss_check( 'PDF non-trivial size', (int) $pdfRow['size_bytes'] > 5000 );
        tss_check( 'htaccess denies direct access', is_file( dirname( $storage ) . '/tradesheet-studio-docs/.htaccess' ) || is_file( $storage . '.htaccess' ) );
}

tss_section( '4. CSV round-trip' );

$csvContent = $plugin->csv()->export( (int) $published['version_id'] );
tss_check( 'CSV export contains header', str_contains( $csvContent, 'RowID,SKU,Product' ) );
tss_check( 'CSV export contains MCB SKU', str_contains( $csvContent, 'VW-MCB-C32' ) );

// Buyer edits quantities (10 for L-hb, 55 for L-mcb... wait steps: mcb step 5, min 5).
preg_match_all( '/^L(\d+),/m', $csvContent, $m );
$hbLineId = null; $mcbLineId = null; $tpLineId = null;
foreach ( $plugin->packets()->versionLines( (int) $published['version_id'] ) as $line ) {
        if ( 'VW-LED-HB150' === $line['sku'] ) { $hbLineId = (int) $line['id']; }
        if ( 'VW-MCB-C32' === $line['sku'] ) { $mcbLineId = (int) $line['id']; }
        if ( 'VW-LED-TP36' === $line['sku'] ) { $tpLineId = (int) $line['id']; }
}

$edited = "# TradeSheet Studio Order Sheet\nRowID,SKU,Product,Variation,UnitPrice,Qty,MinQty,Step,QtyBreaks\n";
$edited .= 'L' . $hbLineId . ',VW-LED-HB150,LED High Bay,,999999.99,10,2,2,' . "\n"; // tampered price + qty 10
$edited .= 'L' . $mcbLineId . ',VW-MCB-C32,MCB C32,,1.00,55,5,5,' . "\n";

$parse = $plugin->csv()->parse( $edited );
tss_check( 'tampered CSV parses', $parse['ok'] );
$validated = $plugin->csv()->validateAgainstVersion( $parse['rows'], (int) $published['version_id'] );
tss_check( 'tampered CSV validates', $validated['errors'] === array() );
tss_check_eq( 'server price ignores file price (hb)', '4399.0000', $validated['rows'][0]['unit_price'] );
tss_check_eq( 'server price ignores file price (mcb)', '412.0000', $validated['rows'][1]['unit_price'] );
tss_check_eq( 'qty break honored in effective price constant source', 55, $validated['rows'][1]['qty'] );

tss_section( '5. Buyer token lifecycle + cross-customer denial' );

$token = $published['token'];
tss_check( 'valid token validates', $plugin->buyerAccess()->validateToken( $packetId, $token ) );

wp_set_current_user( $krishna );
$packet = $plugin->packets()->get( $packetId );
tss_check( 'owner access (krishna) granted', 'owner' === $plugin->buyerAccess()->accessFor( $packet, null ) );

wp_set_current_user( $shakti );
tss_check( 'cross-customer access denied', null === $plugin->buyerAccess()->accessFor( $packet, 'wrong-token-value' ) );
tss_check( 'cross-customer without token denied', null === $plugin->buyerAccess()->accessFor( $packet, '' ) );
wp_set_current_user( 0 );

tss_check( 'invalid token denied', ! $plugin->buyerAccess()->validateToken( $packetId, str_repeat( 'a', 64 ) ) );

$plugin->buyerAccess()->revokeForPacket( $packetId );
tss_check( 'revoked token denied', ! $plugin->buyerAccess()->validateToken( $packetId, $token ) );

// Re-issue for the quote flow.
$token2 = $plugin->buyerAccess()->issueForPacket( $packetId );
tss_check( 'fresh token validates', $plugin->buyerAccess()->validateToken( $packetId, $token2 ) );

tss_section( '6. Quote request -> approve -> accept -> order' );

wp_set_current_user( $krishna );
try {
        $quote = $plugin->quotes()->createRequest(
                $packetId,
                array(
                        array( 'line_id' => $hbLineId, 'qty' => 10 ),
                        array( 'line_id' => $mcbLineId, 'qty' => 55 ),
                        array( 'line_id' => $tpLineId, 'qty' => 0 ),
                ),
                'Please confirm lead time for the high bays. (synthetic buyer note)'
        );
        tss_check( 'quote request created', is_array( $quote ) && $quote['id'] > 0 );
} catch ( \Throwable $e ) {
        tss_check( 'quote request created: ' . $e->getMessage(), false );
        $quote = null;
}

if ( is_array( $quote ) ) {
        $report = json_decode( (string) $quote['discrepancy_report'], true );
        tss_check( 'integrity report: no blocking findings', (int) $report['blocking_count'] === 0 );

        $quoteLines = $plugin->quotes()->lines( (int) $quote['id'] );
        tss_check_eq( 'quote line count', 2, count( $quoteLines ) );
        $hbQuoteLine = null; $mcbQuoteLine = null;
        foreach ( $quoteLines as $line ) {
                if ( 'VW-LED-HB150' === $line['sku'] ) { $hbQuoteLine = $line; }
                if ( 'VW-MCB-C32' === $line['sku'] ) { $mcbQuoteLine = $line; }
        }
        // MCB at qty 55 crosses the 50+ break -> 385.
        tss_check_eq( 'mcb qty-break effective price', '385.0000', $mcbQuoteLine['unit_price'] );

        wp_set_current_user( 1 );
        $approved = $plugin->quotes()->approve( (int) $quote['id'], 'Honor quoted prices (demo).', get_current_user_id() );
        tss_check_eq( 'quote approved', 'approved', $approved['status'] );

        $accepted = $plugin->quotes()->accept( (int) $quote['id'] );
        tss_check_eq( 'quote accepted', 'accepted', $accepted['status'] );

        tss_section( '7. Idempotent order conversion' );

        $first = $plugin->orders()->convert( (int) $quote['id'], 1 );
        tss_check( 'order created', ! empty( $first['order_id'] ) );
        $orderId = (int) $first['order_id'];

        $second = $plugin->orders()->convert( (int) $quote['id'], 1 );
        tss_check( 'second conversion flagged as already', ! empty( $second['already'] ) );
        tss_check_eq( 'same order returned', $orderId, (int) ( $second['order_id'] ?? 0 ) );

        $orders = wc_get_orders( array( 'meta_key' => '_tss_quote_id', 'meta_value' => (string) $quote['id'], 'limit' => 10 ) );
        tss_check_eq( 'exactly one WooCommerce order for this quote', 1, is_array( $orders ) ? count( $orders ) : 0 );

        $order = wc_get_order( $orderId );
        tss_check( 'order exists in WooCommerce', $order instanceof \WC_Order );
        if ( $order instanceof \WC_Order ) {
                tss_check_eq( 'order currency', 'INR', $order->get_currency() );
                tss_check_eq( 'order line count', 2, count( $order->get_items() ) );

                $linePrices = array();
                foreach ( $order->get_items() as $item ) {
                        $linePrices[ (string) $item->get_product()->get_sku() ] = (string) $item->get_total();
                }
                // High bay: 10 x 4399 = 43990; MCB: 55 x 385 = 21175 (WooCommerce stores item totals at 4dp).
                tss_check_eq( 'negotiated hb line total preserved', '43990.0000', $linePrices['VW-LED-HB150'] );
                tss_check_eq( 'negotiated mcb line total preserved', '21175.0000', $linePrices['VW-MCB-C32'] );
                tss_check_eq( 'quote ref on order meta', (string) $quote['ref'], (string) $order->get_meta( '_tss_quote_ref' ) );
                tss_check( 'snapshot checksum on order meta', '' !== (string) $order->get_meta( '_tss_snapshot_checksum' ) );
                $item = $order->get_items();
                $firstItem = array_shift($item);
                tss_check( 'negotiated unit price meta on line', '' !== (string) $firstItem->get_meta( '_tss_negotiated_unit_price' ) );
        }

        tss_section( '8. Price-change discrepancy detection' );

        // New packet for the changed-price scenario.
        $packet2 = $plugin->packets()->create(
                $shakti,
                'Price-Drift Probe — Shakti',
                2,
                'INR',
                gmdate( 'Y-m-d H:i:s', strtotime( '+30 days', current_time( 'timestamp' ) ) ),
                array(),
                1
        );
        $plugin->packets()->addDraftLine( $packet2, $hbParent, $hb150, '4939.0500', 'resolved', 1, 1, array() );
        $pub2 = $plugin->snapshots()->publish( $packet2, 1 );

        // Store price rises 12%.
        $hbProduct = wc_get_product( $hb150 );
        $oldPrice = (string) $hbProduct->get_price();
        $hbProduct->set_regular_price( (string) ( (float) $oldPrice * 1.12 ) );
        $hbProduct->save();

        $shaktiPacket = $plugin->packets()->get( $packet2 );
        wp_set_current_user( $shakti );
        $quote2 = $plugin->quotes()->createRequest( $packet2, array( array( 'line_id' => $plugin->packets()->versionLines( (int) $pub2['version_id'] )[0]['id'], 'qty' => 4 ) ) );
        $report2 = json_decode( (string) $quote2['discrepancy_report'], true );
        $types = array();
        foreach ( (array) ( $report2['items'] ?? array() ) as $item ) {
                $types[] = (string) ( $item['type'] ?? '' );
        }
        tss_check( 'PRICE_CHANGED detected', in_array( 'PRICE_CHANGED', $types, true ) );

        // Restore the store price.
        $hbProduct->set_regular_price( $oldPrice );
        $hbProduct->save();

        tss_section( '9. Merchant revision supersedes original' );

        // Krishna asks again on a fresh packet.
        $packet3 = $plugin->packets()->create( $krishna, 'Revision Demo', 1, 'INR', gmdate( 'Y-m-d H:i:s', strtotime( '+30 days', current_time( 'timestamp' ) ) ), array(), 1 );
        $line3 = $plugin->packets()->addDraftLine( $packet3, $mcbParent, $mcbC32, '412.0000', 'resolved', 5, 5, array() );
        $pub3 = $plugin->snapshots()->publish( $packet3, 1 );
        $vline3 = $plugin->packets()->versionLines( (int) $pub3['version_id'] )[0];

        wp_set_current_user( $krishna );
        $quote3 = $plugin->quotes()->createRequest( $packet3, array( array( 'line_id' => (int) $vline3['id'], 'qty' => 10 ) ) );

        wp_set_current_user( 1 );
        // Merchant revises the price upward.
        $revision = $plugin->quoteRevisions()->revise(
                (int) $quote3['id'],
                array( array( 'quote_line_id' => (int) $plugin->quotes()->lines( (int) $quote3['id'] )[0]['id'], 'unit_price' => '425.0000', 'note' => 'Copper price adjustment (demo).' ) ),
                'Revised unit price for the MCB line.',
                1
        );
        tss_check_eq( 'revision status', 'revised', $revision['status'] );
        tss_check_eq( 'revision number R2', 2, (int) $revision['revision_no'] );

        $originalAfter = $plugin->quotes()->get( (int) $quote3['id'] );
        tss_check_eq( 'original superseded', 'superseded', $originalAfter['status'] );

        tss_throws( 'accepting superseded revision rejected', function () use ( $plugin, $quote3 ) {
                $plugin->quotes()->accept( (int) $quote3['id'] );
        } );

        wp_set_current_user( $krishna );
        $acceptedRev = $plugin->quotes()->accept( (int) $revision['id'] );
        tss_check_eq( 'new revision acceptable', 'accepted', $acceptedRev['status'] );

        $converted3 = $plugin->orders()->convert( (int) $revision['id'], 1 );
        $order3 = wc_get_order( (int) $converted3['order_id'] );
        tss_check( 'revised-price order created', $order3 instanceof \WC_Order );
        if ( $order3 instanceof \WC_Order ) {
                $items3 = $order3->get_items();
                $firstItem3 = array_shift( $items3 );
                $t = (string) $firstItem3->get_total();
                tss_check_eq( 'order uses REVISED negotiated price (10 x 425)', '4250.0000', $t );
                tss_check_eq( 'revised unit price on line meta', '425.0000', (string) $firstItem3->get_meta( '_tss_negotiated_unit_price' ) );
        }

        tss_section( '10. Failure scenarios' );

        // Deleted product blocks preflight publication of a new packet.
        $tmpProduct = new WC_Product_Simple();
        $tmpProduct->set_name( 'Doomed Product' );
        $tmpProduct->set_regular_price( '100' );
        $tmpProduct->set_sku( 'VW-DOOMED-1' );
        $tmpId = $tmpProduct->save();

        $packet4 = $plugin->packets()->create( $krishna, 'Deleted Product Probe', 1, 'INR', null, array(), 1 );
        $plugin->packets()->addDraftLine( $packet4, (int) $tmpId, 0, '90.0000', 'resolved', 1, 1, array() );

        // Delete the product (trash + force).
        wp_delete_post( (int) $tmpId, true );
        // Ensure the product object is gone from lookups.
        wp_cache_flush();

        $preflight4 = $plugin->snapshots()->preflight( $packet4 );
        $codes = array();
        foreach ( $preflight4['errors'] as $error ) { $codes[] = (string) $error['code']; }
        tss_check( 'deleted product blocks publication (PRODUCT_MISSING)', in_array( 'PRODUCT_MISSING', $codes, true ) || in_array( 'PRODUCT_UNAVAILABLE', $codes, true ) );
        tss_throws( 'publish with deleted product refused', function () use ( $plugin, $packet4 ) {
                $plugin->snapshots()->publish( $packet4, 1 );
        } );

        // Quantity below minimum.
        tss_throws( 'qty below minimum rejected', function () use ( $plugin, $packetId, $hbLineId ) {
                $plugin->quotes()->createRequest( $packetId, array( array( 'line_id' => $hbLineId, 'qty' => 1 ) ) );
        } );

        // Quantity violating step.
        tss_throws( 'qty violating step rejected', function () use ( $plugin, $packetId, $mcbLineId ) {
                $plugin->quotes()->createRequest( $packetId, array( array( 'line_id' => $mcbLineId, 'qty' => 7 ) ) );
        } );

        // Malformed CSV: unknown row + duplicate row + qty below minimum.
        $badCsv = "RowID,SKU,Product,Variation,UnitPrice,Qty,MinQty,Step,QtyBreaks\n"
                . 'L999999,NOPE,Unknown,,1,5,1,1,' . "\n"
                . 'L' . $hbLineId . ',VW-LED-HB150,LED High Bay,,4399.00,1,2,2,' . "\n"
                . 'L' . $hbLineId . ',VW-LED-HB150,LED High Bay,,4399.00,4,2,2,' . "\n";
        $badParse = $plugin->csv()->parse( $badCsv );
        tss_check( 'malformed CSV parses to rows', $badParse['ok'] );
        $badValid = $plugin->csv()->validateAgainstVersion( $badParse['rows'], (int) $published['version_id'] );
        $badCodes = array();
        foreach ( $badValid['errors'] as $error ) { $badCodes[] = (string) $error['code']; }
        tss_check( 'unknown row detected', in_array( 'UNKNOWN_ROW', $badCodes, true ) );
        tss_check( 'duplicate row detected', in_array( 'DUPLICATE_ROW', $badCodes, true ) );
        tss_check( 'qty below min detected in CSV', in_array( 'QTY_BELOW_MIN', $badCodes, true ) );

        // Formula injection payload is inert data; a formula in the Qty column
        // is rejected as an invalid quantity (never executed).
        $injCsv = "RowID,SKU,Product,Variation,UnitPrice,Qty,MinQty,Step,QtyBreaks\n"
                . 'L' . $hbLineId . ',=HYPERLINK("http://evil.example"),LED High Bay,,=cmd|1,=1+1,2,2,' . "\n";
        $injParse = $plugin->csv()->parse( $injCsv );
        tss_check( 'formula-injection CSV parsed as inert data', $injParse['ok'] );
        $injValid = $plugin->csv()->validateAgainstVersion( $injParse['rows'], (int) $published['version_id'] );
        tss_check( 'injection qty rejected as invalid quantity', in_array( 'QTY_INVALID', array_map( static function ( $e ) { return (string) $e['code']; }, $injValid['errors'] ), true ) );

        // Expired offer: fresh packet, expire through the machine, quote rejected.
        $packet5 = $plugin->packets()->create( $krishna, 'Expiry Probe', 1, 'INR', gmdate( 'Y-m-d H:i:s', strtotime( '+30 days', current_time( 'timestamp' ) ) ), array(), 1 );
        $plugin->packets()->addDraftLine( $packet5, $mcbParent, $mcbC32, '412.0000', 'resolved', 5, 5, array() );
        $pub5 = $plugin->snapshots()->publish( $packet5, 1 );
        $vline5 = $plugin->packets()->versionLines( (int) $pub5['version_id'] )[0];

        $plugin->packets()->update( $packet5, array( 'valid_until' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day', current_time( 'timestamp' ) ) ) ) );
        $plugin->packets()->setStatus( $packet5, 'expired', 1 );
        $expiredPacket = $plugin->packets()->get( $packet5 );
        tss_check_eq( 'expired packet state', 'expired', (string) $expiredPacket['status'] );
        tss_throws( 'quote on expired packet rejected', function () use ( $plugin, $packet5, $vline5 ) {
                $plugin->quotes()->createRequest( $packet5, array( array( 'line_id' => (int) $vline5['id'], 'qty' => 10 ) ) );
        } );

        // Oversized CSV (rows beyond limit).
        $bigCsv = "RowID,SKU,Product,Variation,UnitPrice,Qty,MinQty,Step,QtyBreaks\n";
        for ( $i = 0; $i < 2500; $i++ ) {
                $bigCsv .= 'L' . ( $i + 1 ) . ',S,P,,1,1,1,1,' . "\n";
        }
        $bigParse = $plugin->csv()->parse( $bigCsv );
        tss_check( 'oversized CSV rejected', ! $bigParse['ok'] );

        tss_section( '11. Privacy export/erase' );

        $privacy = new \TradeSheetStudio\Privacy\PrivacyService();
        $export = $privacy->export( 'krishna@krishnaelectricals.example' );
        tss_check( 'privacy export produced groups', count( $export['data'] ) >= 2 );
        $foundQuote = false;
        foreach ( $export['data'] as $group ) {
                foreach ( (array) $group['data'] as $entry ) {
                        if ( str_contains( (string) $entry['value'], (string) $quote['ref'] ) ) {
                                $foundQuote = true;
                        }
                }
        }
        tss_check( 'export contains quote reference', $foundQuote );

        $erase = $privacy->erase( 'krishna@krishnaelectricals.example' );
        tss_check( 'erase removed tokens', (int) $erase['items_removed'] >= 1 );

        // Snapshot immutability final check: version 1 untouched by everything.
        $versionFinal = $plugin->packets()->version( (int) $published['version_id'] );
        tss_check_eq( 'issued snapshot checksum unchanged end-to-end', $checksumV1, (string) $versionFinal['content_checksum'] );

        // Dashboard metrics are real records.
        $metrics = $plugin->packets()->dashboardMetrics();
        tss_check( 'dashboard orders metric >= 2', (int) $metrics['orders'] >= 2 );
        tss_check( 'dashboard ordered packets >= 2', (int) $metrics['ordered'] >= 2 );
}

wp_set_current_user( 0 );

echo "\n";
printf( "INTEGRATION TESTS: %d passed, %d failed\n", $GLOBALS['tss_it_pass'], $GLOBALS['tss_it_fail'] );

if ( $GLOBALS['tss_it_fail'] > 0 ) {
        echo "Failed:\n";
        foreach ( $GLOBALS['tss_it_failures'] as $failure ) {
                echo " - {$failure}\n";
        }
        exit( 1 );
}
exit( 0 );
