<?php
/**
 * Runtime requirement checks. Boot is refused (with an actionable admin
 * notice) when the environment cannot safely run the plugin.
 *
 * @package TradeSheetStudio\Core
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Core;

final class Requirements {

	/**
	 * @var list<string>
	 */
	private array $failures = array();

	public function check(): bool {
		if ( PHP_VERSION_ID < 80100 ) {
			$this->failures[] = sprintf(
				/* translators: %s: PHP version. */
				__( 'TradeSheet Studio requires PHP 8.1 or newer. This site runs PHP %s.', 'tradesheet-studio' ),
				PHP_VERSION
			);
		}

		if ( function_exists( 'get_bloginfo' ) && version_compare( (string) get_bloginfo( 'version' ), '6.4', '<' ) ) {
			$this->failures[] = __( 'TradeSheet Studio requires WordPress 6.4 or newer.', 'tradesheet-studio' );
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->failures[] = __( 'TradeSheet Studio requires WooCommerce to be installed and active.', 'tradesheet-studio' );
		}

		foreach ( array( 'mbstring', 'dom', 'json' ) as $extension ) {
			if ( ! extension_loaded( $extension ) ) {
				$this->failures[] = sprintf(
					/* translators: %s: PHP extension name. */
					__( 'TradeSheet Studio requires the PHP %s extension.', 'tradesheet-studio' ),
					$extension
				);
			}
		}

		return $this->failures === array();
	}

	/**
	 * @return list<string>
	 */
	public function failures(): array {
		return $this->failures;
	}

	public function notify(): void {
		add_action(
			'admin_notices',
			function (): void {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}

				echo '<div class="notice notice-error"><p><strong>' .
					esc_html__( 'TradeSheet Studio cannot start.', 'tradesheet-studio' ) . '</strong></p><ul style="margin-left:18px;list-style:disc;">';

				foreach ( $this->failures as $failure ) {
					echo '<li>' . esc_html( $failure ) . '</li>';
				}

				echo '</ul></div>';
			}
		);
	}
}
