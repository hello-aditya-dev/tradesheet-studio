<?php
/**
 * CSV order sheet export/import.
 *
 * SECURITY MODEL:
 *  - Exported cells are sanitized against spreadsheet-formula injection
 *    (Utils::csvSafe) because buyers open these files in spreadsheet apps.
 *  - Uploaded files are strictly untrusted: quantities are the ONLY buyer
 *    input honored; the UnitPrice column is informational and never used.
 *  - Parsing uses fgetcsv (no eval, no formula interpretation), with row
 *    and size limits enforced by the caller.
 *  - Every uploaded row is re-validated against the immutable server-side
 *    packet version before anything reaches a quotation.
 *
 * @package TradeSheetStudio\Documents
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Documents;

use TradeSheetStudio\Packets\PacketRepository;
use TradeSheetStudio\Support\Money;
use TradeSheetStudio\Support\Utils;

final class CsvOrderSheetService {

	public const MAX_ROWS = 2000;

	public function __construct(
		private PacketRepository $packets
	) {
	}

	/**
	 * Build the buyer-editable order sheet for a packet version.
	 */
	public function export( int $versionId ): string {
		$version = $this->packets->version( $versionId );
		if ( null === $version ) {
			throw new \RuntimeException( esc_html__( 'Packet version not found.', 'tradesheet-studio' ) );
		}

		$packet  = $this->packets->get( (int) $version['packet_id'] );
		$lines   = $this->packets->versionLines( $versionId );
		$snap    = json_decode( (string) $version['snapshot'], true );
		$snap    = is_array( $snap ) ? $snap : array();

		$currency   = (string) ( $snap['packet']['currency'] ?? ( $packet['currency'] ?? '' ) );
		$taxNote    = ! empty( $snap['tax_context']['prices_include_tax'] )
			? __( 'tax-inclusive', 'tradesheet-studio' )
			: __( 'tax-exclusive', 'tradesheet-studio' );

		$handle = fopen( 'php://temp', 'r+' );

		if ( false === $handle ) {
			throw new \RuntimeException( esc_html__( 'Could not create the CSV document.', 'tradesheet-studio' ) );
		}

		// Meta rows (skipped by the parser, readable by humans).
		fwrite( $handle, "# TradeSheet Studio Order Sheet\n" );
		fwrite( $handle, '# ' . sprintf( 'Packet %s "%s" | Version %d | Issued %s | Valid until %s | Currency %s | Prices %s', Utils::csvSafe( (string) $packet['ref'] ), Utils::csvSafe( (string) $packet['title'] ), (int) $version['version_no'], (string) $version['issued_at'], (string) ( $version['expires_at'] ?? '' ), $currency, $taxNote ) . "\n" );
		fwrite( $handle, '# ' . __( 'Enter quantities in the Qty column (respect Min and Step), save as CSV and upload it in your order portal.', 'tradesheet-studio' ) . "\n" );

		fputcsv( $handle, array( 'RowID', 'SKU', 'Product', 'Variation', 'UnitPrice', 'Qty', 'MinQty', 'Step', 'QtyBreaks' ) );

		foreach ( $lines as $line ) {
			$breaks = json_decode( (string) ( $line['qty_breaks'] ?? '' ), true );
			$breaks = is_array( $breaks ) ? $breaks : array();

			$breakTexts = array();
			foreach ( $breaks as $break ) {
				if ( is_array( $break ) && isset( $break['qty'], $break['price'] ) ) {
					$breakTexts[] = (int) $break['qty'] . '+: ' . Money::format( (string) $break['price'], 2 );
				}
			}

			fputcsv(
				$handle,
				array(
					'L' . (string) $line['id'],
					Utils::csvSafe( (string) $line['sku'] ),
					Utils::csvSafe( (string) $line['name'] ),
					Utils::csvSafe( (string) ( $line['variation_summary'] ?? '' ) ),
					Money::format( (string) $line['unit_price'], 2 ),
					'',
					(int) $line['qty_min'],
					(int) $line['qty_step'],
					Utils::csvSafe( implode( ' | ', $breakTexts ) ),
				)
			);
		}

		rewind( $handle );
		$csv = (string) stream_get_contents( $handle );
		fclose( $handle );

		return $csv;
	}

	/**
	 * Parse an uploaded CSV into raw rows (no server data mixed in yet).
	 *
	 * @return array{ok:bool,errors:list<array{code:string,message:string}>,rows:list<array{row_id:string,qty:string,sku:string,product:string}>}
	 */
	public function parse( string $content ): array {
		$errors = array();

		if ( str_starts_with( $content, "\xEF\xBB\xBF" ) ) {
			$content = substr( $content, 3 );
		}

		// Spreadsheet-formula content is inert data here: never interpreted.
		$handle = fopen( 'php://temp', 'r+' );
		if ( false === $handle ) {
			return array(
				'ok'     => false,
				'errors' => array( array( 'code' => 'PARSE_ERROR', 'message' => __( 'The upload could not be read.', 'tradesheet-studio' ) ) ),
				'rows'   => array(),
			);
		}

		fwrite( $handle, $content );
		rewind( $handle );

		$rows    = array();
		$header  = null;
		$rowNum  = 0;

		while ( false !== ( $row = fgetcsv( $handle, 4096, ',', '"' ) ) ) {
			$rowNum++;

			if ( array() === $row || ( 1 === count( $row ) && '' === trim( (string) $row[0] ) ) ) {
				continue;
			}

			$first = trim( (string) $row[0] );

			// Meta/comment rows.
			if ( str_starts_with( $first, '#' ) ) {
				continue;
			}

			$lowered = array_map(
				static function ( $cell ): string {
					return strtolower( trim( (string) $cell ) );
				},
				$row
			);

			if ( null === $header && in_array( 'rowid', $lowered, true ) ) {
				$header = array(
					'row_id'  => array_search( 'rowid', $lowered, true ),
					'qty'     => array_search( 'qty', $lowered, true ),
					'sku'     => array_search( 'sku', $lowered, true ),
					'product' => array_search( 'product', $lowered, true ),
				);
				continue;
			}

			if ( null === $header ) {
				continue;
			}

			$rows[] = array(
				'row_id'  => isset( $row[ $header['row_id'] ] ) ? trim( (string) $row[ $header['row_id'] ] ) : '',
				'qty'     => isset( $row[ $header['qty'] ] ) ? trim( (string) $row[ $header['qty'] ] ) : '',
				'sku'     => isset( $row[ $header['sku'] ] ) ? trim( (string) $row[ $header['sku'] ] ) : '',
				'product' => isset( $row[ $header['product'] ] ) ? trim( (string) $row[ $header['product'] ] ) : '',
			);

			if ( count( $rows ) > self::MAX_ROWS ) {
				$errors[] = array(
					'code'    => 'TOO_MANY_ROWS',
					'message' => sprintf(
						/* translators: %d: max rows. */
						__( 'The file exceeds the supported maximum of %d order rows.', 'tradesheet-studio' ),
						self::MAX_ROWS
					),
				);
				break;
			}
		}

		fclose( $handle );

		if ( null === $header ) {
			$errors[] = array(
				'code'    => 'HEADER_MISSING',
				'message' => __( 'No header row with a RowID column was found. Use the unmodified exported order sheet.', 'tradesheet-studio' ),
			);
		}

		if ( $rows === array() && $errors === array() ) {
			$errors[] = array(
				'code'    => 'NO_ROWS',
				'message' => __( 'The file contains no order rows.', 'tradesheet-studio' ),
			);
		}

		return array(
			'ok'     => $errors === array(),
			'errors' => $errors,
			'rows'   => $rows,
		);
	}

	/**
	 * Validate parsed rows against the immutable server-side version.
	 * Prices come ONLY from the stored packet lines — never from the file.
	 *
	 * @param list<array{row_id:string,qty:string,sku:string,product:string}> $rows
	 * @return array{rows:list<array<string,mixed>>,errors:list<array<string,mixed>>,warnings:list<array<string,mixed>>}
	 */
	public function validateAgainstVersion( array $rows, int $versionId ): array {
		$errors   = array();
		$warnings = array();

		$linesById = array();
		foreach ( $this->packets->versionLines( $versionId ) as $line ) {
			$linesById[ 'L' . (string) $line['id'] ] = $line;
		}

		$validated = array();
		$seen      = array();

		foreach ( $rows as $row ) {
			$rowId = strtoupper( trim( $row['row_id'] ) );

			if ( '' === $rowId ) {
				$errors[] = array(
					'code'    => 'ROW_ID_EMPTY',
					'row_id'  => $rowId,
					'message' => __( 'A row is missing its RowID.', 'tradesheet-studio' ),
				);
				continue;
			}

			if ( ! isset( $linesById[ $rowId ] ) ) {
				$errors[] = array(
					'code'    => 'UNKNOWN_ROW',
					'row_id'  => $rowId,
					'message' => sprintf(
						/* translators: %s: row id. */
						__( 'Row %s does not exist in this packet version.', 'tradesheet-studio' ),
						$rowId
					),
				);
				continue;
			}

			if ( isset( $seen[ $rowId ] ) ) {
				$errors[] = array(
					'code'    => 'DUPLICATE_ROW',
					'row_id'  => $rowId,
					'message' => sprintf(
						/* translators: %s: row id. */
						__( 'Row %s appears more than once.', 'tradesheet-studio' ),
						$rowId
					),
				);
				continue;
			}
			$seen[ $rowId ] = true;

			$line = $linesById[ $rowId ];

			$qtyRaw = trim( $row['qty'] );
			$qty    = 0;

			if ( '' === $qtyRaw ) {
				$qty = 0; // Skipped line.
			} elseif ( ! preg_match( '/^\d{1,6}$/', $qtyRaw ) ) {
				$errors[] = array(
					'code'    => 'QTY_INVALID',
					'row_id'  => $rowId,
					'message' => sprintf(
						/* translators: 1: row id, 2: value. */
						__( 'Row %1$s has an invalid quantity ("%2$s").', 'tradesheet-studio' ),
						$rowId,
						$qtyRaw
					),
				);
				continue;
			} else {
				$qty = (int) $qtyRaw;
			}

			if ( $qty > 0 && $qty < (int) $line['qty_min'] ) {
				$errors[] = array(
					'code'    => 'QTY_BELOW_MIN',
					'row_id'  => $rowId,
					'message' => sprintf(
						/* translators: 1: row id, 2: minimum qty. */
						__( 'Row %1$s quantity is below the minimum of %2$d.', 'tradesheet-studio' ),
						$rowId,
						(int) $line['qty_min']
					),
				);
				continue;
			}

			if ( $qty > 0 && 0 !== ( $qty % max( 1, (int) $line['qty_step'] ) ) ) {
				$errors[] = array(
					'code'    => 'QTY_STEP_MISMATCH',
					'row_id'  => $rowId,
					'message' => sprintf(
						/* translators: 1: row id, 2: step. */
						__( 'Row %1$s quantity must be a multiple of %2$d.', 'tradesheet-studio' ),
						$rowId,
						(int) $line['qty_step']
					),
				);
				continue;
			}

			if ( '' !== $row['sku'] && '' !== (string) $line['sku'] && strtoupper( $row['sku'] ) !== strtoupper( (string) $line['sku'] ) ) {
				$warnings[] = array(
					'code'    => 'SKU_CHANGED',
					'row_id'  => $rowId,
					'message' => sprintf(
						/* translators: 1: row id, 2: uploaded sku, 3: server sku. */
						__( 'Row %1$s SKU ("%2$s") differs from the packet ("%3$s"). The packet value is used.', 'tradesheet-studio' ),
						$rowId,
						$row['sku'],
						(string) $line['sku']
					),
				);
			}

			if ( '' !== $row['product'] && mb_strtolower( $row['product'] ) !== mb_strtolower( (string) $line['name'] ) ) {
				$warnings[] = array(
					'code'    => 'PRODUCT_NAME_CHANGED',
					'row_id'  => $rowId,
					'message' => sprintf(
						/* translators: %s: row id. */
						__( 'Row %s product name differs from the packet; the packet value is used.', 'tradesheet-studio' ),
						$rowId
					),
				);
			}

			$validated[] = array(
				'row_id'          => $rowId,
				'line_id'         => (int) $line['id'],
				'product_id'      => (int) $line['product_id'],
				'variation_id'    => (int) $line['variation_id'],
				'sku'             => (string) $line['sku'],
				'name'            => (string) $line['name'],
				'variation_summary' => (string) ( $line['variation_summary'] ?? '' ),
				'qty'             => $qty,
				'unit_price'      => (string) $line['unit_price'],
				'qty_min'         => (int) $line['qty_min'],
				'qty_step'        => (int) $line['qty_step'],
				'qty_breaks'      => json_decode( (string) ( $line['qty_breaks'] ?? '' ), true ) ?: array(),
			);
		}

		return array(
			'rows'     => $validated,
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}
}
