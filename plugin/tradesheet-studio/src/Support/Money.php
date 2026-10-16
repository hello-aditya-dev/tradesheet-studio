<?php
/**
 * Exact decimal money arithmetic on canonical decimal strings.
 *
 * RATIONALE: binary floating point is unsuitable for commercial money. Every
 * TradeSheet Studio pricing path uses this helper, which converts decimal
 * strings to scaled integers and performs integer arithmetic only.
 *
 * Canonical representation: plain decimal string, up to 9 integer digits and
 * 4 fractional digits (matching DECIMAL(19,4) columns), e.g. "123.4500".
 *
 * Enforced business bounds (also validated by the preflight validator):
 *   - absolute values <= 999,999,999.9999
 *   - quantities <= 100,000 per line
 * These bounds keep every intermediate product safely inside signed 64-bit
 * integers on every supported PHP build, with no extension dependencies.
 *
 * Rounding mode: half-away-from-zero, applied ONLY at presentation or when
 * a rule input carries more precision than the working scale.
 *
 * @package TradeSheetStudio\Support
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Support;

final class Money {

        public const SCALE   = 4;
        public const MAX_QTY = 100000;

        public static function isValid( string $value ): bool {
                return preg_match( '/^-?\d{1,9}(\.\d{1,4})?$/', $value ) === 1;
        }

        public static function assertValid( string $value ): void {
                if ( ! self::isValid( $value ) ) {
                        throw new \InvalidArgumentException( sprintf( 'Invalid money value: %s', $value ) );
                }
        }

        public static function zero(): string {
                return '0.0000';
        }

        /**
         * Normalize a database money value to the canonical scale-4 string.
         * Some drivers (e.g. SQLite/PDO) return DECIMAL columns without the
         * defined scale ("4399" instead of "4399.0000"); MySQL/MariaDB return
         * the padded form. Canonicalization makes both storage worlds produce
         * identical canonical snapshots.
         */
        public static function fromDb( mixed $value, int $dp = self::SCALE ): string {
                $s = trim( (string) $value );

                if ( '' === $s ) {
                        return '0.0000';
                }

                if ( ! self::isValid( $s ) ) {
                        $s = self::round( preg_replace( '/[^0-9.\-]/', '', $s ) ?? '', $dp );
                        if ( ! self::isValid( $s ) ) {
                                return '0.0000';
                        }
                }

                return self::round( $s, $dp );
        }

        /**
         * Round half-away-from-zero to $dp decimals (0..4).
         */
        public static function round( string $value, int $dp = self::SCALE ): string {
                self::assertValid( $value );
                $dp  = max( 0, min( 4, $dp ) );
                $neg = ( $value !== '' && '0' !== $value && $value[0] === '-' );

                if ( $neg ) {
                        $value = substr( $value, 1 );
                }

                [ $int, $frac ] = self::split( $value );
                $scaled         = (int) ( $int . substr( str_pad( $frac, $dp + 1, '0', STR_PAD_RIGHT ), 0, $dp + 1 ) );
                $q              = intdiv( $scaled, 10 );
                if ( $scaled % 10 >= 5 ) {
                        ++$q;
                }

                $out = self::build( $q, $dp );

                return $neg ? '-' . $out : $out;
        }

        public static function add( string $a, string $b ): string {
                return self::fromScaledInt( self::toScaledInt( $a ) + self::toScaledInt( $b ) );
        }

        public static function sub( string $a, string $b ): string {
                return self::fromScaledInt( self::toScaledInt( $a ) - self::toScaledInt( $b ) );
        }

        /**
         * Multiply a decimal by an integer quantity.
         */
        public static function mul( string $a, int $qty ): string {
                if ( $qty < 0 ) {
                        throw new \InvalidArgumentException( 'Quantity must be non-negative.' );
                }
                if ( $qty > self::MAX_QTY ) {
                        throw new \InvalidArgumentException( sprintf( 'Quantity exceeds the supported maximum of %d.', self::MAX_QTY ) );
                }

                return self::fromScaledInt( self::toScaledInt( $a ) * $qty );
        }

        public static function cmp( string $a, string $b ): int {
                return self::toScaledInt( $a ) <=> self::toScaledInt( $b );
        }

        public static function equals( string $a, string $b ): bool {
                return self::cmp( $a, $b ) === 0;
        }

        /**
         * Percentage OF a value: a * pct / 100, rounded half-away-from-zero at the
         * working scale. Percentage precision is two decimals.
         */
        public static function percent( string $a, string $pct ): string {
                self::assertValid( $a );
                $pct = self::round( $pct, 2 );
                self::assertValid( $pct );

                $ai = self::toScaledInt( $a );    // scale 4.
                $pi = self::toScaledInt2( $pct ); // scale 2.

                $prod = $ai * $pi;                // scale 6.
                // Result at scale 4: (ai*pi) encodes a*pct with 6 decimals, and the
                // /100 percentage division removes two more — divide by 10^4.
                $divisor = 10 ** 4;
                $q       = intdiv( $prod, $divisor );
                $rem     = abs( $prod ) % $divisor;

                if ( $rem * 2 >= $divisor ) {
                        $q += ( $prod >= 0 ) ? 1 : -1;
                }

                return self::fromScaledInt( $q );
        }

        /**
         * Presentation formatting: fixed decimals, optional thousands separator.
         */
        public static function format( string $value, int $dp = 2, string $decimalSep = '.', string $thousandsSep = '' ): string {
                $rounded = self::round( $value, $dp );
                $neg     = ( $rounded !== '' && $rounded[0] === '-' );
                if ( $neg ) {
                        $rounded = substr( $rounded, 1 );
                }

                [ $int, $frac ] = self::split( $rounded );
                $frac           = str_pad( $frac, $dp, '0', STR_PAD_RIGHT );

                if ( $thousandsSep !== '' ) {
                        $int = preg_replace( '/\B(?=(\d{3})+(?!\d))/', $thousandsSep, $int ) ?? $int;
                }

                $out = ( $dp > 0 ) ? $int . $decimalSep . $frac : $int;

                return $neg ? '-' . $out : $out;
        }

        /**
         * Convert a validated decimal string (<= 4 fraction digits) to a scaled
         * integer at the working scale.
         */
        private static function toScaledInt( string $value ): int {
                self::assertValid( $value );
                $neg = ( $value !== '' && $value[0] === '-' );
                if ( $neg ) {
                        $value = substr( $value, 1 );
                }

                [ $int, $frac ] = self::split( $value );
                $frac           = str_pad( $frac, self::SCALE, '0', STR_PAD_RIGHT );

                $scaled = (int) $int * ( 10 ** self::SCALE ) + (int) $frac;

                return $neg ? -$scaled : $scaled;
        }

        /**
         * Scale-2 variant used for percentage inputs.
         */
        private static function toScaledInt2( string $value ): int {
                self::assertValid( $value );
                $neg = ( $value !== '' && $value[0] === '-' );
                if ( $neg ) {
                        $value = substr( $value, 1 );
                }

                [ $int, $frac ] = self::split( $value );
                $frac           = str_pad( $frac, 2, '0', STR_PAD_RIGHT );

                $scaled = (int) $int * 100 + (int) substr( $frac, 0, 2 );

                return $neg ? -$scaled : $scaled;
        }

        private static function fromScaledInt( int $value ): string {
                $neg = $value < 0 ? '-' : '';
                $value = abs( $value );

                $s = (string) $value;
                $s = str_pad( $s, self::SCALE + 1, '0', STR_PAD_LEFT );

                return $neg . substr( $s, 0, -self::SCALE ) . '.' . substr( $s, -self::SCALE );
        }

        /**
         * @return array{0:string,1:string}
         */
        private static function split( string $value ): array {
                $pos = strpos( $value, '.' );
                if ( false === $pos ) {
                        return array( $value, '' );
                }

                return array( substr( $value, 0, $pos ), substr( $value, $pos + 1 ) );
        }

        private static function build( int $value, int $dp ): string {
                if ( 0 === $dp ) {
                        return (string) $value;
                }

                $s = str_pad( (string) $value, $dp + 1, '0', STR_PAD_LEFT );

                return substr( $s, 0, -$dp ) . '.' . substr( $s, -$dp );
        }
}
