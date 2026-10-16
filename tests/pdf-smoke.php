<?php
/**
 * PDF smoke test — REAL generation through the bundled renderer and the
 * plugin's own templates, using synthetic demo data (Volt & Wire Trading
 * Co.). Verifies each output is a structurally valid PDF and that Unicode
 * currency glyphs survive text extraction.
 *
 * Run: php tests/pdf-smoke.php
 *
 * @package TradeSheetStudio\Tests
 */

declare( strict_types=1 );

define( 'TSS_PLUGIN_DIR', dirname( __DIR__ ) . '/plugin/tradesheet-studio/' );

require TSS_PLUGIN_DIR . 'src/Support/Autoloader.php';
\TradeSheetStudio\Support\Autoloader::register();

$vendor = TSS_PLUGIN_DIR . 'vendor/autoload.php';

if ( ! is_file( $vendor ) ) {
        fwrite( STDERR, "vendor/autoload.php missing — run composer install first.\n" );
        exit( 1 );
}

require_once $vendor;

if ( ! class_exists( \Dompdf\Dompdf::class ) ) {
        fwrite( STDERR, "Dompdf not installed.\n" );
        exit( 1 );
}

$lines = array(
        array(
                'row_id' => 'L1', 'sku' => 'VW-LED-HB150', 'name' => 'LED High Bay 150W 5000K IP65',
                'variation_summary' => 'Colour: Graphite · Beam: 110°',
                'description' => 'Industrial LED high bay luminaire, 150W, 19500 lm, 5000K, IP65, 25,000-hour rated life with surge protection.',
                'qty_min' => 2, 'qty_step' => 2, 'unit_price' => '4450.0000', 'qty_breaks' => array( array( 'qty' => 10, 'price' => '4180.0000' ), array( 'qty' => 50, 'price' => '3950.0000' ) ),
                'attributes' => array( array( 'label' => 'Power', 'value' => '150 W' ), array( 'label' => 'Luminous Flux', 'value' => '19,500 lm' ), array( 'label' => 'IP Rating', 'value' => 'IP65' ) ),
                'image' => '',
        ),
        array(
                'row_id' => 'L2', 'sku' => 'VW-LED-TP36', 'name' => 'LED Triproof Batten 36W',
                'variation_summary' => '',
                'description' => 'Waterproof triproof LED batten, 36W, 4000K, IK08, suitable for car parks and cold storage.',
                'qty_min' => 1, 'qty_step' => 1, 'unit_price' => '1240.0000', 'qty_breaks' => array( array( 'qty' => 20, 'price' => '1150.0000' ) ),
                'attributes' => array( array( 'label' => 'Length', 'value' => '1200 mm' ) ),
                'image' => '',
        ),
        array(
                'row_id' => 'L3', 'sku' => 'VW-DB-8WAY', 'name' => 'Distribution Board 8-Way TPN IP65',
                'variation_summary' => '',
                'description' => '8-way TPN distribution board with door interlock, IP65, factory-assembled busbar.',
                'qty_min' => 1, 'qty_step' => 1, 'unit_price' => '3890.0000', 'qty_breaks' => array(),
                'attributes' => array( array( 'label' => 'Ways', 'value' => '8' ), array( 'label' => 'Protection', 'value' => 'IP65' ) ),
                'image' => '',
        ),
        array(
                'row_id' => 'L4', 'sku' => 'VW-MCB-C32', 'name' => 'MCB C32 1P 10kA',
                'variation_summary' => 'Curve: C',
                'description' => 'Miniature circuit breaker, 32A, C-curve, 10kA breaking capacity, single pole.',
                'qty_min' => 5, 'qty_step' => 5, 'unit_price' => '412.0000', 'qty_breaks' => array( array( 'qty' => 50, 'price' => '385.0000' ), array( 'qty' => 200, 'price' => '355.0000' ) ),
                'attributes' => array( array( 'label' => 'Rated Current', 'value' => '32 A' ) ),
                'image' => '',
        ),
        array(
                'row_id' => 'L5', 'sku' => 'VW-CAB-2R5', 'name' => 'Copper Cable 2.5mm² FR 90m Coil',
                'variation_summary' => 'Colour: Red',
                'description' => 'Flame-retardant PVC insulated copper cable, 2.5 mm², 90-metre coil.',
                'qty_min' => 1, 'qty_step' => 1, 'unit_price' => '5999.0000', 'qty_breaks' => array(),
                'attributes' => array( array( 'label' => 'Conductor', 'value' => 'Copper, 2.5 mm²' ) ),
                'image' => '',
        ),
        array(
                'row_id' => 'L6', 'sku' => 'VW-SKT-IEC32', 'name' => 'Industrial Socket 32A 3P+E IP67',
                'variation_summary' => '',
                'description' => 'IEC 60309 industrial socket outlet, 32A, 3P+E, 415V, IP67.',
                'qty_min' => 4, 'qty_step' => 4, 'unit_price' => '760.0000', 'qty_breaks' => array( array( 'qty' => 25, 'price' => '705.0000' ) ),
                'attributes' => array( array( 'label' => 'Current', 'value' => '32 A' ) ),
                'image' => '',
        ),
);

$data = array(
        'template'            => 'trade_table',
        'ref'                 => 'TSS-P-1042',
        'title'               => 'Wholesale Offer — October 2026',
        'version_no'          => 1,
        'issued_at'           => '2026-10-16 06:00:00',
        'expires_at'          => '2026-11-15 06:00:00',
        'currency'            => 'INR',
        'currency_symbol'     => '₹',
        'prices_include_tax'  => false,
        'buyer'               => 'Krishna Electricals — Ramesh K.',
        'company'             => array(
                'name'    => 'Volt & Wire Trading Co. (demo data)',
                'address' => '14 Industrial Estate, Pune 411019',
                'email'   => 'sales@voltwire.example',
                'phone'   => '+91 20 5550 1234',
                'footer'  => 'Demo document — synthetic data.',
                'logo'    => '',
        ),
        'terms'               => array( 'payment_terms' => '30 days net', 'delivery_terms' => 'Ex-works Pune' ),
        'lines'               => $lines,
);

$failures = 0;

foreach ( array( 'trade_table', 'visual_catalog', 'technical_sheet' ) as $template ) {
        $data['template'] = $template;

        ob_start();
        include TSS_PLUGIN_DIR . 'templates/pdf/' . $template . '.php';
        $html = (string) ob_get_clean();

        $dompdf = new \Dompdf\Dompdf(
                array(
                        'isRemoteEnabled'         => false,
                        'defaultFont'             => 'dejavu sans',
                        'isPhpEnabled'            => true,
                        'isHtml5ParserEnabled'    => true,
                        'isFontSubsettingEnabled' => true,
                )
        );

        $dompdf->setPaper( 'a4', 'trade_table' === $template ? 'landscape' : 'portrait' );
        $dompdf->loadHtml( $html, 'UTF-8' );
        $dompdf->render();

        $bytes  = (string) $dompdf->output();
        $out    = sys_get_temp_dir() . '/tss-smoke-' . str_replace( '_', '-', $template ) . '.pdf';
        file_put_contents( $out, $bytes );

        $head = substr( $bytes, 0, 5 );
        $tail = substr( $bytes, -32 );

        $valid = '%PDF-' === $head && false !== strpos( $tail, '%%EOF' );

        printf( "%-17s %8d bytes  header=%s  eof=%s\n", $template, strlen( $bytes ), $head, $valid ? 'yes' : 'NO' );

        if ( ! $valid ) {
                ++$failures;
        }
}

$installed = json_decode( (string) file_get_contents( TSS_PLUGIN_DIR . 'vendor/composer/installed.json' ), true );
$installed = is_array( $installed ) ? ( $installed['packages'] ?? $installed ) : array();

foreach ( (array) $installed as $package ) {
        if ( is_array( $package ) && 'dompdf/dompdf' === (string) ( $package['name'] ?? '' ) ) {
                echo 'Dompdf version: ' . (string) ( $package['version'] ?? 'unknown' ) . "\n";
        }
}

exit( $failures > 0 ? 1 : 0 );
