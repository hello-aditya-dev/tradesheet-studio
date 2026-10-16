<?php
/**
 * Plugin bootstrap container and wiring. Controllers stay thin; services
 * are constructed lazily so dependencies never cycle at load time.
 *
 * @package TradeSheetStudio\Core
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Core;

use TradeSheetStudio\Buyers\BuyerAccessService;
use TradeSheetStudio\Buyers\BuyerPortal;
use TradeSheetStudio\Database\Schema;
use TradeSheetStudio\Documents\CsvOrderSheetService;
use TradeSheetStudio\Documents\DocumentService;
use TradeSheetStudio\Documents\PdfRenderer;
use TradeSheetStudio\Jobs\JobCoordinator;
use TradeSheetStudio\Orders\WooCommerceOrderBridge;
use TradeSheetStudio\Packets\PacketRepository;
use TradeSheetStudio\Packets\PacketSnapshotService;
use TradeSheetStudio\Pricing\PriceBookRepository;
use TradeSheetStudio\Pricing\PricingEngine;
use TradeSheetStudio\Privacy\PrivacyService;
use TradeSheetStudio\Products\ProductAdapter;
use TradeSheetStudio\Quotes\QuoteIntegrityEngine;
use TradeSheetStudio\Quotes\QuoteRepository;
use TradeSheetStudio\Quotes\QuoteRevisionService;
use TradeSheetStudio\Settings\MerchantSettingsService;
use TradeSheetStudio\Support\EventLogger;
use TradeSheetStudio\Support\Utils;

final class Plugin {

	private static ?Plugin $instance = null;

	private ?MerchantSettingsService $settings     = null;
	private ?EventLogger $events                    = null;
	private ?ProductAdapter $products               = null;
	private ?PriceBookRepository $priceBooks        = null;
	private ?PricingEngine $pricing                 = null;
	private ?PacketRepository $packets              = null;
	private ?BuyerAccessService $buyerAccess        = null;
	private ?PdfRenderer $pdfRenderer               = null;
	private ?CsvOrderSheetService $csv              = null;
	private ?DocumentService $documents             = null;
	private ?PacketSnapshotService $snapshots       = null;
	private ?QuoteIntegrityEngine $integrity        = null;
	private ?QuoteRepository $quotes                = null;
	private ?QuoteRevisionService $quoteRevisions   = null;
	private ?WooCommerceOrderBridge $orders         = null;
	private ?JobCoordinator $jobs                   = null;

	public static function instance(): ?Plugin {
		return self::$instance;
	}

	public function boot(): void {
		if ( null !== self::$instance ) {
			return;
		}
		self::$instance = $this;

		load_plugin_textdomain( 'tradesheet-studio', false, dirname( TSS_PLUGIN_BASENAME ) . '/languages' );

		( new Installer() )->maybeUpgrade();

		add_action( 'rest_api_init', array( $this, 'registerRestRoutes' ) );

		( new \TradeSheetStudio\Admin\AdminPage( $this ) )->register();
		( new BuyerPortal( $this ) )->register();
		( new JobCoordinator( $this ) )->register();
		( new PrivacyService() )->register();

		add_action( 'tss_hourly_sweep', array( $this, 'hourlySweep' ) );
		add_action( 'admin_notices', array( $this, 'adminNotices' ) );
		add_filter( 'plugin_action_links_' . TSS_PLUGIN_BASENAME, array( $this, 'actionLinks' ) );

		// Quote provenance on the WooCommerce order screen.
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'renderOrderProvenance' ) );
	}

	public function registerRestRoutes(): void {
		\TradeSheetStudio\Api\Routes::register( $this );
	}

	public function hourlySweep(): void {
		$this->jobs()->sweep();
	}

	public function adminNotices(): void {
		if ( ! Capabilities::canManage() ) {
			return;
		}

		if ( get_transient( 'tss_activation_missing_woocommerce' ) ) {
			delete_transient( 'tss_activation_missing_woocommerce' );
			echo '<div class="notice notice-warning"><p>' .
				esc_html__( 'TradeSheet Studio was activated while WooCommerce was inactive. It will start working as soon as WooCommerce is active.', 'tradesheet-studio' ) .
				'</p></div>';
		}

		if ( ! file_exists( TSS_PLUGIN_DIR . 'assets/admin.js' ) ) {
			echo '<div class="notice notice-error"><p><strong>' .
				esc_html__( 'TradeSheet Studio compiled assets are missing.', 'tradesheet-studio' ) . '</strong> ' .
				esc_html__( 'Reinstall the plugin from the official release ZIP (the release bundles prebuilt assets).', 'tradesheet-studio' ) .
				'</p></div>';
		}
	}

	/**
	 * @param list<string> $links
	 * @return list<string>
	 */
	public function actionLinks( array $links ): array {
		$url = admin_url( 'admin.php?page=tradesheet-studio' );

		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'TradeSheet Studio', 'tradesheet-studio' ) . '</a>'
		);

		return $links;
	}

	/**
	 * Show DealPacket provenance on the WooCommerce order edit screen.
	 */
	public function renderOrderProvenance( \WC_Order $order ): void {
		if ( ! Capabilities::canManage() ) {
			return;
		}

		$quoteRef = (string) $order->get_meta( '_tss_quote_ref' );
		$packetRef = (string) $order->get_meta( '_tss_packet_ref' );

		if ( '' === $quoteRef && '' === $packetRef ) {
			return;
		}

		echo '<p style="margin-top:8px;"><strong>' . esc_html__( 'TradeSheet Studio', 'tradesheet-studio' ) . '</strong><br/>' .
			esc_html(
				sprintf(
					/* translators: 1: quote ref, 2: packet ref, 3: version. */
					__( 'Created from quote %1$s (packet %2$s, version %3$s) at negotiated line prices.', 'tradesheet-studio' ),
					$quoteRef,
					$packetRef,
					(string) $order->get_meta( '_tss_packet_version' )
				)
			) . '</p>';
	}

	// ------------------------------------------------------------------ //
	// Lazy service accessors.
	// ------------------------------------------------------------------ //

	public function settings(): MerchantSettingsService {
		if ( null === $this->settings ) {
			$this->settings = new MerchantSettingsService();
		}

		return $this->settings;
	}

	public function events(): EventLogger {
		if ( null === $this->events ) {
			$this->events = new EventLogger();
		}

		return $this->events;
	}

	public function products(): ProductAdapter {
		if ( null === $this->products ) {
			$this->products = new ProductAdapter();
		}

		return $this->products;
	}

	public function priceBooks(): PriceBookRepository {
		if ( null === $this->priceBooks ) {
			$this->priceBooks = new PriceBookRepository();
		}

		return $this->priceBooks;
	}

	public function pricing(): PricingEngine {
		if ( null === $this->pricing ) {
			$this->pricing = new PricingEngine( $this->priceBooks(), $this->products() );
		}

		return $this->pricing;
	}

	public function packets(): PacketRepository {
		if ( null === $this->packets ) {
			$this->packets = new PacketRepository();
		}

		return $this->packets;
	}

	public function buyerAccess(): BuyerAccessService {
		if ( null === $this->buyerAccess ) {
			$this->buyerAccess = new BuyerAccessService( $this->packets(), $this->settings() );
		}

		return $this->buyerAccess;
	}

	public function pdfRenderer(): PdfRenderer {
		if ( null === $this->pdfRenderer ) {
			$this->pdfRenderer = new PdfRenderer( $this->packets(), $this->settings() );
		}

		return $this->pdfRenderer;
	}

	public function csv(): CsvOrderSheetService {
		if ( null === $this->csv ) {
			$this->csv = new CsvOrderSheetService( $this->packets() );
		}

		return $this->csv;
	}

	public function documents(): DocumentService {
		if ( null === $this->documents ) {
			$this->documents = new DocumentService(
				$this->packets(),
				$this->pdfRenderer(),
				$this->csv(),
				$this->settings(),
				$this->events()
			);
		}

		return $this->documents;
	}

	public function snapshots(): PacketSnapshotService {
		if ( null === $this->snapshots ) {
			$this->snapshots = new PacketSnapshotService(
				$this->packets(),
				$this->products(),
				$this->priceBooks(),
				$this->settings(),
				$this->buyerAccess(),
				$this->events()
			);
		}

		return $this->snapshots;
	}

	public function integrity(): QuoteIntegrityEngine {
		if ( null === $this->integrity ) {
			$this->integrity = new QuoteIntegrityEngine( $this->packets(), $this->products(), $this->pricing() );
		}

		return $this->integrity;
	}

	public function quotes(): QuoteRepository {
		if ( null === $this->quotes ) {
			$this->quotes = new QuoteRepository( $this->packets(), $this->settings(), $this->events(), $this->integrity() );
		}

		return $this->quotes;
	}

	public function quoteRevisions(): QuoteRevisionService {
		if ( null === $this->quoteRevisions ) {
			$this->quoteRevisions = new QuoteRevisionService( $this->quotes(), $this->packets(), $this->integrity(), $this->events() );
		}

		return $this->quoteRevisions;
	}

	public function orders(): WooCommerceOrderBridge {
		if ( null === $this->orders ) {
			$this->orders = new WooCommerceOrderBridge( $this->packets(), $this->quotes(), $this->events() );
		}

		return $this->orders;
	}

	public function jobs(): JobCoordinator {
		if ( null === $this->jobs ) {
			$this->jobs = new JobCoordinator( $this );
		}

		return $this->jobs;
	}

	/**
	 * Table name helper for services that query directly.
	 */
	public function table( string $id ): string {
		return Schema::tableName( $id );
	}

	/**
	 * Current actor id (null for anonymous).
	 */
	public function actorId(): ?int {
		$userId = get_current_user_id();

		return $userId > 0 ? $userId : null;
	}

	/**
	 * Now (site timezone) — centralised for services.
	 */
	public function now(): string {
		return Utils::nowDb();
	}
}
