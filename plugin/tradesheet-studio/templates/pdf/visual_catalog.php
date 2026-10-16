<?php
/**
 * Template: Visual Catalog — editorial layout with product imagery
 * (portrait, two cards per row).
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
		.doc-title { text-align: right; font-size: 16pt; font-weight: bold; }
		.doc-ref { text-align: right; font-size: 9pt; color: #5b656d; }
		.rule { border-bottom: 2pt solid #0f766e; margin: 8px 0 12px 0; }
		.meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 8.5pt; }
		.meta td { padding: 2px 4px; vertical-align: top; }
		.meta .k { color: #5b656d; width: 84px; }
		table.grid { width: 100%; border-collapse: separate; border-spacing: 8px; }
		table.grid td { width: 50%; vertical-align: top; padding: 0; }
		.card { border: 0.7pt solid #d5dfdc; border-radius: 4pt; page-break-inside: avoid; }
		.card img { width: 100%; max-height: 150px; }
		.ph { width: 100%; height: 110px; background: #f2f7f6; border-bottom: 0.7pt solid #d5dfdc; text-align: center; color: #9aa7ad; font-size: 8pt; padding-top: 46px; box-sizing: border-box; }
		.card .body { padding: 8px 10px; }
		.name { font-size: 10pt; font-weight: bold; margin-bottom: 2px; }
		.variation { font-size: 8pt; color: #0f766e; margin-bottom: 4px; }
		.desc { font-size: 8pt; color: #4b555c; line-height: 1.4; margin-bottom: 6px; }
		.pricebar { border-top: 0.7pt solid #e4ece9; padding-top: 5px; margin-top: 4px; }
		.price { font-size: 12pt; font-weight: bold; color: #0f5e57; }
		.qtyrules { font-size: 7.6pt; color: #5b656d; margin-top: 1px; }
		.note { margin-top: 12px; font-size: 7.6pt; color: #5b656d; }
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
			<div class="doc-title"><?php echo $e( $data['title'] ); ?></div>
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

<table class="grid">
	<?php
	$chunks = array_chunk( $data['lines'], 2 );
	foreach ( $chunks as $pair ) :
		?>
		<tr>
		<?php foreach ( $pair as $line ) : ?>
			<td>
				<div class="card">
					<?php if ( '' !== (string) $line['image'] ) : ?>
						<img src="<?php echo $e( $line['image'] ); ?>" />
					<?php else : ?>
						<div class="ph">No product image</div>
					<?php endif; ?>
					<div class="body">
						<div class="name"><?php echo $e( $line['name'] ); ?></div>
						<div class="variation">
							<?php echo $e( $line['variation_summary'] !== '' ? $line['variation_summary'] : 'SKU: ' . ( $line['sku'] !== '' ? $line['sku'] : $line['row_id'] ) ); ?>
						</div>
						<?php if ( '' !== trim( (string) $line['description'] ) ) : ?>
							<div class="desc"><?php echo $e( mb_substr( trim( (string) $line['description'] ), 0, 320 ) ); ?></div>
						<?php endif; ?>
						<div class="pricebar">
							<span class="price"><?php echo $e( $sym ) . ' ' . $e( $fmt( (string) $line['unit_price'] ) ); ?></span>
							<div class="qtyrules">
								<?php
								$qtyBits = array( 'Minimum ' . (int) $line['qty_min'] );
								if ( (int) $line['qty_step'] > 1 ) {
									$qtyBits[] = 'steps of ' . (int) $line['qty_step'];
								}
								if ( '' !== $breakText( $line ) ) {
									$qtyBits[] = $breakText( $line );
								}
								echo $e( implode( '  ·  ', $qtyBits ) );
								?>
							</div>
						</div>
					</div>
				</div>
			</td>
		<?php endforeach; ?>
		<?php if ( 1 === count( $pair ) ) : ?>
			<td></td>
		<?php endif; ?>
		</tr>
	<?php endforeach; ?>
</table>

<p class="note">
	<?php echo $e( 'Prices reflect the issued commercial snapshot of ' . $data['ref'] . ' v' . $data['version_no'] . ' and remain anchored to that version. Downloaded copies cannot be revoked. Taxes and shipping are resolved by the store\'s WooCommerce settings at order time.' ); ?>
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
