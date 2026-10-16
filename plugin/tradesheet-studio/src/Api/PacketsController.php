<?php
/**
 * Packet endpoints — the merchant DealPacket management surface.
 *
 * @package TradeSheetStudio\Api
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Api;

use TradeSheetStudio\Core\Plugin;
use TradeSheetStudio\Documents\PdfRenderer;
use TradeSheetStudio\Packets\PacketStateMachine;
use TradeSheetStudio\Support\Money;
use TradeSheetStudio\Support\Utils;
use TradeSheetStudio\Support\ValidationException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class PacketsController {

	public function __construct( private Plugin $plugin ) {
	}

	public function register(): void {
		$manage = array( \TradeSheetStudio\Security\Capabilities::class, 'restCanManage' );

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/packets',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'listPackets' ),
					'permission_callback' => $manage,
					'args'                => array(
						'status'      => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_key' ),
						'customer_id' => array( 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint' ),
						'search'      => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
						'page'        => array( 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ),
						'per_page'    => array( 'type' => 'integer', 'default' => 20, 'sanitize_callback' => 'absint' ),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'createPacket' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/packets/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'getPacket' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'updatePacket' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'deletePacket' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/packets/(?P<id>\d+)/lines',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'addLine' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/packets/(?P<id>\d+)/lines/(?P<lineId>\d+)',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'updateLine' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'deleteLine' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/packets/(?P<id>\d+)/lines-order',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'reorderLines' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/packets/(?P<id>\d+)/preflight',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'preflight' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/packets/(?P<id>\d+)/publish',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'publish' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/packets/(?P<id>\d+)/revise',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'revise' ),
					'permission_callback' => $manage,
				),
			)
		);

		foreach ( array( 'revoke', 'expire', 'archive', 'cancel' ) as $action ) {
			register_rest_route(
				Routes::NAMESPACE_V1,
				'/packets/(?P<id>\d+)/' . $action,
				array(
					array(
						'methods'             => 'POST',
						'callback'            => function ( WP_REST_Request $request ) use ( $action ) {
							return $this->transition( $request, $action );
						},
						'permission_callback' => $manage,
					),
				)
			);
		}

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/packets/(?P<id>\d+)/share',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'share' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/packets/(?P<id>\d+)/documents',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'regenerateDocuments' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/packets/(?P<id>\d+)/download',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'download' ),
					'permission_callback' => $manage,
					'args'                => array(
						'type'     => array( 'type' => 'string', 'default' => 'pdf', 'sanitize_callback' => 'sanitize_key' ),
						'template' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_key' ),
					),
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE_V1,
			'/packets/(?P<id>\d+)/events',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'events' ),
					'permission_callback' => $manage,
				),
			)
		);
	}

	public function listPackets( WP_REST_Request $request ): WP_REST_Response {
		$rows = $this->plugin->packets()->listPackets(
			'' !== (string) $request->get_param( 'status' ) ? (string) $request->get_param( 'status' ) : null,
			(int) $request->get_param( 'customer_id' ) > 0 ? (int) $request->get_param( 'customer_id' ) : null,
			(string) $request->get_param( 'search' ),
			(int) $request->get_param( 'page' ),
			(int) $request->get_param( 'per_page' )
		);

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = $this->serializePacketSummary( $row );
		}

		return new WP_REST_Response( array( 'items' => $items ), 200 );
	}

	public function createPacket( WP_REST_Request $request ) {
		try {
			$customerId = (int) $request->get_param( 'customer_id' );

			if ( $customerId < 1 || false === get_userdata( $customerId ) ) {
				throw new ValidationException( __( 'Select an existing WooCommerce customer as the buyer.', 'tradesheet-studio' ) );
			}

			$title = sanitize_text_field( (string) $request->get_param( 'title' ) );
			if ( '' === $title ) {
				throw new ValidationException( __( 'The packet requires a title.', 'tradesheet-studio' ) );
			}

			$terms = $request->get_param( 'terms' );
			$terms = is_array( $terms ) ? $this->sanitizeTerms( $terms ) : array();

			$pricebookId = (int) ( $request->get_param( 'pricebook_id' ) ?? 0 );
			if ( $pricebookId > 0 && null === $this->plugin->priceBooks()->get( $pricebookId ) ) {
				throw new ValidationException( __( 'The selected price book does not exist.', 'tradesheet-studio' ) );
			}

			$id = $this->plugin->packets()->create(
				$customerId,
				$title,
				$pricebookId > 0 ? $pricebookId : null,
				$this->normalizeCurrency( (string) $request->get_param( 'currency' ) ),
				$this->normalizeDate( $request->get_param( 'valid_until' ) ),
				$terms,
				get_current_user_id()
			);

			$this->plugin->events()->log( 'packet', $id, 'created', get_current_user_id(), array( 'customer_id' => $customerId ) );

			return new WP_REST_Response( array( 'id' => $id, 'ref' => $this->plugin->packets()->refFor( $id ) ), 201 );
		} catch ( ValidationException $e ) {
			return new WP_Error( 'tss_validation', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	public function getPacket( WP_REST_Request $request ) {
		$packet = $this->plugin->packets()->get( (int) $request->get_param( 'id' ) );

		if ( null === $packet ) {
			return new WP_Error( 'tss_not_found', __( 'Packet not found.', 'tradesheet-studio' ), array( 'status' => 404 ) );
		}

		$packetId = (int) $packet['id'];

		$versions   = array();
		$documents = array();

		foreach ( $this->plugin->packets()->versions( $packetId ) as $version ) {
			$versions[] = array(
				'id'              => (int) $version['id'],
				'version_no'      => (int) $version['version_no'],
				'status'          => (string) $version['status'],
				'checksum'        => (string) $version['content_checksum'],
				'issued_at'       => (string) $version['issued_at'],
				'expires_at'      => $version['expires_at'],
				'issued_by'       => $version['issued_by'],
			);
		}

		$currentVersion = $this->plugin->packets()->currentVersion( $packetId );

		if ( null !== $currentVersion ) {
			foreach ( $this->plugin->documents()->documentsForVersion( (int) $currentVersion['id'] ) as $doc ) {
				$documents[] = array(
					'id'           => (int) $doc['id'],
					'type'         => (string) $doc['type'],
					'template'     => (string) $doc['template'],
					'size_bytes'   => (int) $doc['size_bytes'],
					'generated_at' => (string) $doc['generated_at'],
				);
			}
		}

		$lines = array();
		$sourceLines = ( PacketStateMachine::DRAFT === (string) $packet['status'] )
			? $this->plugin->packets()->draftLines( $packetId )
			: $this->plugin->packets()->versionLines( (int) ( $currentVersion['id'] ?? 0 ) );

		foreach ( $sourceLines as $line ) {
			$lines[] = $this->serializeLine( $line );
		}

		$quotes = array();
		foreach ( $this->plugin->quotes()->forPacket( $packetId ) as $quote ) {
			$quotes[] = $this->serializeQuoteSummary( $quote );
		}

		return new WP_REST_Response(
			array(
				'packet'          => $this->serializePacketSummary( $packet ),
				'lines'           => $lines,
				'versions'        => $versions,
				'current_version' => null === $currentVersion ? null : array(
					'id'         => (int) $currentVersion['id'],
					'version_no' => (int) $currentVersion['version_no'],
					'status'     => (string) $currentVersion['status'],
					'checksum'   => (string) $currentVersion['content_checksum'],
					'expires_at' => $currentVersion['expires_at'],
				),
				'documents'       => $documents,
				'quotes'          => $quotes,
				'terms'           => json_decode( (string) ( $packet['terms'] ?? '{}' ), true ) ?: array(),
			),
			200
		);
	}

	public function updatePacket( WP_REST_Request $request ) {
		try {
			$id     = (int) $request->get_param( 'id' );
			$packet = $this->plugin->packets()->get( $id );

			if ( null === $packet ) {
				return new WP_Error( 'tss_not_found', __( 'Packet not found.', 'tradesheet-studio' ), array( 'status' => 404 ) );
			}

			if ( PacketStateMachine::DRAFT !== (string) $packet['status'] ) {
				throw new ValidationException( __( 'Issued packets are immutable. Use Revise to create a new version.', 'tradesheet-studio' ) );
			}

			$fields = array();

			if ( null !== $request->get_param( 'title' ) ) {
				$fields['title'] = sanitize_text_field( (string) $request->get_param( 'title' ) );
			}
			if ( null !== $request->get_param( 'valid_until' ) ) {
				$fields['valid_until'] = $this->normalizeDate( $request->get_param( 'valid_until' ) );
			}
			if ( null !== $request->get_param( 'pricebook_id' ) ) {
				$bookId = (int) $request->get_param( 'pricebook_id' );
				if ( $bookId > 0 && null === $this->plugin->priceBooks()->get( $bookId ) ) {
					throw new ValidationException( __( 'The selected price book does not exist.', 'tradesheet-studio' ) );
				}
				$fields['pricebook_id'] = $bookId;
			}
			if ( null !== $request->get_param( 'currency' ) ) {
				$fields['currency'] = $this->normalizeCurrency( (string) $request->get_param( 'currency' ) );
			}
			if ( null !== $request->get_param( 'terms' ) && is_array( $request->get_param( 'terms' ) ) ) {
				$fields['terms'] = $this->sanitizeTerms( $request->get_param( 'terms' ) );
			}

			$this->plugin->packets()->update( $id, $fields );

			return new WP_REST_Response( array( 'updated' => true ), 200 );
		} catch ( ValidationException $e ) {
			return new WP_Error( 'tss_validation', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	public function deletePacket( WP_REST_Request $request ) {
		$deleted = $this->plugin->packets()->deleteDraft( (int) $request->get_param( 'id' ) );

		if ( ! $deleted ) {
			return new WP_Error( 'tss_validation', __( 'Only draft packets can be deleted. Issued packets must be revoked or archived.', 'tradesheet-studio' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	public function addLine( WP_REST_Request $request ) {
		try {
			$id     = (int) $request->get_param( 'id' );
			$packet = $this->plugin->packets()->get( $id );

			if ( null === $packet ) {
				return new WP_Error( 'tss_not_found', __( 'Packet not found.', 'tradesheet-studio' ), array( 'status' => 404 ) );
			}

			if ( PacketStateMachine::DRAFT !== (string) $packet['status'] ) {
				throw new ValidationException( __( 'Lines can only be changed while the packet is a draft.', 'tradesheet-studio' ) );
			}

			$productId   = (int) $request->get_param( 'product_id' );
			$variationId = (int) ( $request->get_param( 'variation_id' ) ?? 0 );

			$product = $this->plugin->products()->get( $variationId > 0 ? $variationId : $productId );
			if ( null === $product ) {
				throw new ValidationException( __( 'The product does not exist in the store.', 'tradesheet-studio' ) );
			}

			if ( ! $this->plugin->products()->supportedType( $product ) ) {
				throw new ValidationException(
					sprintf(
						/* translators: %s: product type. */
						__( 'Product type "%s" is not supported in version 1 (select a simple product or a specific variation).', 'tradesheet-studio' ),
						(string) $product->get_type()
					)
				);
			}

			$qtyMin  = max( 1, (int) ( $request->get_param( 'qty_min' ) ?? 1 ) );
			$qtyStep = max( 1, (int) ( $request->get_param( 'qty_step' ) ?? 1 ) );

			$overrideRaw = trim( (string) ( $request->get_param( 'override_price' ) ?? '' ) );

			if ( '' !== $overrideRaw ) {
				$override = Money::round( Utils::sanitizeFloatish( $overrideRaw ), 4 );
				if ( ! Money::isValid( $override ) || Money::cmp( $override, '0.0000' ) < 0 ) {
					throw new ValidationException( __( 'The price override must be a non-negative amount.', 'tradesheet-studio' ) );
				}
				$unitPrice   = $override;
				$priceSource = 'packet_override';
				$breaks      = array();
				$resolution  = null;
			} else {
				$resolution  = $this->plugin->pricing()->resolveForCustomer( (int) $packet['customer_id'], $productId, $variationId, $qtyMin );
				$unitPrice   = $resolution->unitPrice;
				$priceSource = 'resolved';
				$breaks      = $resolution->qtyBreaks;
			}

			$lineId = $this->plugin->packets()->addDraftLine(
				$id,
				$productId,
				$variationId,
				$unitPrice,
				$priceSource,
				$qtyMin,
				$qtyStep,
				$breaks
			);

			$line = null;
			foreach ( $this->plugin->packets()->draftLines( $id ) as $draftLine ) {
				if ( (int) $draftLine['id'] === $lineId ) {
					$line = $draftLine;
					break;
				}
			}

			return new WP_REST_Response(
				array(
					'line'       => null === $line ? null : $this->serializeLine( $line ),
					'resolution' => null === $resolution ? null : $resolution->toArray(),
				),
				201
			);
		} catch ( ValidationException $e ) {
			return new WP_Error( 'tss_validation', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	public function updateLine( WP_REST_Request $request ) {
		try {
			$id     = (int) $request->get_param( 'id' );
			$packet = $this->plugin->packets()->get( $id );

			if ( null === $packet || PacketStateMachine::DRAFT !== (string) $packet['status'] ) {
				throw new ValidationException( __( 'Lines can only be changed while the packet is a draft.', 'tradesheet-studio' ) );
			}

			$fields = array();

			if ( null !== $request->get_param( 'qty_min' ) ) {
				$fields['qty_min'] = (int) $request->get_param( 'qty_min' );
			}
			if ( null !== $request->get_param( 'qty_step' ) ) {
				$fields['qty_step'] = (int) $request->get_param( 'qty_step' );
			}
			if ( null !== $request->get_param( 'override_price' ) ) {
				$override = trim( (string) $request->get_param( 'override_price' ) );
				if ( '' !== $override ) {
					$normalized = Money::round( Utils::sanitizeFloatish( $override ), 4 );
					if ( ! Money::isValid( $normalized ) || Money::cmp( $normalized, '0.0000' ) < 0 ) {
						throw new ValidationException( __( 'The price override must be a non-negative amount.', 'tradesheet-studio' ) );
					}
					$fields['unit_price'] = $normalized;
				}
			}
			if ( null !== $request->get_param( 'sort_order' ) ) {
				$fields['sort_order'] = (int) $request->get_param( 'sort_order' );
			}

			$updated = $this->plugin->packets()->updateDraftLine( (int) $request->get_param( 'lineId' ), $fields );

			return new WP_REST_Response( array( 'updated' => $updated ), 200 );
		} catch ( ValidationException $e ) {
			return new WP_Error( 'tss_validation', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	public function deleteLine( WP_REST_Request $request ) {
		$deleted = $this->plugin->packets()->removeDraftLine( (int) $request->get_param( 'lineId' ) );

		return new WP_REST_Response( array( 'deleted' => $deleted ), 200 );
	}

	public function reorderLines( WP_REST_Request $request ) {
		$order   = $request->get_param( 'order' );
		$order   = is_array( $order ) ? array_map( 'absint', $order ) : array();
		$packetId = (int) $request->get_param( 'id' );

		$this->plugin->packets()->reorderDraftLines( $packetId, $order );

		return new WP_REST_Response( array( 'reordered' => true ), 200 );
	}

	public function preflight( WP_REST_Request $request ) {
		$findings = $this->plugin->snapshots()->preflight( (int) $request->get_param( 'id' ) );

		return new WP_REST_Response( $findings, 200 );
	}

	public function publish( WP_REST_Request $request ) {
		try {
			$packetId = (int) $request->get_param( 'id' );
			$template = (string) ( $request->get_param( 'template' ) ?? '' );

			if ( '' !== $template && ! in_array( $template, PdfRenderer::TEMPLATES, true ) ) {
				throw new ValidationException( __( 'Unknown document template.', 'tradesheet-studio' ) );
			}

			$result = $this->plugin->snapshots()->publish( $packetId, get_current_user_id() );

			$documents = array();
			try {
				$generation = $this->plugin->documents()->generateForVersion( (int) $result['version_id'], get_current_user_id(), '' !== $template ? $template : null );

				foreach ( $this->plugin->documents()->documentsForVersion( (int) $result['version_id'] ) as $doc ) {
					$documents[] = array(
						'id'           => (int) $doc['id'],
						'type'         => (string) $doc['type'],
						'template'     => (string) $doc['template'],
						'size_bytes'   => (int) $doc['size_bytes'],
						'generated_at' => (string) $doc['generated_at'],
					);
				}

				$result['template'] = $generation['template'];
			} catch ( \Throwable $e ) {
				$result['document_error'] = $e->getMessage();
			}

			$result['documents'] = $documents;

			return new WP_REST_Response( $result, 201 );
		} catch ( ValidationException $e ) {
			return new WP_Error( 'tss_validation', $e->getMessage(), array( 'status' => 400 ) );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'tss_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	public function revise( WP_REST_Request $request ) {
		try {
			$revised = $this->plugin->snapshots()->revise( (int) $request->get_param( 'id' ), get_current_user_id() );

			return new WP_REST_Response( array( 'revised' => $revised ), 200 );
		} catch ( ValidationException $e ) {
			return new WP_Error( 'tss_validation', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	public function transition( WP_REST_Request $request, string $action ) {
		try {
			$id     = (int) $request->get_param( 'id' );
			$packet = $this->plugin->packets()->get( $id );

			if ( null === $packet ) {
				return new WP_Error( 'tss_not_found', __( 'Packet not found.', 'tradesheet-studio' ), array( 'status' => 404 ) );
			}

			$target = array(
				'revoke'  => PacketStateMachine::REVOKED,
				'expire'  => PacketStateMachine::EXPIRED,
				'archive' => PacketStateMachine::ARCHIVED,
				'cancel'  => PacketStateMachine::CANCELLED,
			)[ $action ];

			$this->plugin->packets()->setStatus( $id, $target, get_current_user_id() );

			if ( in_array( $action, array( 'revoke', 'expire' ), true ) ) {
				$this->plugin->buyerAccess()->revokeForPacket( $id );
			}

			return new WP_REST_Response( array( 'status' => $target ), 200 );
		} catch ( \TradeSheetStudio\Support\ValidationException $e ) {
			return new WP_Error( 'tss_validation', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	public function share( WP_REST_Request $request ) {
		$share = $this->plugin->buyerAccess()->shareUrl( (int) $request->get_param( 'id' ) );

		if ( null === $share ) {
			return new WP_Error( 'tss_not_found', __( 'Packet not found.', 'tradesheet-studio' ), array( 'status' => 404 ) );
		}

		$this->plugin->events()->log( 'packet', (int) $request->get_param( 'id' ), 'share_link_created', get_current_user_id(), array() );

		return new WP_REST_Response( $share, 200 );
	}

	public function regenerateDocuments( WP_REST_Request $request ) {
		try {
			$packetId = (int) $request->get_param( 'id' );
			$version  = $this->plugin->packets()->currentVersion( $packetId );

			if ( null === $version ) {
				return new WP_Error( 'tss_validation', __( 'The packet has no published version yet.', 'tradesheet-studio' ), array( 'status' => 400 ) );
			}

			$template = (string) ( $request->get_param( 'template' ) ?? '' );
			if ( '' !== $template && ! in_array( $template, PdfRenderer::TEMPLATES, true ) ) {
				return new WP_Error( 'tss_validation', __( 'Unknown document template.', 'tradesheet-studio' ), array( 'status' => 400 ) );
			}

			$this->plugin->documents()->generateForVersion( (int) $version['id'], get_current_user_id(), '' !== $template ? $template : null );

			$documents = array();
			foreach ( $this->plugin->documents()->documentsForVersion( (int) $version['id'] ) as $doc ) {
				$documents[] = array(
					'id'           => (int) $doc['id'],
					'type'         => (string) $doc['type'],
					'template'     => (string) $doc['template'],
					'size_bytes'   => (int) $doc['size_bytes'],
					'generated_at' => (string) $doc['generated_at'],
				);
			}

			return new WP_REST_Response( array( 'documents' => $documents ), 200 );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'tss_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Merchant download of packet documents (permission: manage).
	 */
	public function download( WP_REST_Request $request ) {
		$packetId = (int) $request->get_param( 'id' );
		$type     = sanitize_key( (string) $request->get_param( 'type' ) );
		$template = sanitize_key( (string) $request->get_param( 'template' ) );

		$version = $this->plugin->packets()->currentVersion( $packetId );

		if ( null === $version ) {
			return new WP_Error( 'tss_validation', __( 'The packet has no published version yet.', 'tradesheet-studio' ), array( 'status' => 400 ) );
		}

		foreach ( $this->plugin->documents()->documentsForVersion( (int) $version['id'] ) as $doc ) {
			if ( (string) $doc['type'] === $type && (string) $doc['template'] === $template ) {
				$this->plugin->documents()->stream( (int) $doc['id'] );
			}
		}

		return new WP_Error( 'tss_not_found', __( 'Document not found for this version; regenerate documents first.', 'tradesheet-studio' ), array( 'status' => 404 ) );
	}

	public function events( WP_REST_Request $request ) {
		$events = $this->plugin->events()->forObject( 'packet', (int) $request->get_param( 'id' ), 200 );

		return new WP_REST_Response( array( 'items' => $events ), 200 );
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	private function serializePacketSummary( array $row ): array {
		$customerName = '';
		$customerEmail = '';

		$user = get_userdata( (int) $row['customer_id'] );
		if ( false !== $user ) {
			$customerName  = (string) $user->display_name;
			$customerEmail = (string) $user->user_email;
		}

		return array(
			'id'                 => (int) $row['id'],
			'ref'                => (string) $row['ref'],
			'title'              => (string) $row['title'],
			'status'             => (string) $row['status'],
			'customer_id'        => (int) $row['customer_id'],
			'customer_name'      => $customerName,
			'customer_email'     => $customerEmail,
			'currency'           => (string) $row['currency'],
			'pricebook_id'       => $row['pricebook_id'] ? (int) $row['pricebook_id'] : null,
			'current_version_id' => $row['current_version_id'] ? (int) $row['current_version_id'] : null,
			'valid_until'        => $row['valid_until'],
			'created_at'         => (string) $row['created_at'],
			'updated_at'         => (string) $row['updated_at'],
		);
	}

	/**
	 * @param array<string,mixed> $line
	 * @return array<string,mixed>
	 */
	private function serializeLine( array $line ): array {
		return array(
			'id'               => (int) $line['id'],
			'row_id'           => 'L' . (string) $line['id'],
			'product_id'       => (int) $line['product_id'],
			'variation_id'     => (int) $line['variation_id'],
			'sku'              => (string) $line['sku'],
			'name'             => (string) $line['name'],
			'variation_summary' => (string) ( $line['variation_summary'] ?? '' ),
			'qty_min'          => (int) $line['qty_min'],
			'qty_step'         => (int) $line['qty_step'],
			'unit_price'       => (string) $line['unit_price'],
			'price_source'     => (string) $line['price_source'],
			'qty_breaks'       => json_decode( (string) ( $line['qty_breaks'] ?? '' ), true ) ?: array(),
			'sort_order'       => (int) $line['sort_order'],
			'image_id'         => $line['image_id'] ? (int) $line['image_id'] : null,
		);
	}

	/**
	 * @param array<string,mixed> $quote
	 * @return array<string,mixed>
	 */
	private function serializeQuoteSummary( array $quote ): array {
		$report = json_decode( (string) ( $quote['discrepancy_report'] ?? '' ), true );

		return array(
			'id'              => (int) $quote['id'],
			'ref'             => (string) $quote['ref'],
			'revision_no'     => (int) $quote['revision_no'],
			'status'          => (string) $quote['status'],
			'requested_at'    => $quote['requested_at'],
			'expires_at'      => $quote['expires_at'],
			'blocking_count'  => is_array( $report ) ? (int) ( $report['blocking_count'] ?? 0 ) : 0,
			'warning_count'   => is_array( $report ) ? (int) ( $report['warning_count'] ?? 0 ) : 0,
			'converted_order_id' => $quote['converted_order_id'] ? (int) $quote['converted_order_id'] : null,
		);
	}

	/**
	 * @param array<string,mixed> $terms
	 * @return array<string,mixed>
	 */
	private function sanitizeTerms( array $terms ): array {
		$allowed = array( 'payment_terms', 'delivery_terms', 'lead_time', 'notes', 'min_order_value' );
		$clean   = array();

		foreach ( $terms as $key => $value ) {
			if ( in_array( $key, $allowed, true ) && is_string( $value ) ) {
				$clean[ $key ] = mb_substr( sanitize_textarea_field( $value ), 0, 2000 );
			}
		}

		return $clean;
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
			return $value . ' 23:59:59';
		}

		if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ) {
			return $value;
		}

		return null;
	}
}
