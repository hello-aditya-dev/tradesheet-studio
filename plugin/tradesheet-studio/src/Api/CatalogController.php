<?php
/**
 * Catalog endpoints: admin product/customer search for the composer.
 *
 * @package TradeSheetStudio\Api
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Api;

use TradeSheetStudio\Core\Plugin;
use TradeSheetStudio\Security\Capabilities;
use WP_REST_Request;
use WP_REST_Response;

final class CatalogController {

	public function __construct( private Plugin $plugin ) {
	}

	public function register(): void {
		register_rest_route(
			Routes::NAMESPACE_V1,
			'/products',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'products' ),
					'permission_callback' => array( Capabilities::class, 'restCanManage' ),
					'args'                => array(
						'search'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
						'page'     => array( 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ),
						'per_page' => array( 'type' => 'integer', 'default' => 20, 'sanitize_callback' => 'absint' ),
						'category' => array( 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint' ),
					),
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/products/(?P<id>\d+)/variations',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'variations' ),
					'permission_callback' => array( Capabilities::class, 'restCanManage' ),
					'args'                => array(
						'id' => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
					),
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/customers',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'customers' ),
					'permission_callback' => array( Capabilities::class, 'restCanManage' ),
					'args'                => array(
						'search'   => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
						'page'     => array( 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ),
						'per_page' => array( 'type' => 'integer', 'default' => 25, 'sanitize_callback' => 'absint' ),
					),
				),
			)
		);
	}

	public function products( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->plugin->products()->search(
			(string) $request->get_param( 'search' ),
			(int) $request->get_param( 'page' ),
			(int) $request->get_param( 'per_page' ),
			(int) $request->get_param( 'category' )
		);

		$currency = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol() ) : '';

		return new WP_REST_Response(
			array(
				'items'           => $result['items'],
				'total'           => (int) $result['total'],
				'currency_symbol' => $currency,
			),
			200
		);
	}

	public function variations( WP_REST_Request $request ): WP_REST_Response {
		$productId = (int) $request->get_param( 'id' );

		return new WP_REST_Response(
			array( 'items' => $this->plugin->products()->variations( $productId ) ),
			200
		);
	}

	public function customers( WP_REST_Request $request ): WP_REST_Response {
		$search  = (string) $request->get_param( 'search' );
		$page    = max( 1, (int) $request->get_param( 'page' ) );
		$perPage = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );

		$args = array(
			'role'   => 'customer',
			'number' => $perPage,
			'paged'  => $page,
			'fields' => array( 'ID', 'display_name', 'user_email' ),
		);

		if ( '' !== $search ) {
			$args['search'] = '*' . $search . '*';
		}

		$query  = new \WP_User_Query( $args );
		$users  = (array) $query->get_results();
		$total  = (int) $query->get_total();

		$items = array();
		foreach ( $users as $user ) {
			$items[] = array(
				'id'              => (int) $user->ID,
				'display_name'    => (string) $user->display_name,
				'email'           => (string) $user->user_email,
				'billing_company' => (string) get_user_meta( (int) $user->ID, 'billing_company', true ),
				'pricebook_id'    => $this->plugin->priceBooks()->assignedBookId( (int) $user->ID ),
			);
		}

		return new WP_REST_Response(
			array(
				'items' => $items,
				'total' => $total,
			),
			200
		);
	}
}
