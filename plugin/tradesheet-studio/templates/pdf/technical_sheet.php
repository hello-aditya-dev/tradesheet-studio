<?php
/**
 * Template: Technical Sheet — specification-driven presentation for
 * component manufacturers and technical distributors (portrait).
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
		@page { margin: 40px 34px 42px 34px; }
		body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #22272b; margin: 0; }
		.brand { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
		.company { font-size: 14pt; font-weight: bold; color: #0f766e; }
		.company-sub { font-size: 8pt; color: #5b656d; margin-top: 2px; }
		.doc-title { text-align: right; font-size: 15pt; font-weight: bold; }
		.doc-ref { text-align: right; font-size: 9pt; color: #5b656d; }
		.rule { border-bottom: 2pt solid #0f766e; margin: 8px 0 10px 0; }
		.meta { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 8.5pt; }
		.meta td { padding: 2px 4px; vertical-align: top; }
		.meta .k { color: #5b656d; width: 84px; }
		.product { page-break-inside: avoid; border: 0.7pt solid #d5dfdc; margin-bottom: 10px; }
		.p-head { background: #f2f7f6; border-bottom: 0.7pt solid #d5dfdc; padding: 6px 10px; }
		.p-name { font-size: 10.5pt; font-weight: bold; }
		.p-sku { font-family: "DejaVu Sans Mono", monospace; font-size: 8pt; color: #37474f; }
		.p-body { width: 100%; border-collapse: collapse; }
		.p-body td { vertical-align: top; padding: 8px 10px; }
		.imgcell { width: 118px; }
		.imgcell img { width: 100px; }
		.ph { width: 100px; height: 74px; background: #eef4f2; border: 0.5pt dashed #b8ccc7; color: #9aa7ad; font-size: 7.5pt; text-align: center; padding-top: 30px; box-sizing: border-box; }
		table.specs { width: 100%; border-collapse: collapse; }
		table.specs th { text-align: left; background: #0f766e; color: #ffffff; font-size: 7.8pt; padding: 3px 6px; width: 170px; }
		table.specs td { border: 0.5pt solid #dfe8e5; font-size: 8pt; padding: 3px 6px; }
		.price-cell { font-weight: bold; color: #0f5e57; }
		.desc { margin: 6px 0 0 0; font-size: 8pt; color: #4b555c; line-height: 1.4; }
		.note { margin-top: 10px; font-size: 7.6pt; color: #5b656d; }
	</style>
</head>
<body>

<table class="brand">
	<tr>
		<td>
			<?php if ( '' !== (string) $data['company']['logo'] ) : ?>
				<img src="<?php echo $e( $data['company']['logo'] ); ?>" style="max-height: 48px;" />
			<?php endif; ?>
			<div class="company"><?php echo $e( $data['company']['name'] ); ?></div>
			<div class="company-sub"><?php echo $e( trim( $data['company']['address'] ) ); ?></div>
			<div class="company-sub"><?php echo $e( trim( $data['company']['email'] . ( '' !== $data['company']['phone'] ? '  ·  ' . $data['company']['phone'] : '' ) ) ); ?></div>
		</td>
		<td style="text-align: right;">
			<div class="doc-title">Technical Offer Sheet</div>
			<div class="doc-ref"><?php echo $e( $data['ref'] ); ?> · Version <?php echo $e( $data['version_no'] ); ?></div>
		</td>
	</tr>
</table>

<div class="rule"></div>

<table class="meta">
	<tr>
		<td class="k">Prepared for</td><td><?php echo $e( $data['buyer'] ); ?></td>
		<td class="k">Issued</td><td><?php echo $e( $data['issued_at'] ); ?></td>
	</tr>
	<tr>
		<td class="k">Valid until</td><td><?php echo $e( $data['expires_at'] ); ?></td>
		<td class="k">Prices</td><td><?php echo $e( $data['currency'] . ' — ' . $taxLabel ); ?></td>
	</tr>
</table>

<?php foreach ( $data['lines'] as $line ) : ?>
	<div class="product">
		<div class="p-head">
			<span class="p-name"><?php echo $e( $line['name'] ); ?></span>
			<?php if ( '' !== (string) $line['variation_summary'] ) : ?>
				<span style="color:#0f766e;"> — <?php echo $e( $line['variation_summary'] ); ?></span>
			<?php endif; ?>
			<div class="p-sku">SKU: <?php echo $e( $line['sku'] !== '' ? $line['sku'] : $line['row_id'] ); ?></div>
		</div>
		<table class="p-body">
			<tr>
				<td class="imgcell">
					<?php if ( '' !== (string) $line['image'] ) : ?>
						<img src="<?php echo $e( $line['image'] ); ?>" />
					<?php else : ?>
						<div class="ph">No image</div>
					<?php endif; ?>
				</td>
				<td>
					<table class="specs">
						<tr><th>Unit price</th><td class="price-cell"><?php echo $e( $sym ) . ' ' . $e( $fmt( (string) $line['unit_price'] ) ); ?> (<?php echo $e( $taxLabel ); ?>)</td></tr>
						<tr><th>Ordering</th><td><?php echo $e( 'Minimum quantity ' . (int) $line['qty_min'] . ( (int) $line['qty_step'] > 1 ? ' · steps of ' . (int) $line['qty_step'] : '' ) . ( '' !== $breakText( $line ) ? ' · Breaks: ' . $breakText( $line ) : '' ) ); ?></td></tr>
						<?php foreach ( ( $line['attributes'] ?? array() ) as $attribute ) : ?>
							<?php if ( is_array( $attribute ) && isset( $attribute['label'], $attribute['value'] ) ) : ?>
								<tr><th><?php echo $e( $attribute['label'] ); ?></th><td><?php echo $e( $attribute['value'] ); ?></td></tr>
							<?php endif; ?>
						<?php endforeach; ?>
					</table>
					<?php if ( '' !== trim( (string) $line['description'] ) ) : ?>
						<p class="desc"><?php echo $e( mb_substr( trim( (string) $line['description'] ), 0, 600 ) ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
	</div>
<?php endforeach; ?>

<p class="note">
	<?php echo $e( 'Specifications and prices reflect the issued commercial snapshot of ' . $data['ref'] . ' v' . $data['version_no'] . '. Verify product suitability for your application. Downloaded copies cannot be revoked. Taxes and shipping are resolved by the store\'s WooCommerce settings at order time.' ); ?>
</p>

<script type="text/php">
if ( isset( $pdf ) ) {
	$pdf->page_script( '
		$font = $fontMetrics->getFont("dejavu sans", "normal");
		$pdf->text(34, $pdf->get_height() - 24, \'<?php echo $footerJs; ?>\', $font, 7.2, array(0.42,0.46,0.48));
		$pdf->text($pdf->get_width() - 112, $pdf->get_height() - 24, "Page $PAGE_NUM of $PAGE_COUNT", $font, 7.2, array(0.42,0.46,0.48));
	' );
}
</script>

</body>
</html>
