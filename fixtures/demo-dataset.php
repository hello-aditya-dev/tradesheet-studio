<?php
/**
 * Synthetic demo dataset — Volt & Wire Trading Co. (industrial electrical
 * supplies). ALL data is synthetic and clearly labeled as demo data.
 *
 * Run: wp eval-file fixtures/demo-dataset.php
 *
 * NOTE: no strict_types declare — this file runs through `wp eval-file`,
 * which eval-wraps the source.
 *
 * @package TradeSheetStudio\Fixtures
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create a simple product.
 */
function tss_demo_simple( string $sku, string $name, string $desc, float $price, int $qty = 0 ): int {
        $existing = wc_get_product_id_by_sku( $sku );
        if ( $existing > 0 ) {
                return $existing;
        }

        $product = new WC_Product_Simple();
        $product->set_name( $name );
        $product->set_sku( $sku );
        $product->set_regular_price( (string) $price );
        $product->set_description( $desc );
        $product->set_short_description( tss_demo_short( $desc ) );
        $product->set_manage_stock( $qty > 0 );
        if ( $qty > 0 ) {
                $product->set_stock_quantity( $qty );
        }
        $product->set_status( 'publish' );

        return $product->save();
}

/**
 * Create a variable product with variations.
 *
 * @param array<string,array{sku:string,name:string,price:float,attrs:array<string,string>}> $variants
 */
function tss_demo_variable( string $sku, string $name, string $desc, string $attrName, array $variants ): int {
        $existing = wc_get_product_id_by_sku( $sku );
        if ( $existing > 0 ) {
                return $existing;
        }

        $taxonomy = wc_attribute_taxonomy_name( strtolower( str_replace( ' ', '-', $attrName ) ) );

        $attrId = 0;
        $termIds = array();

        $registered = wc_get_attribute_taxonomies();
        foreach ( $registered as $attr ) {
                if ( 'pa_' . $attr->attribute_name === $taxonomy ) {
                        $attrId = (int) $attr->attribute_id;
                        break;
                }
        }

        if ( 0 === $attrId ) {
                $attrId = (int) wc_create_attribute(
                        array(
                                'name'         => $attrName,
                                'slug'         => str_replace( ' ', '-', strtolower( $attrName ) ),
                                'type'         => 'select',
                                'order_by'     => 'menu_order',
                                'has_archives' => false,
                        )
                );

                register_taxonomy(
                        $taxonomy,
                        array( 'product' ),
                        array( 'hierarchical' => false, 'show_ui' => false, 'query_var' => true )
                );
        } else {
                register_taxonomy(
                        $taxonomy,
                        array( 'product' ),
                        array( 'hierarchical' => false, 'show_ui' => false, 'query_var' => true )
                );
        }

        $attribute = new WC_Product_Attribute();
        $attribute->set_id( $attrId );
        $attribute->set_name( $taxonomy );
        $attribute->set_visible( true );
        $attribute->set_variation( true );

        $optionTerms = array();
        $optionSlugs = array();

        foreach ( $variants as $variant ) {
                foreach ( $variant['attrs'] as $term ) {
                        $slug = sanitize_title( $term );
                        if ( isset( $optionTerms[ $term ] ) ) {
                                continue;
                        }
                        $termId = term_exists( $term, $taxonomy );
                        if ( ! $termId ) {
                                $termId = wp_insert_term( $term, $taxonomy );
                                if ( is_wp_error( $termId ) ) {
                                        continue;
                                }
                        }
                        $termId = is_array( $termId ) ? (int) $termId['term_id'] : (int) $termId;
                        $optionTerms[ $term ] = $termId;
                        $optionSlugs[] = $slug;
                }
        }

        $attribute->set_options( array_values( $optionSlugs ) );

        $product = new WC_Product_Variable();
        $product->set_name( $name );
        $product->set_sku( $sku );
        $product->set_description( $desc );
        $product->set_short_description( tss_demo_short( $desc ) );
        $product->set_attributes( array( $attribute ) );
        $product->set_status( 'publish' );
        $parentId = $product->save();

        foreach ( $variants as $variant ) {
                $variation = new WC_Product_Variation();
                $variation->set_parent_id( $parentId );
                $variation->set_sku( $variant['sku'] );
                $variation->set_regular_price( (string) $variant['price'] );

                $varAttrs = array();
                foreach ( $variant['attrs'] as $term ) {
                        $varAttrs[ $taxonomy ] = sanitize_title( $term );
                }
                $variation->set_attributes( $varAttrs );
                $variation->set_status( 'publish' );
                $variation->save();
        }

        return $parentId;
}

function tss_demo_short( string $desc ): string {
        return mb_substr( $desc, 0, 160 );
}

function tss_demo_customer( string $login, string $email, string $company, string $city ): int {
        $userId = username_exists( $login );
        if ( $userId > 0 ) {
                return (int) $userId;
        }

        $userId = wp_create_user( $login, wp_generate_password( 24 ), $email );
        if ( is_wp_error( $userId ) ) {
                return 0;
        }

        $user = get_userdata( (int) $userId );
        $user->set_role( 'customer' );

        update_user_meta( (int) $userId, 'billing_company', $company );
        update_user_meta( (int) $userId, 'billing_city', $city );
        update_user_meta( (int) $userId, 'billing_country', 'IN' );
        update_user_meta( (int) $userId, 'billing_first_name', explode( ' ', trim( str_replace( array( 'Pvt.', 'Ltd.', 'Electricals', 'Industries' ), '', $company ) ) )[0] ?? 'Trade' );
        update_user_meta( (int) $userId, 'billing_email', $email );

        return (int) $userId;
}

// ------------------------------------------------------------------ //
// Products (synthetic demo data).
// ------------------------------------------------------------------ //

$count = 0;

// LED lighting.
$count += 1; tss_demo_variable(
        'VW-LED-HB',
        'LED High Bay Luminaire',
        'Industrial LED high bay luminaire with die-cast aluminium housing, surge protection and 110° reflector. IP65, 50,000-hour rated life. Suitable for warehouses and factory floors.',
        'Wattage',
        array(
                array( 'sku' => 'VW-LED-HB100', 'name' => 'LED High Bay 100W', 'price' => 3599.0, 'attrs' => array( '100 W' ) ),
                array( 'sku' => 'VW-LED-HB150', 'name' => 'LED High Bay 150W', 'price' => 5199.0, 'attrs' => array( '150 W' ) ),
                array( 'sku' => 'VW-LED-HB200', 'name' => 'LED High Bay 200W', 'price' => 6799.0, 'attrs' => array( '200 W' ) ),
        )
);

$count += 1; tss_demo_variable(
        'VW-LED-TP',
        'LED Triproof Batten',
        'Waterproof triproof LED batten with IK08 impact resistance and IP66 sealing. 4000K neutral white, suitable for car parks, cold storage and food processing areas.',
        'Length',
        array(
                array( 'sku' => 'VW-LED-TP24', 'name' => 'LED Triproof Batten 24W', 'price' => 980.0, 'attrs' => array( '1200 mm' ) ),
                array( 'sku' => 'VW-LED-TP36', 'name' => 'LED Triproof Batten 36W', 'price' => 1480.0, 'attrs' => array( '1500 mm' ) ),
                array( 'sku' => 'VW-LED-TP48', 'name' => 'LED Triproof Batten 48W', 'price' => 1890.0, 'attrs' => array( '1800 mm' ) ),
        )
);

$count += 1; tss_demo_simple( 'VW-LED-FL600', 'LED Floodlight 600W 5700K', 'High-output LED floodlight for facade and sports-field lighting, 600W, 96,000 lm, IP66 with stainless steel bracket.', 18999.00 );
$count += 1; tss_demo_simple( 'VW-LED-DP18', 'LED Downlight Panel 18W Round', 'Recessed round LED panel, 18W, 3000K warm white, flicker-free driver, 24° beam for retail interiors.', 549.00, 400 );
$count += 1; tss_demo_simple( 'VW-LED-ST50', 'LED Street Light 50W IP66', 'LED street luminaire, 50W, 5700K, tool-less access, photocell-ready, 30,000-hour warranty option.', 2899.00, 150 );
$count += 1; tss_demo_simple( 'VW-LED-BC9', 'LED Batten 9W Emergency', 'Emergency LED batten with 3-hour battery backup, maintained mode, ideal for stairwells and exits.', 1290.00 );
$count += 1; tss_demo_simple( 'VW-LED-EX20', 'LED Explosion-Proof Light 20W', 'Class I Division 2 certified LED luminaire for petrochemical and paint-shop environments.', 7450.00, 60 );

$count += 1; tss_demo_variable(
        'VW-DB-TPN',
        'Distribution Board TPN',
        'Sheet-steel TPN distribution board with door interlock, neutral and earth links, DIN rail pre-fitted. IP65 versions for outdoor kiosks.',
        'Ways',
        array(
                array( 'sku' => 'VW-DB-4WAY', 'name' => 'Distribution Board 4-Way TPN', 'price' => 2150.0, 'attrs' => array( '4 Way' ) ),
                array( 'sku' => 'VW-DB-8WAY', 'name' => 'Distribution Board 8-Way TPN IP65', 'price' => 4650.0, 'attrs' => array( '8 Way' ) ),
                array( 'sku' => 'VW-DB-12WAY', 'name' => 'Distribution Board 12-Way TPN', 'price' => 6350.0, 'attrs' => array( '12 Way' ) ),
        )
);

$count += 1; tss_demo_variable(
        'VW-MCB',
        'Miniature Circuit Breaker',
        'MCB with 10kA breaking capacity, DIN rail mount, positive contact indication. Available in B and C curves, single to triple pole.',
        'Rated Current',
        array(
                array( 'sku' => 'VW-MCB-C16', 'name' => 'MCB C16 1P 10kA', 'price' => 435.0, 'attrs' => array( '16 A' ) ),
                array( 'sku' => 'VW-MCB-C32', 'name' => 'MCB C32 1P 10kA', 'price' => 495.0, 'attrs' => array( '32 A' ) ),
                array( 'sku' => 'VW-MCB-C63', 'name' => 'MCB C63 2P 10kA', 'price' => 1180.0, 'attrs' => array( '63 A' ) ),
        )
);

$count += 1; tss_demo_simple( 'VW-MCB-B20-3P', 'MCB B20 3P 10kA', 'Triple-pole B-curve MCB for motor circuits and resistive loads, 20A per pole.', 1490.00, 800 );
$count += 1; tss_demo_simple( 'VW-RCCB-30', 'RCCB 2P 30mA', 'Residual current circuit breaker, double pole, 30mA sensitivity, 40A rating for personnel protection.', 1650.00, 300 );
$count += 1; tss_demo_simple( 'VW-RCBO-32', 'RCBO 1P+N 32A 30mA', 'Combined MCB and RCD in one module width, 32A, 30mA, A-curve for mixed loads.', 2450.00, 250 );
$count += 1; tss_demo_simple( 'VW-CONTACTOR-25', 'Contactor 3P 25A 230V AC3', 'IEC contactor for motor switching, 25A AC3, auxiliary contact block optional.', 1780.00, 180 );
$count += 1; tss_demo_simple( 'VW-OLOAD-9A', 'Thermal Overload Relay 6-9A', 'Adjustable bimetal overload relay, 6-9A range, manual/automatic reset, class 10.', 1120.00, 200 );
$count += 1; tss_demo_simple( 'VW-SPD-T2', 'Surge Protection Device Type 2', 'Type 2 SPD, 40kA per pole, replaceable cartridges with visual fault indicator, 275V.', 3950.00, 120 );
$count += 1; tss_demo_simple( 'VW-RELAY-TMR', 'Timer Relay Multifunction', 'Multifunction time relay, 0.05s to 100h, star-delta and pulse modes, 8-pin octal.', 1890.00, 90 );

$count += 1; tss_demo_variable(
        'VW-CAB-FR',
        'FR Copper Cable',
        'Flame-retardant PVC insulated copper conductor cable to IS 694. Voltage grade 1100V. Sold in standard coils and drums.',
        'Size',
        array(
                array( 'sku' => 'VW-CAB-1R5', 'name' => 'Copper Cable 1.5mm² FR 90m Coil', 'price' => 4299.0, 'attrs' => array( '1.5 sq.mm' ) ),
                array( 'sku' => 'VW-CAB-2R5', 'name' => 'Copper Cable 2.5mm² FR 90m Coil', 'price' => 6999.0, 'attrs' => array( '2.5 sq.mm' ) ),
                array( 'sku' => 'VW-CAB-4R0', 'name' => 'Copper Cable 4mm² FR 90m Coil', 'price' => 10999.0, 'attrs' => array( '4 sq.mm' ) ),
                array( 'sku' => 'VW-CAB-6R0', 'name' => 'Copper Cable 6mm² FR 90m Coil', 'price' => 16499.0, 'attrs' => array( '6 sq.mm' ) ),
        )
);

$count += 1; tss_demo_simple( 'VW-CAB-ARM-4C', 'Armoured Cable 4-Core 25mm²', 'XLPE insulated, SWA armoured 4-core aluminium cable, 25mm², 30m drum cut lengths available.', 38500.00, 40 );
$count += 1; tss_demo_simple( 'VW-CAB-FLEX-2R5', 'Flexible Copper Cable 2.5mm² 100m', 'Multi-strand flexible copper cable for machine wiring and control panels, 100m coil.', 7499.00, 220 );
$count += 1; tss_demo_simple( 'VW-CAB-DATA-CAT6', 'Cat6 Shielded Cable 305m Box', 'S/FTP category 6 cable, 305m pull box, verified to 250MHz with sequential marking.', 6999.00, 150 );

$count += 1; tss_demo_variable(
        'VW-SKT-IEC',
        'IEC 60309 Industrial Socket',
        'Industrial socket outlet to IEC 60309, IP67 when capped, screw-less terminal design, 415V three phase.',
        'Current',
        array(
                array( 'sku' => 'VW-SKT-IEC16', 'name' => 'Industrial Socket 16A 3P+E IP67', 'price' => 649.0, 'attrs' => array( '16 A' ) ),
                array( 'sku' => 'VW-SKT-IEC32', 'name' => 'Industrial Socket 32A 3P+E IP67', 'price' => 899.0, 'attrs' => array( '32 A' ) ),
                array( 'sku' => 'VW-SKT-IEC63', 'name' => 'Industrial Socket 63A 3P+N+E IP67', 'price' => 2290.0, 'attrs' => array( '63 A' ) ),
        )
);

$count += 1; tss_demo_simple( 'VW-PLUG-IEC32', 'Industrial Plug 32A 3P+E IP67', 'Matching IEC 60309 plug with cable gland for 8-14mm cable diameter.', 749.00, 500 );
$count += 1; tss_demo_simple( 'VW-CPL-IEC32', 'Industrial Coupler 32A 3P+E', 'Inter-socket coupler for extension assemblies, IP67, VDE certified.', 819.00, 300 );

$count += 1; tss_demo_variable(
        'VW-SW-MODULAR',
        'Modular Switches',
        'Premium modular switchgear range for industrial panels — dust-resistant silver contacts, snap-in mounting.',
        'Type',
        array(
                array( 'sku' => 'VW-SW-10A-SP', 'name' => 'Modular Switch 10A SPST', 'price' => 129.0, 'attrs' => array( 'Single Pole' ) ),
                array( 'sku' => 'VW-SW-20A-DP', 'name' => 'Modular Switch 20A DPST', 'price' => 249.0, 'attrs' => array( 'Double Pole' ) ),
                array( 'sku' => 'VW-SW-32A-TP', 'name' => 'Changeover Switch 32A TP', 'price' => 1950.0, 'attrs' => array( 'Triple Pole' ) ),
        )
);

$count += 1; tss_demo_simple( 'VW-SW-PB-GREEN', 'Push Button Station 22mm Green', 'Metallic 22mm push button station with 1NO contact, IP65 front seal, panel mount.', 489.00, 600 );
$count += 1; tss_demo_simple( 'VW-SW-PB-ESTOP', 'Emergency Stop Mushroom 40mm', 'Red mushroom-head e-stop with latching twist-release, 2NC contacts, 40mm.', 749.00, 400 );
$count += 1; tss_demo_simple( 'VW-SW-SELECT-3P', 'Selector Switch 3-Position', 'Maintained 3-position selector, black handle, 1NO+1NC contact block.', 399.00, 350 );
$count += 1; tss_demo_simple( 'VW-SW-FLOAT', 'Float Switch Cable 20m', 'Cable-type float switch for tank level control, 20m PVC cable, 250V 10A contacts.', 1180.00, 150 );

$count += 1; tss_demo_simple( 'VW-TRUNKY-75', 'PVC Cable Trunking 75x75 Grey', 'Slotted PVC trunking with snap-on lid, 75x75mm, 2m lengths, self-extinguishing to UL94 V0.', 289.00, 900 );
$count += 1; tss_demo_simple( 'VW-CONDUIT-25', 'PVC Conduit Pipe 25mm ISI', 'Heavy-duty ISI-marked PVC conduit, 25mm, 3m length, threaded joints compatible.', 98.00, 2000 );
$count += 1; tss_demo_simple( 'VW-CONDUIT-BND', 'Conduit Bending Spring 25mm', 'Internal bending spring for clean bends in 25mm PVC conduit without kinking.', 245.00, 300 );
$count += 1; tss_demo_simple( 'VW-JBOX-4W', 'Junction Box 4-Way PVC', 'Flush-mount 4-way junction box with knockouts and screw lid, IP30.', 68.00, 3000 );
$count += 1; tss_demo_simple( 'VW-CBLTIE-300', 'Cable Ties 300x4.8mm Pack 100', 'Nylon 66 cable ties, 300x4.8mm, 22kg tensile, UV-resistant black, pack of 100.', 389.00, 1200 );
$count += 1; tss_demo_simple( 'VW-LUG-C10', 'Cable Lug 10mm Pack 25', 'Tinned copper cable lugs, 10mm stud, pack of 25 with insulating caps.', 449.00, 400 );
$count += 1; tss_demo_simple( 'VW-GLAND-M25', 'Cable Gland M25 Brass Pack 10', 'Brass cable glands M25 with IP68 seal and locknut, pack of 10.', 699.00, 350 );
$count += 1; tss_demo_simple( 'VW-SHRINK-1M', 'Heat Shrink Tube 6mm 1m', 'Polyolefin heat shrink tubing, 6mm diameter, 2:1 shrink ratio, 125°C rating.', 59.00, 2500 );
$count += 1; tss_demo_simple( 'VW-FERRULE-KIT', 'Ferrule Kit 0.5-6mm² 1000pc', 'Insulated copper ferrule assortment in modular case, DIN 46228, 1000 pieces.', 1999.00, 200 );

$count += 1; tss_demo_simple( 'VW-CHT-ANA', 'Analog Panel Meter 96mm', 'Analog moving-coil panel meter, 96x96mm, AC ammeter with selector switch provision.', 1350.00, 120 );
$count += 1; tss_demo_simple( 'VW-CHT-DIG-V', 'Digital Voltmeter 96mm True RMS', 'True RMS digital panel meter, 500V AC range, 0.5% accuracy, CT/PT programmable.', 2890.00, 90 );
$count += 1; tss_demo_simple( 'VW-CHT-PF', 'Power Factor Meter 96mm', 'Digital power factor meter with 4-20mA retransmission and relay output options.', 4550.00, 60 );
$count += 1; tss_demo_simple( 'VW-CHT-CT-200', 'Current Transformer 200/5A', 'Measuring CT 200/5A, class 1, bar primary, moulded case with terminal cover.', 1240.00, 140 );
$count += 1; tss_demo_simple( 'VW-CHT-KWH-3P', 'Energy Meter 3-Phase CT Operated', 'Three-phase four-wire energy meter, CT operated, LCD with kWkWh and demand display.', 4890.00, 80 );

$count += 1; tss_demo_simple( 'VW-TRF-CTRL-500', 'Control Transformer 500VA', 'Isolation control transformer 500VA, 415/230V to 110/24V, terminal shrouds included.', 3990.00, 70 );
$count += 1; tss_demo_simple( 'VW-PSU-24V-5A', 'DIN Power Supply 24V 5A', 'Switched-mode DIN rail power supply, 24VDC 5A, 120W, short-circuit and overload protection.', 4250.00, 100 );
$count += 1; tss_demo_simple( 'VW-BUZZ-24', 'Panel Buzzer 24VDC Flush', 'Panel-mount buzzer, 24VDC, 85dB at 1m, flush 22mm mounting.', 329.00, 400 );
$count += 1; tss_demo_simple( 'VW-LAMP-LED-24', 'Panel Indicator LED 24V Red', '22mm LED indicator with built-in resistor, 24V AC/DC, long-life dome lens.', 189.00, 900 );
$count += 1; tss_demo_simple( 'VW-FUSE-DIA-20', 'Fuse Pack DIAZED 20A 5pc', 'DIAZED fuse links 20A with screw caps and reduction sets, pack of 5.', 549.00, 300 );

$count += 1; tss_demo_simple( 'VW-SAFE-30', 'Safety Switch 30A Rotary', 'Load-break rotary safety switch, 30A, padlockable handle, front panel mounting.', 2890.00, 110 );
$count += 1; tss_demo_simple( 'VW-CAP-12.5', 'Power Capacitor 12.5kVAr', 'Self-healing power factor correction capacitor, 12.5kVAr 440V, 3-phase delta.', 3150.00, 95 );
$count += 1; tss_demo_simple( 'VW-RELAY-CR-3P', 'Control Relay 3P CO 10A', 'General purpose control relay, 3 changeover contacts, 10A, LED and diode options.', 629.00, 500 );
$count += 1; tss_demo_simple( 'VW-TERM-DIN-4', 'Terminal Block DIN Pack 50', 'Screw-type DIN terminal blocks, 4mm², grey, pack of 50 with end brackets.', 749.00, 400 );
$count += 1; tss_demo_simple( 'VW-WIRE-MKR', 'Wire Marker Refills A-F', 'Self-laminating wire marker refills, characters A-F, for 0.5-6mm² conductors.', 429.00, 300 );
$count += 1; tss_demo_simple( 'VW-TAPE-PVC', 'PVC Insulation Tape 19mm', 'Premium PVC electrical tape, 19mm x 20m, flame retardant adhesive, 600V dielectric.', 45.00, 5000 );
$count += 1; tss_demo_simple( 'VW-TAPE-MSK', 'Masking Tape 24mm Auto', 'Automotive-grade masking tape for powder-coating masking, 24mm, 180°C resistant.', 89.00, 2000 );

// ------------------------------------------------------------------ //
// Customers (synthetic).
// ------------------------------------------------------------------ //

$krishnaId = tss_demo_customer( 'krishna.elec', 'krishna@krishnaelectricals.example', 'Krishna Electricals', 'Nashik' );
$shaktiId  = tss_demo_customer( 'shakti.ind', 'purchase@shaktiindustries.example', 'Shakti Industries Pvt. Ltd.', 'Aurangabad' );

// ------------------------------------------------------------------ //
// Price books.
// ------------------------------------------------------------------ //

$plugin = \TradeSheetStudio\Core\Plugin::instance();

$books = $plugin->priceBooks();
$rules = array();

$tierA = 0;
$tierB = 0;

foreach ( $books->listBooks() as $book ) {
        if ( 'Distributor Tier A' === $book['name'] ) {
                $tierA = (int) $book['id'];
        }
        if ( 'Distributor Tier B' === $book['name'] ) {
                $tierB = (int) $book['id'];
        }
}

if ( 0 === $tierA ) {
        $tierA = $books->create( 'Distributor Tier A', 'Top-tier distributors with annual volume commitment (demo data).', 'active', 'INR', 10, null, null );
}
if ( 0 === $tierB ) {
        $tierB = $books->create( 'Distributor Tier B', 'Standard trade buyers (demo data).', 'active', 'INR', 5, null, null );
}

function tss_rule( \TradeSheetStudio\Pricing\PriceBookRepository $books, int $bookId, array $rule ): void {
        try {
                $books->upsertRule( \TradeSheetStudio\Pricing\PriceRule::fromArray( $rule ) );
        } catch ( \Throwable $e ) {
                WP_CLI::log( 'Rule warning: ' . $e->getMessage() );
        }
}

// Tier A: book-wide 10% off.
tss_rule( $books, $tierA, array(
        'pricebook_id' => $tierA,
        'customer_id'  => 0,
        'product_id'   => 0,
        'variation_id' => 0,
        'percent_off'  => '10.00',
        'min_qty'      => 1,
        'qty_step'     => 1,
        'priority'     => 0,
) );

// Tier A: fixed override on the 150W high bay with quantity breaks.
tss_rule( $books, $tierA, array(
        'pricebook_id' => $tierA,
        'customer_id'  => 0,
        'product_id'   => wc_get_product_id_by_sku( 'VW-LED-HB' ),
        'variation_id' => wc_get_product_id_by_sku( 'VW-LED-HB150' ),
        'price_override' => '4450.0000',
        'min_qty'      => 2,
        'qty_step'     => 2,
        'qty_breaks'   => array(
                array( 'qty' => 10, 'price' => '4180.0000' ),
                array( 'qty' => 50, 'price' => '3950.0000' ),
        ),
        'priority'     => 0,
) );

// Tier A: MCB C32 with tiered breaks.
tss_rule( $books, $tierA, array(
        'pricebook_id' => $tierA,
        'customer_id'  => 0,
        'product_id'   => wc_get_product_id_by_sku( 'VW-MCB' ),
        'variation_id' => wc_get_product_id_by_sku( 'VW-MCB-C32' ),
        'price_override' => '412.0000',
        'min_qty'      => 5,
        'qty_step'     => 5,
        'qty_breaks'   => array(
                array( 'qty' => 50, 'price' => '385.0000' ),
                array( 'qty' => 200, 'price' => '355.0000' ),
        ),
        'priority'     => 0,
) );

// Tier A: customer-specific override — Krishna gets a sharper high bay price.
if ( $krishnaId > 0 ) {
        tss_rule( $books, $tierA, array(
                'pricebook_id' => $tierA,
                'customer_id'  => $krishnaId,
                'product_id'   => wc_get_product_id_by_sku( 'VW-LED-HB' ),
                'variation_id' => wc_get_product_id_by_sku( 'VW-LED-HB150' ),
                'price_override' => '4399.0000',
                'min_qty'      => 2,
                'qty_step'     => 2,
                'priority'     => 0,
        ) );
}

// Tier B: book-wide 5% off.
tss_rule( $books, $tierB, array(
        'pricebook_id' => $tierB,
        'customer_id'  => 0,
        'product_id'   => 0,
        'variation_id' => 0,
        'percent_off'  => '5.00',
        'min_qty'      => 1,
        'qty_step'     => 1,
        'priority'     => 0,
) );

// Assignments.
if ( $krishnaId > 0 ) {
        $books->assignCustomer( $tierA, $krishnaId );
}
if ( $shaktiId > 0 ) {
        $books->assignCustomer( $tierB, $shaktiId );
}

printf(
        "Demo data ready: %d product groups created, Tier A book #%d, Tier B book #%d, Krishna=#%d, Shakti=#%d\n",
        $count,
        $tierA,
        $tierB,
        $krishnaId,
        $shaktiId
);
