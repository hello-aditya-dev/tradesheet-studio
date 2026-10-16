<?php
/**
 * Quote endpoints — merchant review, revision and conversion.
 *
 * @package TradeSheetStudio\Api
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Api;

use TradeSheetStudio\Core\Plugin;
use TradeSheetStudio\Support\Money;
use TradeSheetStudio\Support\ValidationException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class QuotesController {

	public function __construct( private Plugin $plugin ) {
	}

	public function register(): void {
		$manage = array( \TradeSheetStudio\Security\Capabilities::class, 'restCanManage' );

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/quotes',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'listQuotes' ),
					'permission_callback' => $manage,
					'args'                => array(
						'status'   => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_key' ),
						'page'     => array( 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ),
						'per_page' => array( 'type' => 'integer', 'default' => 20, 'sanitize_callback' => 'absint' ),
					),
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/quotes/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'getQuote' ),
					'permission_callback' => $manage,
				),
			)
		);

		foreach ( array( 'review', 'approve', 'reject', 'revise', 'convert' ) as $action ) {
			register_rest_route(
				Routes::NAMESPACE_V1,
				'/quotes/(?P<id>\d+)/' . $action,
				array(
					array(
						'methods'             => 'POST',
						'callback'            => function ( WP_REST_Request $request ) use ( $action ) {
							return $this->action( $request, $action );
						},
						'permission_callback' => $manage,
					),
				)
			);
		}
	}

	public function listQuotes( WP_REST_Request $request ): WP_REST_Response {
		$rows  = $this->plugin->quotes()->listQuotes(
			'' !== (string) $request->get_param( 'status' ) ? (string) $request->get_param( 'status' ) : null,
			(int) $request->get_param( 'page' ),
			(int) $request->get_param( 'per_page' )
		);

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = $this->serializeQuote( $row );
		}

		return new WP_REST_Response( array( 'items' => $items ), 200 );
	}

	public function getQuote( WP_REST_Request $request ) {
		$quoteId = (int) $request->get_param( 'id' );
		$quote   = $this->plugin->quotes()->get( $quoteId );

		if ( null === $quote ) {
			return new WP_Error( 'tss_not_found', __( 'Quote not found.', 'tradesheet-studio' ), array( 'status' => 404 ) );
		}

		$lines  = array();
		$totals = '0.0000';

		foreach ( $this->plugin->quotes()->lines( $quoteId ) as $line ) {
			$serialized = array(
				'id'               => (int) $line['id'],
				'packet_line_id'   => (int) $line['packet_line_id'],
				'row_id'           => 'L' . (string) $line['packet_line_id'],
				'product_id'       => (int) $line['product_id'],
				'variation_id'     => (int) $line['variation_id'],
				'sku'              => (string) $line['sku'],
				'name'             => (string) $line['name'],
				'variation_summary' => (string) ( $line['variation_summary'] ?? '' ),
				'qty'              => (int) $line['qty'],
				'unit_price'       => (string) $line['unit_price'],
				'line_total'       => Money::mul( (string) $line['unit_price'], (int) $line['qty'] ),
				'price_source'     => (string) $line['price_source'],
				'merchant_note'    => $line['merchant_note'],
			);

			$totals = Money::add( $totals, (string) $serialized['line_total'] );
			$lines[] = $serialized;
		}

		// Changes vs the parent revision (for revision display).
		$changes = array();
		if ( ! empty( $quote['parent_quote_id'] ) ) {
			$changes = $this->changesVsParent( (int) $quote['parent_quote_id'], $lines );
		}

		$packet  = $this->plugin->packets()->get( (int) $quote['packet_id'] );
		$version = $this->plugin->packets()->version( (int) $quote['version_id'] );

		$orderLink = null;
		if ( ! empty( $quote['converted_order_id'] ) ) {
			$orderLink = array( 'order_id' => (int) $quote['converted_order_id'] );
		}

		return new WP_REST_Response(
			array(
				'quote'    => $this->serializeQuote( $quote ),
				'lines'    => $lines,
				'total'    => $totals,
				'report'   => json_decode( (string) ( $quote['discrepancy_report'] ?? '' ), true ) ?: array(),
				'changes'  => $changes,
				'packet'   => null === $packet ? null : array(
					'id'     => (int) $packet['id'],
					'ref'    => (string) $packet['ref'],
					'title'  => (string) $packet['title'],
					'status' => (string) $packet['status'],
				),
				'version'  => null === $version ? null : array(
					'id'         => (int) $version['id'],
					'version_no' => (int) $version['version_no'],
					'checksum'   => (string) $version['content_checksum'],
				),
				'order'    => $orderLink,
			),
			200
		);
	}

	public function action( WP_REST_Request $request, string $action ) {
		try {
			$quoteId = (int) $request->get_param( 'id' );
			$actorId = get_current_user_id();

			switch ( $action ) {
				case 'review':
					$quote = $this->plugin->quotes()->setStatus( $quoteId, 'in_review', $actorId );
					break;

				case 'approve':
					$quote = $this->plugin->quotes()->approve( $quoteId, (string) ( $request->get_param( 'notes' ) ?? '' ), $actorId );
					break;

				case 'reject':
					$quote = $this->plugin->quotes()->reject( $quoteId, (string) ( $request->get_param( 'notes' ) ?? '' ), $actorId );
					break;

				case 'revise':
					$changes = $request->get_param( 'changes' );
					$changes = is_array( $changes ) ? $changes : array();

					$quote = $this->plugin->quoteRevisions()->revise(
						$quoteId,
						$changes,
						(string) ( $request->get_param( 'notes' ) ?? '' ),
						$actorId
					);
					break;

				case 'convert':
					$result = $this->plugin->orders()->convert( $quoteId, $actorId );

					if ( ! empty( $result['pending'] ) ) {
						return new WP_REST_Response( array( 'pending' => true, 'message' => (string) $result['message'] ), 202 );
					}

					return new WP_REST_Response( $result, 201 );

				default:
					return new WP_Error( 'tss_error', __( 'Unknown action.', 'tradesheet-studio' ), array( 'status' => 400 ) );
			}

			return new WP_REST_Response( array( 'quote' => $this->serializeQuote( $quote ) ), 200 );
		} catch ( ValidationException $e ) {
			return new WP_Error( 'tss_validation', $e->getMessage(), array( 'status' => 400 ) );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'tss_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * @param list<array<string,mixed>> $revisionLines
	 * @return list<array<string,mixed>>
	 */
	private function changesVsParent( int $parentQuoteId, array $revisionLines ): array {
		$parentLines = array();
		foreach ( $this->plugin->quotes()->lines( $parentQuoteId ) as $line ) {
			$parentLines[ (int) $line['packet_line_id'] ] = $line;
		}

		$changes = array();
		$seen    = array();

		foreach ( $revisionLines as $line ) {
			$packetLineId = (int) $line['packet_line_id'];
			$seen[ $packetLineId ] = true;
			$parent = $parentLines[ $packetLineId ] ?? null;

			if ( null === $parent ) {
				$changes[] = array(
					'row_id' => (string) $line['row_id'],
					'name'   => (string) $line['name'],
					'change' => 'added',
				);
				continue;
			}

			if ( Money::cmp( (string) $parent['unit_price'], (string) $line['unit_price'] ) !== 0 ) {
				$changes[] = array(
					'row_id'      => (string) $line['row_id'],
					'name'        => (string) $line['name'],
					'change'      => 'price',
					'old_price'   => (string) $parent['unit_price'],
					'new_price'   => (string) $line['unit_price'],
				);
			}

			if ( (int) $parent['qty'] !== (int) $line['qty'] ) {
				$changes[] = array(
					'row_id'   => (string) $line['row_id'],
					'name'     => (string) $line['name'],
					'change'   => 'qty',
					'old_qty'  => (int) $parent['qty'],
					'new_qty'  => (int) $line['qty'],
				);
			}
		}

		foreach ( $parentLines as $packetLineId => $parent ) {
			if ( ! isset( $seen[ $packetLineId ] ) ) {
				$changes[] = array(
					'row_id' => 'L' . (string) $parent['packet_line_id'],
					'name'   => (string) $parent['name'],
					'change' => 'removed',
				);
			}
		}

		return $changes;
	}

	/**
	 * @param array<string,mixed> $quote
	 * @return array<string,mixed>
	 */
	private function serializeQuote( array $quote ): array {
		$report = json_decode( (string) ( $quote['discrepancy_report'] ?? '' ), true );

		$customerName = '';
		$user         = get_userdata( (int) $quote['customer_id'] );
		if ( false !== $user ) {
			$customerName = (string) $user->display_name;
		}

		$packet = $this->plugin->packets()->get( (int) $quote['packet_id'] );

		return array(
			'id'                 => (int) $quote['id'],
			'ref'                => (string) $quote['ref'],
			'packet_id'          => (int) $quote['packet_id'],
			'packet_ref'         => null === $packet ? '' : (string) $packet['ref'],
			'packet_title'       => null === $packet ? '' : (string) $packet['title'],
			'version_id'         => (int) $quote['version_id'],
			'revision_no'        => (int) $quote['revision_no'],
			'parent_quote_id'    => $quote['parent_quote_id'] ? (int) $quote['parent_quote_id'] : null,
			'customer_id'        => (int) $quote['customer_id'],
			'customer_name'      => $customerName,
			'status'             => (string) $quote['status'],
			'requested_at'       => $quote['requested_at'],
			'reviewed_at'        => $quote['reviewed_at'],
			'approved_at'        => $quote['approved_at'],
			'accepted_at'        => $quote['accepted_at'],
			'expires_at'         => $quote['expires_at'],
			'converted_order_id' => $quote['converted_order_id'] ? (int) $quote['converted_order_id'] : null,
			'notes_buyer'        => (string) ( $quote['notes_buyer'] ?? '' ),
			'notes_merchant'     => (string) ( $quote['notes_merchant'] ?? '' ),
			'blocking_count'     => is_array( $report ) ? (int) ( $report['blocking_count'] ?? 0 ) : 0,
			'warning_count'      => is_array( $report ) ? (int) ( $report['warning_count'] ?? 0 ) : 0,
		);
	}
}
