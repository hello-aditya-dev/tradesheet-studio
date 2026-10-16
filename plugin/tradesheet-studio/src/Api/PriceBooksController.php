<?php
/**
 * Price book endpoints.
 *
 * @package TradeSheetStudio\Api
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Api;

use TradeSheetStudio\Core\Plugin;
use TradeSheetStudio\Pricing\PriceRule;
use TradeSheetStudio\Security\Capabilities;
use TradeSheetStudio\Support\ValidationException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class PriceBooksController {

	public function __construct( private Plugin $plugin ) {
	}

	public function register(): void {
		$manage = array( Capabilities::class, 'restCanManage' );

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/pricebooks',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'listBooks' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'createBook' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/pricebooks/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'getBook' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'updateBook' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'deleteBook' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/pricebooks/(?P<id>\d+)/duplicate',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'duplicateBook' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/pricebooks/(?P<id>\d+)/rules',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'listRules' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'upsertRule' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/pricebooks/(?P<id>\d+)/rules/(?P<ruleId>\d+)',
			array(
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'deleteRule' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/pricebooks/(?P<id>\d+)/preview',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'preview' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/pricebooks/(?P<id>\d+)/customers',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'listCustomers' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'assignCustomer' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/pricebooks/(?P<id>\d+)/customers/(?P<customerId>\d+)',
			array(
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'unassignCustomer' ),
					'permission_callback' => $manage,
				),
			)
		);
	}

	public function listBooks(): WP_REST_Response {
		$books = array();

		foreach ( $this->plugin->priceBooks()->listBooks() as $book ) {
			$book['rule_count']     = $this->plugin->priceBooks()->ruleCount( (int) $book['id'] );
			$book['customer_count'] = count( $this->plugin->priceBooks()->assignments( (int) $book['id'] ) );
			$books[]                = $this->serializeBook( $book );
		}

		return new WP_REST_Response( array( 'items' => $books ), 200 );
	}

	public function createBook( WP_REST_Request $request ) {
		try {
			$name = sanitize_text_field( (string) $request->get_param( 'name' ) );

			if ( '' === $name ) {
				throw new ValidationException( __( 'A price book requires a name.', 'tradesheet-studio' ) );
			}

			$id = $this->plugin->priceBooks()->create(
				$name,
				(string) $request->get_param( 'description' ),
				(string) ( $request->get_param( 'status' ) ?? 'active' ),
				$this->normalizeCurrency( (string) $request->get_param( 'currency' ) ),
				(int) ( $request->get_param( 'priority' ) ?? 0 ),
				$this->normalizeDate( $request->get_param( 'valid_from' ) ),
				$this->normalizeDate( $request->get_param( 'valid_to' ) )
			);

			return new WP_REST_Response( array( 'id' => $id ), 201 );
		} catch ( ValidationException $e ) {
			return new WP_Error( 'tss_validation', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	public function getBook( WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$book = $this->plugin->priceBooks()->get( $id );

		if ( null === $book ) {
			return new WP_Error( 'tss_not_found', __( 'Price book not found.', 'tradesheet-studio' ), array( 'status' => 404 ) );
		}

		$book['rule_count']     = $this->plugin->priceBooks()->ruleCount( $id );
		$book['customer_count'] = count( $this->plugin->priceBooks()->assignments( $id ) );

		return new WP_REST_Response(
			array(
				'book'      => $this->serializeBook( $book ),
				'rules'     => array_map( array( $this, 'serializeRule' ), $this->plugin->priceBooks()->rulesForBook( $id ) ),
				'customers' => $this->serializeCustomers( $this->plugin->priceBooks()->assignments( $id ) ),
			),
			200
		);
	}

	public function updateBook( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( null === $this->plugin->priceBooks()->get( $id ) ) {
			return new WP_Error( 'tss_not_found', __( 'Price book not found.', 'tradesheet-studio' ), array( 'status' => 404 ) );
		}

		$fields = array(
			'name'        => $request->get_param( 'name' ),
			'description' => $request->get_param( 'description' ),
			'status'      => $request->get_param( 'status' ),
			'currency'    => $this->normalizeCurrency( (string) $request->get_param( 'currency' ) ),
			'priority'    => $request->get_param( 'priority' ),
			'valid_from'  => $this->normalizeDate( $request->get_param( 'valid_from' ) ),
			'valid_to'    => $this->normalizeDate( $request->get_param( 'valid_to' ) ),
		);

		$this->plugin->priceBooks()->update( $id, array_filter( $fields, static function ( $value ) { return null !== $value; } ) );

		return new WP_REST_Response( array( 'updated' => true ), 200 );
	}

	public function deleteBook( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( null === $this->plugin->priceBooks()->get( $id ) ) {
			return new WP_Error( 'tss_not_found', __( 'Price book not found.', 'tradesheet-studio' ), array( 'status' => 404 ) );
		}

		$this->plugin->priceBooks()->delete( $id );
		$this->plugin->events()->log( 'pricebook', $id, 'deleted', get_current_user_id(), array() );

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	public function duplicateBook( WP_REST_Request $request ) {
		$id    = (int) $request->get_param( 'id' );
		$newId = $this->plugin->priceBooks()->duplicate( $id );

		if ( 0 === $newId ) {
			return new WP_Error( 'tss_not_found', __( 'Price book not found.', 'tradesheet-studio' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( array( 'id' => $newId ), 201 );
	}

	public function listRules( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		return new WP_REST_Response(
			array( 'items' => array_map( array( $this, 'serializeRule' ), $this->plugin->priceBooks()->rulesForBook( $id ) ) ),
			200
		);
	}

	public function upsertRule( WP_REST_Request $request ) {
		try {
			$bookId = (int) $request->get_param( 'id' );

			if ( null === $this->plugin->priceBooks()->get( $bookId ) ) {
				return new WP_Error( 'tss_not_found', __( 'Price book not found.', 'tradesheet-studio' ), array( 'status' => 404 ) );
			}

			$data                = $request->get_json_params();
			$data                = is_array( $data ) ? $data : $request->get_params();
			$data['pricebook_id'] = $bookId;

			$breaks = $data['qty_breaks'] ?? array();
			if ( is_string( $breaks ) ) {
				$decoded = json_decode( $breaks, true );
				$breaks  = is_array( $decoded ) ? $decoded : array();
			}
			$data['qty_breaks'] = $breaks;

			$rule = PriceRule::fromArray( $data );

			$ruleId = $this->plugin->priceBooks()->upsertRule( $rule );
			$this->plugin->events()->log( 'pricebook', $bookId, 'rule_saved', get_current_user_id(), array( 'rule_id' => $ruleId ) );

			return new WP_REST_Response( array( 'id' => $ruleId ), 201 );
		} catch ( ValidationException $e ) {
			return new WP_Error( 'tss_validation', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	public function deleteRule( WP_REST_Request $request ) {
		$deleted = $this->plugin->priceBooks()->deleteRule( (int) $request->get_param( 'ruleId' ) );

		return new WP_REST_Response( array( 'deleted' => $deleted ), 200 );
	}

	public function preview( WP_REST_Request $request ) {
		$resolution = $this->plugin->pricing()->resolveForCustomer(
			(int) $request->get_param( 'customer_id' ),
			(int) $request->get_param( 'product_id' ),
			(int) ( $request->get_param( 'variation_id' ) ?? 0 ),
			max( 1, (int) ( $request->get_param( 'qty' ) ?? 1 ) )
		);

		return new WP_REST_Response( $resolution->toArray(), 200 );
	}

	public function listCustomers( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		return new WP_REST_Response(
			array( 'items' => $this->serializeCustomers( $this->plugin->priceBooks()->assignments( $id ) ) ),
			200
		);
	}

	public function assignCustomer( WP_REST_Request $request ) {
		$bookId     = (int) $request->get_param( 'id' );
		$customerId = (int) $request->get_param( 'customer_id' );

		if ( false === get_userdata( $customerId ) ) {
			return new WP_Error( 'tss_validation', __( 'Customer not found.', 'tradesheet-studio' ), array( 'status' => 400 ) );
		}

		if ( null === $this->plugin->priceBooks()->get( $bookId ) ) {
			return new WP_Error( 'tss_not_found', __( 'Price book not found.', 'tradesheet-studio' ), array( 'status' => 404 ) );
		}

		$this->plugin->priceBooks()->assignCustomer( $bookId, $customerId );
		$this->plugin->events()->log( 'pricebook', $bookId, 'customer_assigned', get_current_user_id(), array( 'customer_id' => $customerId ) );

		return new WP_REST_Response( array( 'assigned' => true ), 200 );
	}

	public function unassignCustomer( WP_REST_Request $request ) {
		$customerId = (int) $request->get_param( 'customerId' );
		$this->plugin->priceBooks()->unassignCustomer( $customerId );

		return new WP_REST_Response( array( 'unassigned' => true ), 200 );
	}

	/**
	 * @param array<string,mixed> $book
	 * @return array<string,mixed>
	 */
	private function serializeBook( array $book ): array {
		$book['valid_from'] = $book['valid_from'] ?: null;
		$book['valid_to']   = $book['valid_to'] ?: null;

		return $book;
	}

	/**
	 * @param array<string,mixed> $rule
	 * @return array<string,mixed>
	 */
	private function serializeRule( array $rule ): array {
		$rule['qty_breaks'] = json_decode( (string) ( $rule['qty_breaks'] ?? '' ), true ) ?: array();

		return $rule;
	}

	/**
	 * @param list<int> $customerIds
	 * @return list<array<string,mixed>>
	 */
	private function serializeCustomers( array $customerIds ): array {
		$items = array();

		foreach ( $customerIds as $customerId ) {
			$user = get_userdata( (int) $customerId );
			if ( false === $user ) {
				continue;
			}

			$items[] = array(
				'id'              => (int) $user->ID,
				'display_name'    => (string) $user->display_name,
				'email'           => (string) $user->user_email,
				'billing_company' => (string) get_user_meta( (int) $user->ID, 'billing_company', true ),
			);
		}

		return $items;
	}

	private function normalizeCurrency( string $currency ): string {
		return strtoupper( mb_substr( preg_replace( '/[^A-Za-z]/', '', $currency ) ?? '', 0, 3 ) );
	}

	private function normalizeDate( mixed $value ): ?string {
		if ( null === $value || '' === $value ) {
			return null;
		}

		$value = trim( (string) $value );

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return $value . ' 00:00:00';
		}

		if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ) {
			return $value;
		}

		return null;
	}
}
