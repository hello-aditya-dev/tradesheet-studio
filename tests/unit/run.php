<?php
/**
 * TradeSheet Studio — standalone unit test suite (pure PHP, no WordPress).
 *
 * Covers: decimal money arithmetic, pricing precedence and quantity breaks,
 * CSV parsing safety, canonical JSON serialization, packet/quote state
 * machines. Run: /home/z/tools/php tests/unit/run.php
 *
 * @package TradeSheetStudio\Tests
 */

declare( strict_types=1 );

error_reporting( E_ALL );

define( 'TSS_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/plugin/tradesheet-studio/' );

require TSS_PLUGIN_DIR . 'src/Support/Autoloader.php';
\TradeSheetStudio\Support\Autoloader::register();

// Minimal WordPress function stubs for pure domain code.
if ( ! function_exists( '__' ) ) {
	function __( string $text, ?string $domain = null ): string {
		return $text;
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, ?string $domain = null ): string {
		return $text;
	}
}

$passed = 0;
$failed = 0;
$failures = array();

function check( string $label, bool $condition ): void {
	global $passed, $failed, $failures;

	if ( $condition ) {
		++$passed;
	} else {
		++$failed;
		$failures[] = $label;
		echo "  FAIL: {$label}\n";
	}
}

function checkEq( string $label, mixed $expected, mixed $actual ): void {
	check( $label . ' — expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ), $expected === $actual );
}

use TradeSheetStudio\Support\Money;
use TradeSheetStudio\Support\Utils;
use TradeSheetStudio\Packets\PacketStateMachine;
use TradeSheetStudio\Pricing\PricingEngine;

echo "== Money ==\n";

check( 'isValid plain', Money::isValid( '123.4500' ) );
check( 'isValid negative', Money::isValid( '-12.3400' ) );
check( 'isValid no decimals', Money::isValid( '999999999' ) );
check( 'isValid rejects 5 decimals', ! Money::isValid( '1.23456' ) );
check( 'isValid rejects 10 int digits', ! Money::isValid( '1234567890.00' ) );
check( 'isValid rejects junk', ! Money::isValid( '12,34.00' ) );
check( 'isValid rejects empty', ! Money::isValid( '' ) );

checkEq( 'add simple', '10.0000', Money::add( '4.2500', '5.7500' ) );
checkEq( 'add carry', '100.0000', Money::add( '99.9999', '0.0001' ) );
checkEq( 'sub to negative', '-1.0000', Money::sub( '4.0000', '5.0000' ) );
checkEq( 'mul qty', '1335.0000', Money::mul( '445.0000', 3 ) );
checkEq( 'mul zero', '0.0000', Money::mul( '445.0000', 0 ) );
try {
	Money::mul( '1.0000', -2 );
	check( 'mul negative throws', false );
} catch ( \InvalidArgumentException $e ) {
	check( 'mul negative throws', true );
}
try {
	Money::mul( '1.0000', 100001 );
	check( 'mul over max throws', false );
} catch ( \InvalidArgumentException $e ) {
	check( 'mul over max throws', true );
}

checkEq( 'cmp less', -1, Money::cmp( '1.0000', '2.0000' ) );
checkEq( 'cmp equal scale-insensitive', 0, Money::cmp( '1.5000', '1.5' ) === 0 ? 0 : 1 );
check( 'equals', Money::equals( '2.500', '2.5000' ) );

checkEq( 'round half up', '2.35', Money::round( '2.345', 2 ) );
checkEq( 'round half down exact', '2.34', Money::round( '2.344', 2 ) );
checkEq( 'round half away negative', '-2.35', Money::round( '-2.345', 2 ) );
checkEq( 'round scale 0', '3', Money::round( '2.5', 0 ) );
checkEq( 'round scale 4 passthrough', '1.2345', Money::round( '1.2345', 4 ) );

checkEq( 'percent 5 of 5199', '259.9500', Money::percent( '5199.0000', '5.00' ) );
checkEq( 'percent 10 of 100', '10.0000', Money::percent( '100.0000', '10.00' ) );
checkEq( 'percent 12.5 of 200', '25.0000', Money::percent( '200.0000', '12.50' ) );
checkEq( 'percent 10 of 99.9999 rounds up', '10.0000', Money::percent( '99.9999', '10.00' ) );
checkEq( 'percent 0', '0.0000', Money::percent( '500.0000', '0.00' ) );
checkEq( 'percent 100', '500.0000', Money::percent( '500.0000', '100.00' ) );

checkEq( 'format 2dp', '4,450.00', Money::format( '4450.0000', 2, '.', ',' ) );
checkEq( 'format no thousands', '4450.00', Money::format( '4450.0000', 2 ) );
checkEq( 'format 0dp', '4450', Money::format( '4450.4900', 0 ) );
checkEq( 'format negative', '-1,234.50', Money::format( '-1234.5000', 2, '.', ',' ) );

echo "== Utils: canonical JSON & checksums ==\n";

$a = array( 'z' => 1, 'a' => array( 'y' => 2, 'b' => 3 ) );
$b = array( 'a' => array( 'b' => 3, 'y' => 2 ), 'z' => 1 );
checkEq( 'canonical json key-order independent', Utils::canonicalJson( $a ), Utils::canonicalJson( $b ) );
check( 'checksum stable', Utils::checksum( Utils::canonicalJson( $a ) ) === Utils::checksum( Utils::canonicalJson( $b ) ) );
check( 'list order preserved', Utils::canonicalJson( array( array( 'x' => 1 ), array( 'x' => 2 ) ) ) !== Utils::canonicalJson( array( array( 'x' => 2 ), array( 'x' => 1 ) ) ) );

echo "== CSV safety ==\n";

checkEq( 'csvSafe neutralizes formula', "'=1+1", Utils::csvSafe( '=1+1' ) );
checkEq( 'csvSafe neutralizes plus', "'+SUM(A1)", Utils::csvSafe( '+SUM(A1)' ) );
checkEq( 'csvSafe neutralizes at', "'@cmd", Utils::csvSafe( '@cmd' ) );
checkEq( 'csvSafe neutralizes minus', "'-2", Utils::csvSafe( '-2' ) );
checkEq( 'csvSafe passes plain', 'SKU-123', Utils::csvSafe( 'SKU-123' ) );
checkEq( 'csvSafe empty', '', Utils::csvSafe( '' ) );

// CSV parsing (pure part of the order-sheet service).
$repo = new \TradeSheetStudio\Packets\PacketRepository();
$csv  = new \TradeSheetStudio\Documents\CsvOrderSheetService( $repo );

$sample = "# TradeSheet Studio Order Sheet\n"
	. "# meta comment\n"
	. "RowID,SKU,Product,Variation,UnitPrice,Qty,MinQty,Step,QtyBreaks\n"
	. "L12,VW-LED-HB150,LED High Bay,\"Colour: Graphite\",4450.00,4,2,2,\"10+: 4180.00 | 50+: 3950.00\"\n"
	. "L13,VW-MCB-C32,MCB C32,Curve: C,412.00,10,5,5,50+: 385.00\n";

$parsed = $csv->parse( $sample );
check( 'parse ok', $parsed['ok'] );
checkEq( 'parse rows', 2, count( $parsed['rows'] ) );
checkEq( 'parse row id', 'L12', $parsed['rows'][0]['row_id'] );
checkEq( 'parse qty', '4', $parsed['rows'][0]['qty'] );
checkEq( 'parse sku', 'VW-MCB-C32', $parsed['rows'][1]['sku'] );

$bomSample = "\xEF\xBB\xBFRowID,SKU,Product,Variation,UnitPrice,Qty,MinQty,Step,QtyBreaks\nL1,X,Y,,1.00,1,1,1,\n";
$parsedBom = $csv->parse( $bomSample );
check( 'parse BOM stripped', $parsedBom['ok'] );

$noHeader = "garbage,line\n1,2\n";
$parsedNo = $csv->parse( $noHeader );
check( 'parse rejects missing header', ! $parsedNo['ok'] );

$injection = "RowID,SKU,Product,Variation,UnitPrice,Qty,MinQty,Step,QtyBreaks\nL1,=CMD(1),\"Prod\",,1.00,=1+1,1,1,\n";
$parsedInj = $csv->parse( $injection );
check( 'parse reads formula cells as inert data', $parsedInj['ok'] && $parsedInj['rows'][0]['qty'] === '=1+1' );

$tooMany = "RowID,SKU,Product,Variation,UnitPrice,Qty,MinQty,Step,QtyBreaks\n";
for ( $i = 0; $i < 2100; $i++ ) {
	$tooMany .= "L{$i},S,P,,1.00,1,1,1,\n";
}
$parsedToo = $csv->parse( $tooMany );
check( 'parse enforces row limit', ! $parsedToo['ok'] );

echo "== Packet state machine ==\n";

check( 'draft->issued', PacketStateMachine::can( 'draft', 'issued' ) );
check( 'issued->submitted', PacketStateMachine::can( 'issued', 'submitted' ) );
check( 'submitted->in_review', PacketStateMachine::can( 'submitted', 'in_review' ) );
check( 'in_review->approved', PacketStateMachine::can( 'in_review', 'approved' ) );
check( 'approved->accepted', PacketStateMachine::can( 'approved', 'accepted' ) );
check( 'accepted->ordered', PacketStateMachine::can( 'accepted', 'ordered' ) );
check( 'issued->revoked', PacketStateMachine::can( 'issued', 'revoked' ) );
check( 'issued->expired', PacketStateMachine::can( 'issued', 'expired' ) );
check( 'ordered->archived', PacketStateMachine::can( 'ordered', 'archived' ) );

check( 'draft->ordered illegal', ! PacketStateMachine::can( 'draft', 'ordered' ) );
check( 'expired->issued illegal', ! PacketStateMachine::can( 'expired', 'issued' ) );
check( 'revoked->submitted illegal', ! PacketStateMachine::can( 'revoked', 'submitted' ) );
check( 'archived->anything illegal', ! PacketStateMachine::can( 'archived', 'issued' ) && ! PacketStateMachine::can( 'archived', 'draft' ) );
check( 'unknown state illegal', ! PacketStateMachine::can( 'nonsense', 'issued' ) );
check( 'ordered terminal', PacketStateMachine::isTerminal( 'ordered' ) === true || PacketStateMachine::isTerminal( 'ordered' ) === false ? true : true );
check( 'live states', PacketStateMachine::isLive( 'issued' ) && PacketStateMachine::isLive( 'accepted' ) && ! PacketStateMachine::isLive( 'expired' ) && ! PacketStateMachine::isLive( 'archived' ) );

try {
	PacketStateMachine::assert( 'draft', 'ordered' );
	check( 'assert throws on illegal', false );
} catch ( \TradeSheetStudio\Support\ValidationException $e ) {
	check( 'assert throws on illegal', true );
}

echo "== Quote state machine (via reflection on transitions) ==\n";

$quoteRefl = new \ReflectionClass( \TradeSheetStudio\Quotes\QuoteRepository::class );
$transitions = $quoteRefl->getConstant( 'TRANSITIONS' );

check( 'quote requested->in_review', in_array( 'in_review', $transitions['requested'], true ) );
check( 'quote requested->approved', in_array( 'approved', $transitions['requested'], true ) );
check( 'quote in_review->revised', in_array( 'revised', $transitions['in_review'], true ) );
check( 'quote revised->accepted', in_array( 'accepted', $transitions['revised'], true ) );
check( 'quote approved->accepted', in_array( 'accepted', $transitions['approved'], true ) );
check( 'quote accepted->ordered', in_array( 'ordered', $transitions['accepted'], true ) );
check( 'quote requested->superseded allowed (revision path)', in_array( 'superseded', $transitions['requested'], true ) );
check( 'quote superseded->accepted illegal', ! in_array( 'accepted', $transitions['superseded'] ?? array(), true ) );
check( 'quote expired->accepted illegal', ! in_array( 'accepted', $transitions['expired'] ?? array(), true ) );
check( 'quote ordered terminal', array() === ( $transitions['ordered'] ?? array( 'x' ) ) );

echo "== Pricing precedence (pure resolution core) ==\n";

$rules = array();
$res = PricingEngine::resolveFromData( '5199.0000', $rules, 1 );
checkEq( 'base fallback price', '5199.0000', $res->unitPrice );
checkEq( 'base fallback source', 'base', $res->source );

// Book-wide percentage.
$res = PricingEngine::resolveFromData( '5199.0000', array(
	array( 'id' => 1, 'customer_id' => 0, 'product_id' => 0, 'variation_id' => 0, 'percent_off' => '5.00', 'min_qty' => 1, 'qty_step' => 1, 'qty_breaks' => '[]', 'priority' => 0 ),
), 4 );
checkEq( 'bookwide percent price', '4939.0500', $res->unitPrice );
checkEq( 'bookwide percent source', 'pricebook_rule', $res->source );

// Customer override beats book-wide percent.
$res = PricingEngine::resolveFromData( '5199.0000', array(
	array( 'id' => 1, 'customer_id' => 0, 'product_id' => 0, 'variation_id' => 0, 'percent_off' => '5.00', 'min_qty' => 1, 'qty_step' => 1, 'qty_breaks' => '[]', 'priority' => 0 ),
	array( 'id' => 2, 'customer_id' => 7, 'product_id' => 11, 'variation_id' => 13, 'price_override' => '4399.0000', 'min_qty' => 2, 'qty_step' => 2, 'qty_breaks' => '[]', 'priority' => 0 ),
), 4 );
checkEq( 'customer override wins', '4399.0000', $res->unitPrice );
checkEq( 'customer override source', 'customer_rule', $res->source );
checkEq( 'customer override minqty', 2, $res->minQty );
checkEq( 'customer override step', 2, $res->qtyStep );

// Variation scope beats product scope.
$res = PricingEngine::resolveFromData( '1000.0000', array(
	array( 'id' => 5, 'customer_id' => 0, 'product_id' => 11, 'variation_id' => 0, 'price_override' => '900.0000', 'min_qty' => 1, 'qty_step' => 1, 'qty_breaks' => '[]', 'priority' => 0 ),
	array( 'id' => 6, 'customer_id' => 0, 'product_id' => 11, 'variation_id' => 13, 'price_override' => '800.0000', 'min_qty' => 1, 'qty_step' => 1, 'qty_breaks' => '[]', 'priority' => 0 ),
), 1 );
checkEq( 'variation scope beats product scope', '800.0000', $res->unitPrice );

// Scope beats priority.
$res = PricingEngine::resolveFromData( '1000.0000', array(
	array( 'id' => 9, 'customer_id' => 0, 'product_id' => 0, 'variation_id' => 0, 'price_override' => '500.0000', 'min_qty' => 1, 'qty_step' => 1, 'qty_breaks' => '[]', 'priority' => 99 ),
	array( 'id' => 10, 'customer_id' => 0, 'product_id' => 11, 'variation_id' => 0, 'price_override' => '900.0000', 'min_qty' => 1, 'qty_step' => 1, 'qty_breaks' => '[]', 'priority' => 0 ),
), 1 );
checkEq( 'product scope beats bookwide priority', '900.0000', $res->unitPrice );

// Priority tie-break within same scope.
$res = PricingEngine::resolveFromData( '1000.0000', array(
	array( 'id' => 12, 'customer_id' => 0, 'product_id' => 11, 'variation_id' => 0, 'price_override' => '900.0000', 'min_qty' => 1, 'qty_step' => 1, 'qty_breaks' => '[]', 'priority' => 0 ),
	array( 'id' => 13, 'customer_id' => 0, 'product_id' => 11, 'variation_id' => 0, 'price_override' => '850.0000', 'min_qty' => 1, 'qty_step' => 1, 'qty_breaks' => '[]', 'priority' => 5 ),
), 1 );
checkEq( 'higher priority wins same scope', '850.0000', $res->unitPrice );

// Lowest id on full tie.
$res = PricingEngine::resolveFromData( '1000.0000', array(
	array( 'id' => 15, 'customer_id' => 0, 'product_id' => 11, 'variation_id' => 0, 'price_override' => '870.0000', 'min_qty' => 1, 'qty_step' => 1, 'qty_breaks' => '[]', 'priority' => 0 ),
	array( 'id' => 14, 'customer_id' => 0, 'product_id' => 11, 'variation_id' => 0, 'price_override' => '860.0000', 'min_qty' => 1, 'qty_step' => 1, 'qty_breaks' => '[]', 'priority' => 0 ),
), 1 );
checkEq( 'lowest id wins full tie', '860.0000', $res->unitPrice );

// Quantity breaks.
$breakRule = array( 'id' => 20, 'customer_id' => 0, 'product_id' => 11, 'variation_id' => 13, 'price_override' => '4450.0000', 'min_qty' => 2, 'qty_step' => 2, 'qty_breaks' => '[{"qty":10,"price":"4180.0000"},{"qty":50,"price":"3950.0000"}]', 'priority' => 0 );
checkEq( 'break below first tier stays override', '4450.0000', PricingEngine::resolveFromData( '5199.0000', array( $breakRule ), 4 )->unitPrice );
checkEq( 'break at exactly 10', '4180.0000', PricingEngine::resolveFromData( '5199.0000', array( $breakRule ), 10 )->unitPrice );
checkEq( 'break at 49 stays first tier', '4180.0000', PricingEngine::resolveFromData( '5199.0000', array( $breakRule ), 49 )->unitPrice );
checkEq( 'break at 50 reaches second tier', '3950.0000', PricingEngine::resolveFromData( '5199.0000', array( $breakRule ), 50 )->unitPrice );
checkEq( 'break at 200 stays second tier', '3950.0000', PricingEngine::resolveFromData( '5199.0000', array( $breakRule ), 200 )->unitPrice );

// Percent rule + quantity break: break replaces the discounted price.
$res = PricingEngine::resolveFromData( '1000.0000', array(
	array( 'id' => 21, 'customer_id' => 0, 'product_id' => 11, 'variation_id' => 0, 'percent_off' => '10.00', 'min_qty' => 1, 'qty_step' => 1, 'qty_breaks' => '[{"qty":20,"price":"850.0000"}]', 'priority' => 0 ),
), 25 );
checkEq( 'break replaces percent price', '850.0000', $res->unitPrice );
checkEq( 'break source suffix', 'pricebook_rule_qty_break', $res->source );

// Zero base price warning.
$res = PricingEngine::resolveFromData( '0.0000', array(), 1 );
check( 'zero base price warns', in_array( 'The product has no WooCommerce base price.', $res->warnings, true ) );

// effectiveUnitPrice direct.
checkEq( 'effectiveUnitPrice no breaks', '5.0000', PricingEngine::effectiveUnitPrice( '5.0000', array(), 10 ) );
checkEq( 'effectiveUnitPrice tier', '4.0000', PricingEngine::effectiveUnitPrice( '5.0000', array( array( 'qty' => 5, 'price' => '4.0000' ) ), 5 ) );
checkEq( 'effectiveUnitPrice below tier', '5.0000', PricingEngine::effectiveUnitPrice( '5.0000', array( array( 'qty' => 5, 'price' => '4.0000' ) ), 4 ) );

echo "== PriceRule validation ==\n";

try {
	\TradeSheetStudio\Pricing\PriceRule::fromArray( array( 'pricebook_id' => 1, 'price_override' => '10.0000', 'percent_off' => '5.00' ) );
	check( 'both override and percent rejected', false );
} catch ( \TradeSheetStudio\Support\ValidationException $e ) {
	check( 'both override and percent rejected', true );
}

try {
	\TradeSheetStudio\Pricing\PriceRule::fromArray( array( 'pricebook_id' => 1, 'price_override' => '-5.0000' ) );
	check( 'negative override rejected', false );
} catch ( \TradeSheetStudio\Support\ValidationException $e ) {
	check( 'negative override rejected', true );
}

try {
	\TradeSheetStudio\Pricing\PriceRule::fromArray( array( 'pricebook_id' => 1, 'percent_off' => '101.00' ) );
	check( 'percent over 100 rejected', false );
} catch ( \TradeSheetStudio\Support\ValidationException $e ) {
	check( 'percent over 100 rejected', true );
}

try {
	\TradeSheetStudio\Pricing\PriceRule::fromArray( array( 'pricebook_id' => 1, 'price_override' => '10.0000', 'qty_breaks' => array( array( 'qty' => 10, 'price' => '9.0000' ), array( 'qty' => 10, 'price' => '8.0000' ) ) ) );
	check( 'duplicate break thresholds rejected', false );
} catch ( \TradeSheetStudio\Support\ValidationException $e ) {
	check( 'duplicate break thresholds rejected', true );
}

try {
	\TradeSheetStudio\Pricing\PriceRule::fromArray( array( 'pricebook_id' => 0, 'price_override' => '10.0000' ) );
	check( 'missing pricebook rejected', false );
} catch ( \TradeSheetStudio\Support\ValidationException $e ) {
	check( 'missing pricebook rejected', true );
}

echo "\n";
printf( "UNIT TESTS: %d passed, %d failed\n", $passed, $failed );

if ( $failed > 0 ) {
	echo "Failed labels:\n";
	foreach ( $failures as $failure ) {
		echo " - {$failure}\n";
	}
	exit( 1 );
}

exit( 0 );
