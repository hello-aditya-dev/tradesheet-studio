<?php
/**
 * Transactional email notifications (opt-out via settings). No third-party
 * service is used; delivery relies on WordPress wp_mail().
 *
 * @package TradeSheetStudio\Support
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Support;

final class Notifier {

	private static function enabled(): bool {
		$settings = new \TradeSheetStudio\Settings\MerchantSettingsService();

		return (bool) $settings->get( 'notify_emails' );
	}

	private static function send( string $to, string $subject, string $body ): bool {
		if ( '' === $to || ! is_email( $to ) || ! self::enabled() || ! function_exists( 'wp_mail' ) ) {
			return false;
		}

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		return wp_mail( $to, $subject, $body, $headers );
	}

	private static function buyerEmail( int $customerId ): string {
		$user = $customerId > 0 ? get_userdata( $customerId ) : false;

		return ( false !== $user && isset( $user->user_email ) ) ? (string) $user->user_email : '';
	}

	private static function merchantEmail(): string {
		$settings = new \TradeSheetStudio\Settings\MerchantSettingsService();
		$email    = (string) $settings->get( 'company_email' );

		if ( '' === $email ) {
			$email = (string) get_option( 'admin_email', '' );
		}

		return $email;
	}

	public static function quoteRequested( int $customerId, string $packetRef, string $quoteRef ): bool {
		return self::send(
			self::merchantEmail(),
			sprintf( '[%s] New quote request %s (packet %s)', get_option( 'blogname', 'Store' ), $quoteRef, $packetRef ),
			sprintf(
				"A buyer submitted a quotation request.\n\nQuote: %s\nPacket: %s\n\nReview it in WooCommerce → TradeSheet Studio → Quotes.\n",
				$quoteRef,
				$packetRef
			)
		);
	}

	public static function quoteRevised( int $customerId, string $packetRef, string $revisionRef ): bool {
		return self::send(
			self::buyerEmail( $customerId ),
			sprintf( '[%s] Quotation %s revised — your confirmation needed', get_option( 'blogname', 'Store' ), $packetRef ),
			sprintf(
				"The merchant issued a revised quotation for your request on packet %s.\n\nRevision: %s\n\nOpen your order portal to review the changes and explicitly accept or reject the revision.\n",
				$packetRef,
				$revisionRef
			)
		);
	}

	public static function quoteApproved( int $customerId, string $packetRef, string $quoteRef ): bool {
		return self::send(
			self::buyerEmail( $customerId ),
			sprintf( '[%s] Quotation %s approved', get_option( 'blogname', 'Store' ), $quoteRef ),
			sprintf(
				"Your quotation request for packet %s has been approved at the quoted terms.\n\nQuote: %s\n\nOpen your order portal to accept it and create the order.\n",
				$packetRef,
				$quoteRef
			)
		);
	}

	public static function orderCreated( int $customerId, int $orderId, string $payUrl ): bool {
		$payLine = '' !== $payUrl ? sprintf( "Pay or review your order: %s\n", $payUrl ) : '';

		return self::send(
			self::buyerEmail( $customerId ),
			sprintf( '[%s] Order #%d created from your accepted quotation', get_option( 'blogname', 'Store' ), $orderId ),
			sprintf(
				"Your accepted quotation was converted into WooCommerce order #%d at the negotiated terms.\n\n%sPayment is handled by the store's normal checkout.\n",
				$orderId,
				$payLine
			)
		);
	}
}
