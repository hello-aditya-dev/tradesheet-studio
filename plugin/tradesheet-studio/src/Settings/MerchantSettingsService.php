<?php
/**
 * Merchant settings service. Settings live in a single autoloaded option and
 * are validated/sanitized centrally. Defaults degrade gracefully to
 * WordPress/WooCommerce store information.
 *
 * @package TradeSheetStudio\Settings
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Settings;

final class MerchantSettingsService {

        private const OPTION = 'tss_settings';

        private const DEFAULTS = array(
                'company_name'              => '',
                'company_address'           => '',
                'company_email'             => '',
                'company_phone'             => '',
                'logo_id'                   => 0,
                'doc_footer'                => '',
                'default_validity_days'     => 30,
                'quote_validity_days'       => 14,
                'buyer_token_days'          => 30,
                'doc_size'                  => 'a4',
                'default_template'          => 'trade_table',
                'file_retention_days'       => 0,
                'notify_emails'             => true,
                'delete_data_on_uninstall'  => false,
        );

        private const DOC_SIZES    = array( 'a4', 'letter' );
        private const TEMPLATES    = array( 'trade_table', 'visual_catalog', 'technical_sheet' );

        /**
         * @return array<string,mixed>
         */
        public function all(): array {
                $stored = get_option( self::OPTION, array() );
                $stored = is_array( $stored ) ? $stored : array();

                return array_merge( self::DEFAULTS, $stored );
        }

        public function get( string $key ): mixed {
                $all = $this->all();

                return $all[ $key ] ?? null;
        }

        /**
         * Validate, sanitize, persist and return the resulting settings.
         *
         * @param array<string,mixed> $values
         * @return array<string,mixed>
         */
        public function update( array $values ): array {
                $current = $this->all();
                $clean   = $this->sanitize( $values );
                $next    = array_merge( $current, $clean );

                update_option( self::OPTION, $next, false );

                return $next;
        }

        /**
         * @param array<string,mixed> $values
         * @return array<string,mixed>
         */
        private function sanitize( array $values ): array {
                $clean = array();

                foreach ( $values as $key => $value ) {
                        switch ( $key ) {
                                case 'company_name':
                                case 'company_phone':
                                        $clean[ $key ] = mb_substr( sanitize_text_field( (string) $value ), 0, 191 );
                                        break;

                                case 'company_address':
                                case 'doc_footer':
                                        $clean[ $key ] = mb_substr( sanitize_textarea_field( (string) $value ), 0, 2000 );
                                        break;

                                case 'company_email':
                                        $clean[ $key ] = sanitize_email( (string) $value );
                                        break;

                                case 'logo_id':
                                        $clean[ $key ] = absint( $value );
                                        break;

                                case 'default_validity_days':
                                case 'quote_validity_days':
                                case 'buyer_token_days':
                                        $clean[ $key ] = max( 1, min( 730, absint( $value ) ) );
                                        break;

                                case 'file_retention_days':
                                        $clean[ $key ] = max( 0, min( 3650, absint( $value ) ) );
                                        break;

                                case 'doc_size':
                                        if ( in_array( (string) $value, self::DOC_SIZES, true ) ) {
                                                $clean[ $key ] = (string) $value;
                                        }
                                        break;

                                case 'default_template':
                                        if ( in_array( (string) $value, self::TEMPLATES, true ) ) {
                                                $clean[ $key ] = (string) $value;
                                        }
                                        break;

                                case 'notify_emails':
                                case 'delete_data_on_uninstall':
                                        $clean[ $key ] = (bool) $value;
                                        break;
                        }
                }

                return $clean;
        }

        /**
         * Company presentation details with WooCommerce store fallbacks.
         *
         * @return array<string,string|int>
         */
        public function companyPresentation(): array {
                $settings = $this->all();

                $name = (string) $settings['company_name'];
                if ( '' === $name ) {
                        $name = (string) get_option( 'blogname', '' );
                }

                $email = (string) $settings['company_email'];
                if ( '' === $email && function_exists( 'WC' ) ) {
                        $email = (string) WC()->mailer()->get_from_address();
                }

                return array(
                        'name'    => $name,
                        'address' => (string) $settings['company_address'],
                        'email'   => $email,
                        'phone'   => (string) $settings['company_phone'],
                        'logo_id' => (int) $settings['logo_id'],
                        'footer'  => (string) $settings['doc_footer'],
                );
        }
}
