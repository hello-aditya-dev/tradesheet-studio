<?php
/**
 * Buyer portal template — standalone, theme-independent page.
 *
 * Receives $data from BuyerPortal::render(). CSS is inlined and scoped under
 * .tssbp-* so merchant themes cannot break it and it cannot break them;
 * JavaScript (assets/buyer.js) is progressive enhancement only.
 *
 * @package TradeSheetStudio\Buyers
 */

declare( strict_types=1 );

/** @var array<string,mixed> $data */

use TradeSheetStudio\Support\Money;

$e    = static function ( mixed $value ): string {
	return esc_html( (string) $value );
};
$ea   = static function ( mixed $value ): string {
	return esc_attr( (string) $value );
};
$sym  = (string) $data['packet']['symbol'];
$fmt  = static function ( string $price ) use ( $sym ): string {
	return $sym . ' ' . Money::format( $price, 2, '.', ',' );
};
$breaksText = static function ( array $line ) use ( $fmt ): string {
	$parts = array();
	foreach ( ( $line['qty_breaks'] ?? array() ) as $break ) {
		if ( is_array( $break ) && isset( $break['qty'], $break['price'] ) ) {
			$parts[] = (int) $break['qty'] . '+: ' . $fmt( (string) $break['price'] );
		}
	}
	return implode( ' · ', $parts );
};

$taxLabel = $data['prices_include_tax']
	? __( 'Prices include tax; store tax settings apply at order time.', 'tradesheet-studio' )
	: __( 'Prices exclude tax; taxes are added by the store at order time.', 'tradesheet-studio' );

$acceptStates = array( 'approved', 'revised' );
?>
<!DOCTYPE html>
<html lang="<?php echo $ea( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<meta name="referrer" content="no-referrer" />
	<title><?php echo $e( $data['packet']['title'] ); ?> — <?php echo $e( $data['packet']['ref'] ); ?></title>
	<style>
		:root { --tss-teal:#0f766e; --tss-teal-dark:#0f5e57; --tss-ink:#22272b; --tss-muted:#5b656d; --tss-line:#dfe5e2; --tss-bg:#f4f6f5; --tss-amber:#b45309; --tss-red:#b3261e; }
		* { box-sizing: border-box; }
		body.tssbp { margin:0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: var(--tss-bg); color: var(--tss-ink); line-height: 1.5; }
		.tssbp main { max-width: 960px; margin: 0 auto; padding: 16px 16px 48px; }
		.tssbp header.tssbp-head { background: #fff; border-bottom: 1px solid var(--tss-line); padding: 14px 16px; }
		.tssbp-head-inner { max-width: 960px; margin: 0 auto; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; }
		.tssbp-company { font-weight: 700; color: var(--tss-teal-dark); font-size: 15px; }
		.tssbp-brandmark { font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: var(--tss-muted); }
		.tssbp h1 { font-size: 20px; margin: 0; }
		.tssbp h2 { font-size: 15px; margin: 28px 0 10px; color: var(--tss-teal-dark); }
		.tssbp .meta { font-size: 13px; color: var(--tss-muted); margin-top: 4px; }
		.tssbp .badge { display:inline-block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; padding:3px 8px; border-radius:4px; }
		.tssbp .badge.live { background:#e6f2ef; color:var(--tss-teal-dark); }
		.tssbp .badge.requested, .tssbp .badge.in_review { background:#fdf3e2; color:var(--tss-amber); }
		.tssbp .badge.approved, .tssbp .badge.revised { background:#e6f2ef; color:var(--tss-teal-dark); }
		.tssbp .badge.accepted, .tssbp .badge.ordered { background:#e2efdd; color:#33691e; }
		.tssbp .badge.rejected, .tssbp .badge.expired, .tssbp .badge.revoked { background:#f9e5e3; color:var(--tss-red); }
		.tssbp .card { background:#fff; border:1px solid var(--tss-line); border-radius:8px; padding:16px; margin-top:12px; }
		.tssbp table { width:100%; border-collapse: collapse; font-size: 13px; }
		.tssbp th { text-align:left; color: var(--tss-muted); font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid var(--tss-line); padding:8px 8px; }
		.tssbp td { border-bottom:1px solid var(--tss-line); padding:10px 8px; vertical-align: top; }
		.tssbp .num { text-align:right; white-space:nowrap; }
		.tssbp .sku { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12px; color:var(--tss-muted); }
		.tssbp .variation { color: var(--tss-teal-dark); font-size:12px; }
		.tssbp .breaks { font-size:11px; color:var(--tss-muted); margin-top:2px; }
		.tssbp input[type=number] { width: 84px; padding: 8px; border:1px solid var(--tss-line); border-radius:6px; font-size:14px; }
		.tssbp input[type=number]:focus { outline: 2px solid var(--tss-teal); outline-offset: 0; }
		.tssbp .estimate { font-size:13px; }
		.tssbp .total { font-weight:700; color: var(--tss-teal-dark); }
		.tssbp .toolbar { display:flex; flex-wrap:wrap; gap:8px; margin:10px 0 0; align-items:center; }
		.tssbp input[type=search] { flex:1 1 220px; padding:9px 12px; border:1px solid var(--tss-line); border-radius:6px; font-size:14px; }
		.tssbp button, .tssbp a.btn { display:inline-block; -webkit-appearance:none; appearance:none; border:0; border-radius:6px; padding:11px 18px; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; min-height:44px; }
		.tssbp .btn-primary { background: var(--tss-teal); color:#fff; }
		.tssbp .btn-primary:hover { background: var(--tss-teal-dark); }
		.tssbp .btn-secondary { background:#eef2f0; color: var(--tss-ink); }
		.tssbp .btn-secondary:hover { background:#e2e8e5; }
		.tssbp .notice { border-radius:6px; padding:12px 14px; margin-top:12px; font-size:14px; }
		.tssbp .notice.info { background:#e6f2ef; color:var(--tss-teal-dark); }
		.tssbp .notice.error { background:#f9e5e3; color:var(--tss-red); }
		.tssbp .notice.warn { background:#fdf3e2; color:var(--tss-amber); }
		.tssbp textarea { width:100%; min-height:70px; border:1px solid var(--tss-line); border-radius:6px; padding:10px; font: inherit; }
		.tssbp .report li { margin: 6px 0; font-size: 13px; }
		.tssbp .sev-blocking { color: var(--tss-red); font-weight: 700; }
		.tssbp .sev-warning { color: var(--tss-amber); font-weight: 700; }
		.tssbp .dl-links { display:flex; flex-wrap:wrap; gap:10px; }
		.tssbp footer { margin-top:32px; font-size:12px; color:var(--tss-muted); }
		.tssbp .changes li { font-size:13px; margin:4px 0; }
		@media (max-width: 720px) {
			.tssbp .hide-mobile { display:none; }
			.tssbp td, .tssbp th { padding:8px 4px; }
			.tssbp input[type=number] { width:72px; }
		}
	</style>
</head>
<body class="tssbp">
<header class="tssbp-head">
	<div class="tssbp-head-inner">
		<div>
			<div class="tssbp-company"><?php echo $e( $data['company']['name'] ); ?></div>
			<div class="tssbp-brandmark"><?php echo $e( __( 'TradeSheet Studio order portal', 'tradesheet-studio' ) ); ?></div>
		</div>
		<div style="text-align:right;">
			<div class="badge live"><?php echo $e( sprintf( __( 'Version v%d · valid until %s', 'tradesheet-studio' ), (int) $data['packet']['version_no'], (string) $data['packet']['valid_until'] ) ); ?></div>
		</div>
	</div>
</header>

<main>
	<h1><?php echo $e( $data['packet']['title'] ); ?></h1>
	<div class="meta">
		<?php
		echo $e(
			sprintf(
				/* translators: 1: packet ref, 2: buyer, 3: issued at. */
				__( 'Offer %1$s · Prepared for %2$s · Issued %3$s', 'tradesheet-studio' ),
				(string) $data['packet']['ref'],
				(string) $data['packet']['customer'],
				(string) $data['packet']['issued_at']
			)
		);
		?>
	</div>

	<?php if ( '' !== (string) $data['message'] ) : ?>
		<div class="notice info" role="status"><?php echo $e( $data['message'] ); ?></div>
	<?php endif; ?>

	<?php if ( '' !== (string) $data['error'] ) : ?>
		<div class="notice error" role="alert"><?php echo $e( $data['error'] ); ?></div>
	<?php endif; ?>

	<?php if ( $data['order_id'] > 0 ) : ?>
		<div class="notice info" role="status">
			<?php
			echo $e(
				sprintf(
					/* translators: %d: order id. */
					__( 'WooCommerce order #%d was created at your accepted terms. Complete payment through the store checkout (link sent by email).', 'tradesheet-studio' ),
					(int) $data['order_id']
				)
			);
			?>
		</div>
	<?php endif; ?>

	<h2 id="tss-lines"><?php echo $e( __( 'Products & quantities', 'tradesheet-studio' ) ); ?></h2>

	<?php if ( null === $data['quote'] || in_array( (string) $data['quote']['status'], array( 'rejected', 'superseded', 'cancelled' ), true ) ) : ?>
	<div class="card">
		<div class="toolbar">
			<input type="search" id="tss-filter" placeholder="<?php echo $ea( __( 'Search SKU or product…', 'tradesheet-studio' ) ); ?>" aria-label="<?php echo $ea( __( 'Search products', 'tradesheet-studio' ) ); ?>" />
		</div>
		<form method="post" action="<?php echo $ea( $data['action_url'] ); ?>">
			<input type="hidden" name="tss_action" value="submit_quote" />
			<?php if ( '' !== (string) $data['token'] ) : ?>
				<input type="hidden" name="t" value="<?php echo $ea( $data['token'] ); ?>" />
			<?php endif; ?>
			<?php if ( '' !== (string) $data['nonce'] ) : ?>
				<input type="hidden" name="tss_nonce" value="<?php echo $ea( $data['nonce'] ); ?>" />
			<?php endif; ?>
			<table aria-describedby="tss-lines">
				<thead>
				<tr>
					<th><?php echo $e( __( 'Product', 'tradesheet-studio' ) ); ?></th>
					<th class="num hide-mobile"><?php echo $e( __( 'Unit price', 'tradesheet-studio' ) ); ?></th>
					<th class="num"><?php echo $e( __( 'Qty', 'tradesheet-studio' ) ); ?></th>
					<th class="num hide-mobile"><?php echo $e( __( 'Line estimate', 'tradesheet-studio' ) ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $data['lines'] as $line ) : ?>
					<tr class="tss-row" data-search="<?php echo $ea( mb_strtolower( (string) $line['name'] . ' ' . (string) $line['sku'] . ' ' . (string) $line['variation_summary'] ) ); ?>">
						<td>
							<strong><?php echo $e( $line['name'] ); ?></strong>
							<?php if ( '' !== (string) $line['sku'] ) : ?>
								<div class="sku"><?php echo $e( $line['sku'] ); ?></div>
							<?php endif; ?>
							<?php if ( '' !== (string) $line['variation_summary'] ) : ?>
								<div class="variation"><?php echo $e( $line['variation_summary'] ); ?></div>
							<?php endif; ?>
							<?php if ( '' !== $breaksText( $line ) ) : ?>
								<div class="breaks"><?php echo $e( $breaksText( $line ) ); ?></div>
							<?php endif; ?>
						</td>
						<td class="num hide-mobile" data-base="<?php echo $ea( (string) $line['unit_price'] ); ?>"><?php echo $e( $fmt( (string) $line['unit_price'] ) ); ?></td>
						<td class="num">
							<input
								type="number"
								name="tss_qty[<?php echo $ea( (string) $line['line_id'] ); ?>]"
								min="0"
								max="100000"
								step="<?php echo $ea( (string) max( 1, (int) $line['qty_step'] ) ); ?>"
								placeholder="<?php echo $ea( (string) $line['qty_min'] ); ?>"
								value="0"
								data-min="<?php echo $ea( (string) $line['qty_min'] ); ?>"
								data-step="<?php echo $ea( (string) $line['qty_step'] ); ?>"
								data-price="<?php echo $ea( (string) $line['unit_price'] ); ?>"
								data-breaks="<?php echo $ea( wp_json_encode( $line['qty_breaks'] ) ); ?>"
								aria-label="<?php echo $ea( sprintf( /* translators: %s: product name */ __( 'Quantity for %s', 'tradesheet-studio' ), (string) $line['name'] ) ); ?>"
							/>
						</td>
						<td class="num estimate hide-mobile" data-line-estimate>—</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<div class="toolbar" style="justify-content:space-between;">
				<div>
					<div class="meta"><?php echo $e( __( 'Estimate', 'tradesheet-studio' ) ); ?>: <span class="total" id="tss-total">—</span></div>
					<div class="meta" style="font-size:11px;"><?php echo $e( $taxLabel ); ?></div>
				</div>
				<button type="submit" class="btn-primary"><?php echo $e( __( 'Submit quotation request', 'tradesheet-studio' ) ); ?></button>
			</div>
			<div style="margin-top:10px;">
				<label for="tss-notes" class="meta"><?php echo $e( __( 'Note to the merchant (optional)', 'tradesheet-studio' ) ); ?></label>
				<textarea id="tss-notes" name="tss_notes" maxlength="2000"></textarea>
			</div>
		</form>
	</div>
	<?php else : ?>
		<div class="card">
			<p class="meta"><?php echo $e( __( 'Quantities are locked while a quotation is open. Ask the merchant to revise the offer if you need different quantities.', 'tradesheet-studio' ) ); ?></p>
		</div>
	<?php endif; ?>

	<h2 id="tss-documents"><?php echo $e( __( 'Downloads', 'tradesheet-studio' ) ); ?></h2>
	<div class="card">
		<?php if ( $data['documents'] === array() ) : ?>
			<p class="meta"><?php echo $e( __( 'Documents for this version have not been generated yet.', 'tradesheet-studio' ) ); ?></p>
		<?php else : ?>
			<div class="dl-links">
				<?php
				$dlLabels = array(
					'pdf' => __( 'Download PDF catalog', 'tradesheet-studio' ),
					'csv' => __( 'Download CSV order sheet', 'tradesheet-studio' ),
					'zip' => __( 'Download all documents (ZIP)', 'tradesheet-studio' ),
				);
				foreach ( $data['documents'] as $doc ) :
					$type = (string) $doc['type'];
					$url  = (string) $data['action_url'] . '&download=' . $ea( $type );
					?>
					<a class="btn btn-secondary" href="<?php echo $ea( $url ); ?>"><?php echo $e( $dlLabels[ $type ] ?? $type ); ?></a>
				<?php endforeach; ?>
			</div>
			<p class="meta" style="margin-top:10px;"><?php echo $e( __( 'Downloaded documents are issued copies: they show their version and validity date and cannot be revoked retroactively.', 'tradesheet-studio' ) ); ?></p>
		<?php endif; ?>
	</div>

	<h2 id="tss-csv"><?php echo $e( __( 'Upload a completed order sheet', 'tradesheet-studio' ) ); ?></h2>
	<div class="card">
		<?php if ( null === $data['csv_preview'] && ( null === $data['quote'] || in_array( (string) $data['quote']['status'], array( 'rejected', 'superseded', 'cancelled' ), true ) ) ) : ?>
			<form method="post" action="<?php echo $ea( $data['action_url'] ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="tss_action" value="upload_csv" />
				<?php if ( '' !== (string) $data['token'] ) : ?>
					<input type="hidden" name="t" value="<?php echo $ea( $data['token'] ); ?>" />
				<?php endif; ?>
				<?php if ( '' !== (string) $data['nonce'] ) : ?>
					<input type="hidden" name="tss_nonce" value="<?php echo $ea( $data['nonce'] ); ?>" />
				<?php endif; ?>
				<p class="meta"><?php echo $e( __( 'Download the CSV above, enter quantities in the Qty column, save and upload it here. Prices in the file are never used — every price is re-validated against this offer.', 'tradesheet-studio' ) ); ?></p>
				<div class="toolbar">
					<input type="file" name="tss_csv" accept=".csv,text/csv" required aria-label="<?php echo $ea( __( 'CSV file', 'tradesheet-studio' ) ); ?>" />
					<button type="submit" class="btn-primary"><?php echo $e( __( 'Upload CSV', 'tradesheet-studio' ) ); ?></button>
				</div>
			</form>
		<?php elseif ( null !== $data['csv_preview'] ) : ?>
			<form method="post" action="<?php echo $ea( $data['action_url'] ); ?>">
				<input type="hidden" name="tss_action" value="confirm_csv" />
				<?php if ( '' !== (string) $data['token'] ) : ?>
					<input type="hidden" name="t" value="<?php echo $ea( $data['token'] ); ?>" />
				<?php endif; ?>
				<?php if ( '' !== (string) $data['nonce'] ) : ?>
					<input type="hidden" name="tss_nonce" value="<?php echo $ea( $data['nonce'] ); ?>" />
				<?php endif; ?>
				<table>
					<thead>
					<tr>
						<th><?php echo $e( __( 'Row', 'tradesheet-studio' ) ); ?></th>
						<th><?php echo $e( __( 'Product', 'tradesheet-studio' ) ); ?></th>
						<th class="num"><?php echo $e( __( 'Qty', 'tradesheet-studio' ) ); ?></th>
						<th class="num"><?php echo $e( __( 'Unit price', 'tradesheet-studio' ) ); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ( $data['csv_preview'] as $row ) : ?>
						<?php if ( (int) ( $row['qty'] ?? 0 ) > 0 ) : ?>
							<tr>
								<td class="sku"><?php echo $e( (string) ( $row['row_id'] ?? '' ) ); ?></td>
								<td><?php echo $e( (string) ( $row['name'] ?? '' ) ); ?></td>
								<td class="num"><?php echo $e( (string) ( $row['qty'] ?? 0 ) ); ?></td>
								<td class="num"><?php echo $e( $fmt( (string) ( $row['unit_price'] ?? '0.0000' ) ) ); ?></td>
							</tr>
						<?php endif; ?>
					<?php endforeach; ?>
					</tbody>
				</table>
				<div class="toolbar">
					<button type="submit" class="btn-primary"><?php echo $e( __( 'Confirm and submit request', 'tradesheet-studio' ) ); ?></button>
				</div>
			</form>
		<?php else : ?>
			<p class="meta"><?php echo $e( __( 'CSV upload is disabled while a quotation is open.', 'tradesheet-studio' ) ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( null !== $data['quote'] ) : ?>
		<?php
		$quote     = $data['quote'];
		$canAccept = in_array( (string) $quote['status'], $acceptStates, true );
		?>
		<h2 id="tss-quote"><?php echo $e( __( 'Your quotation', 'tradesheet-studio' ) ); ?></h2>
		<div class="card">
			<div class="toolbar" style="justify-content:space-between;">
				<div>
					<span class="badge <?php echo $ea( (string) $quote['status'] ); ?>"><?php echo $e( (string) $quote['status'] ); ?></span>
					<span class="meta" style="margin-left:8px;">
						<?php
						echo $e(
							sprintf(
								/* translators: 1: quote ref, 2: revision no. */
								__( '%1$s · revision R%2$d', 'tradesheet-studio' ),
								(string) $quote['ref'],
								(int) $quote['revision_no']
							)
						);
						?>
					</span>
				</div>
				<div class="meta"><?php echo $e( sprintf( /* translators: %s: date */ __( 'Valid until %s', 'tradesheet-studio' ), (string) $quote['expires_at'] ) ); ?></div>
			</div>

			<?php if ( '' !== (string) $quote['notes_merchant'] ) : ?>
				<div class="notice info"><?php echo $e( sprintf( /* translators: %s: note */ __( 'Merchant note: %s', 'tradesheet-studio' ), (string) $quote['notes_merchant'] ) ); ?></div>
			<?php endif; ?>

			<table style="margin-top:10px;">
				<thead>
				<tr>
					<th><?php echo $e( __( 'Product', 'tradesheet-studio' ) ); ?></th>
					<th class="num"><?php echo $e( __( 'Qty', 'tradesheet-studio' ) ); ?></th>
					<th class="num"><?php echo $e( __( 'Unit price', 'tradesheet-studio' ) ); ?></th>
					<th class="num"><?php echo $e( __( 'Total', 'tradesheet-studio' ) ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $quote['lines'] as $line ) : ?>
					<tr>
						<td>
							<?php echo $e( (string) $line['name'] ); ?>
							<?php if ( '' !== (string) $line['merchant_note'] ) : ?>
								<div class="breaks"><?php echo $e( sprintf( /* translators: %s: note */ __( 'Merchant: %s', 'tradesheet-studio' ), (string) $line['merchant_note'] ) ); ?></div>
							<?php endif; ?>
						</td>
						<td class="num"><?php echo $e( (string) $line['qty'] ); ?></td>
						<td class="num"><?php echo $e( $fmt( (string) $line['unit_price'] ) ); ?></td>
						<td class="num"><?php echo $e( $fmt( (string) $line['line_total'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
				<tfoot>
				<tr>
					<td colspan="3" class="num"><strong><?php echo $e( __( 'Quoted total', 'tradesheet-studio' ) ); ?></strong></td>
					<td class="num total"><?php echo $e( $fmt( (string) $quote['total'] ) ); ?></td>
				</tr>
				</tfoot>
			</table>
			<p class="meta" style="font-size:11px;"><?php echo $e( $taxLabel ); ?> <?php echo $e( __( 'Shipping is determined by the store at order time.', 'tradesheet-studio' ) ); ?></p>

			<?php $report = is_array( $quote['report'] ) ? ( $quote['report']['items'] ?? array() ) : array(); ?>
			<?php if ( is_array( $report ) && $report !== array() ) : ?>
				<h2 style="margin-top:16px;"><?php echo $e( __( 'Integrity findings at submission', 'tradesheet-studio' ) ); ?></h2>
				<ul class="report" style="padding-left:20px;">
					<?php foreach ( $report as $item ) : ?>
						<?php if ( is_array( $item ) ) : ?>
							<li>
								<span class="sev-<?php echo $ea( (string) ( $item['severity'] ?? 'warning' ) ); ?>"><?php echo $e( strtoupper( (string) ( $item['severity'] ?? 'warning' ) ) ); ?></span>
								— <?php echo $e( (string) ( $item['message'] ?? '' ) ); ?>
							</li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( $canAccept ) : ?>
				<form method="post" action="<?php echo $ea( $data['action_url'] ); ?>" style="margin-top:14px;">
					<input type="hidden" name="tss_action" value="accept_quote" />
					<input type="hidden" name="tss_quote_id" value="<?php echo $ea( (string) $quote['id'] ); ?>" />
					<?php if ( '' !== (string) $data['token'] ) : ?>
						<input type="hidden" name="t" value="<?php echo $ea( $data['token'] ); ?>" />
					<?php endif; ?>
					<?php if ( '' !== (string) $data['nonce'] ) : ?>
						<input type="hidden" name="tss_nonce" value="<?php echo $ea( $data['nonce'] ); ?>" />
					<?php endif; ?>
					<button type="submit" class="btn-primary"><?php echo $e( __( 'Accept quotation and create order', 'tradesheet-studio' ) ); ?></button>
					<p class="meta" style="margin-top:8px;font-size:11px;"><?php echo $e( __( 'Accepting creates a WooCommerce order at the quoted terms; payment happens through the store checkout. Submitting twice cannot create two orders.', 'tradesheet-studio' ) ); ?></p>
				</form>
			<?php endif; ?>

			<?php if ( 'ordered' === (string) $quote['status'] && (int) $quote['order_id'] > 0 ) : ?>
				<div class="notice info" style="margin-top:12px;">
					<?php
					echo $e(
						sprintf(
							/* translators: %d: order id. */
							__( 'Converted into WooCommerce order #%d at the negotiated terms.', 'tradesheet-studio' ),
							(int) $quote['order_id']
						)
					);
					?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<footer>
		<?php
		echo $e(
			sprintf(
				/* translators: 1: ref, 2: version no, 3: checksum prefix. */
				__( 'DealPacket %1$s v%2$d · snapshot checksum %3$s… · generated by TradeSheet Studio.', 'tradesheet-studio' ),
				(string) $data['packet']['ref'],
				(int) $data['packet']['version_no'],
				(string) $data['packet']['checksum']
			)
		);
		?>
	</footer>
</main>

<script src="<?php echo $ea( (string) $data['assets_url'] ); ?>buyer.js" defer></script>
</body>
</html>
