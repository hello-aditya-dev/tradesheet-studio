<?php
/**
 * Template: Trade Table — dense wholesale price sheet (landscape).
 *
 * Receives $data from PdfRenderer::buildTemplateData(). All dynamic output
 * is escaped; no untrusted HTML is ever rendered.
 *
 * @package TradeSheetStudio\Documents
 */

declare( strict_types=1 );

/** @var array<string,mixed> $data */

use TradeSheetStudio\Support\Money;

$e   = static function ( mixed $value ): string {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
};
$fmt = static function ( string $price ): string {
	return Money::format( $price, 2, '.', ',' );
};
$sym = $data['currency_symbol'];

$taxLabel = $data['prices_include_tax'] ? 'tax-inclusive' : 'tax-exclusive';

$breakText = static function ( array $line ) use ( $fmt ): string {
	$parts = array();
	foreach ( ( $line['qty_breaks'] ?? array() ) as $break ) {
		if ( is_array( $break ) && isset( $break['qty'], $break['price'] ) ) {
			$parts[] = (int) $break['qty'] . '+: ' . $fmt( (string) $break['price'] );
		}
	}
	return implode( '  ·  ', $parts );
};

$footerBase = 'Issued ' . $data['issued_at'] . ' — Version ' . $data['version_no'] . ' — Valid until ' . $data['expires_at'];
if ( '' !== (string) $data['company']['footer'] ) {
	$footerBase .= ' — ' . (string) $data['company']['footer'];
}
$footerJs = str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), $footerBase );
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8" />
	<title><?php echo $e( $data['company']['name'] . ' — ' . $data['title'] ); ?></title>
	<style>
		@page { margin: 36px 30px 40px 30px; }
		body { font-family: "DejaVu Sans", sans-serif; font-size: 8.5pt; color: #22272b; margin: 0; }
		.brand { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
		.brand td { vertical-align: top; }
		.company { font-size: 13pt; font-weight: bold; color: #0f766e; }
		.company-sub { font-size: 8pt; color: #5b656d; margin-top: 2px; }
		.doc-title { text-align: right; font-size: 15pt; font-weight: bold; color: #22272b; }
		.doc-ref { text-align: right; font-size: 9pt; color: #5b656d; }
		.meta { width: 100%; border-collapse: collapse; background: #f2f7f6; border: 0.5pt solid #cfe3df; margin-bottom: 12px; }
		.meta td { padding: 5px 8px; font-size: 8pt; }
		.meta .k { color: #5b656d; width: 90px; }
		table.lines { width: 100%; border-collapse: collapse; }
		table.lines th { background: #0f766e; color: #ffffff; text-align: left; padding: 5px 6px; font-size: 8pt; border: 0.5pt solid #0f766e; }
		table.lines td { border: 0.5pt solid #d5dfdc; padding: 4px 6px; font-size: 8.2pt; vertical-align: top; }
		table.lines tr { page-break-inside: avoid; }
		.num { text-align: right; white-space: nowrap; }
		.sku { font-family: "DejaVu Sans Mono", monospace; font-size: 7.6pt; color: #37474f; }
		.price { font-weight: bold; color: #0f5e57; }
		.note { margin-top: 10px; font-size: 7.6pt; color: #5b656d; }
	</style>
</head>
<body>

<table class="brand">
	<tr>
		<td>
			<?php if ( '' !== (string) $data['company']['logo'] ) : ?>
				<img src="<?php echo $e( $data['company']['logo'] ); ?>" style="max-height: 46px;" />
			<?php endif; ?>
			<div class="company"><?php echo $e( $data['company']['name'] ); ?></div>
			<div class="company-sub"><?php echo $e( trim( $data['company']['address'] ) ); ?></div>
			<div class="company-sub"><?php echo $e( trim( $data['company']['email'] . ( '' !== $data['company']['phone'] ? '  ·  ' . $data['company']['phone'] : '' ) ) ); ?></div>
		</td>
		<td style="text-align: right;">
			<div class="doc-title">Wholesale Price Sheet</div>
			<div class="doc-ref"><?php echo $e( $data['ref'] ); ?> · Version <?php echo $e( $data['version_no'] ); ?></div>
		</td>
	</tr>
</table>

<table class="meta">
	<tr>
		<td class="k">Offer</td><td><?php echo $e( $data['title'] ); ?></td>
		<td class="k">Buyer</td><td><?php echo $e( $data['buyer'] ); ?></td>
	</tr>
	<tr>
		<td class="k">Issued</td><td><?php echo $e( $data['issued_at'] ); ?></td>
		<td class="k">Valid until</td><td><?php echo $e( $data['expires_at'] ); ?></td>
	</tr>
	<tr>
		<td class="k">Currency</td><td><?php echo $e( $data['currency'] ); ?> (<?php echo $e( $sym ); ?>)</td>
		<td class="k">Prices</td><td><?php echo $e( $taxLabel ); ?></td>
	</tr>
</table>

<table class="lines">
	<thead>
	<tr>
		<th style="width: 13%;">SKU</th>
		<th style="width: 32%;">Product</th>
		<th style="width: 17%;">Variation</th>
		<th style="width: 11%;">Unit price</th>
		<th style="width: 8%;">Min qty</th>
		<th style="width: 7%;">Step</th>
		<th style="width: 12%;">Quantity breaks</th>
	</tr>
	</thead>
	<tbody>
	<?php foreach ( $data['lines'] as $line ) : ?>
		<tr>
			<td class="sku"><?php echo $e( $line['sku'] !== '' ? $line['sku'] : $line['row_id'] ); ?></td>
			<td><?php echo $e( $line['name'] ); ?></td>
			<td><?php echo $e( $line['variation_summary'] ); ?></td>
			<td class="num price"><?php echo $e( $sym ) . ' ' . $e( $fmt( (string) $line['unit_price'] ) ); ?></td>
			<td class="num"><?php echo $e( (string) $line['qty_min'] ); ?></td>
			<td class="num"><?php echo $e( (string) $line['qty_step'] ); ?></td>
			<td><?php echo $e( $breakText( $line ) ); ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<p class="note">
	<?php echo $e( 'This document reflects the commercial snapshot of ' . $data['ref'] . ' v' . $data['version_no'] . ' issued on ' . $data['issued_at'] . '. Downloaded copies cannot be revoked; always verify the current version in your order portal. Taxes and shipping are resolved by the store\'s WooCommerce settings at order time.' ); ?>
</p>

<script type="text/php">
if ( isset( $pdf ) ) {
	$pdf->page_script( '
		$font = $fontMetrics->getFont("dejavu sans", "normal");
		$pdf->text(30, $pdf->get_height() - 22, \'<?php echo $footerJs; ?>\', $font, 7.2, array(0.42,0.46,0.48));
		$pdf->text($pdf->get_width() - 110, $pdf->get_height() - 22, "Page $PAGE_NUM of $PAGE_COUNT", $font, 7.2, array(0.42,0.46,0.48));
	' );
}
</script>

</body>
</html>
