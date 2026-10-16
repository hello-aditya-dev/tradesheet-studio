<?php
/**
 * Packet state machine.
 *
 * draft → issued → submitted → in_review → approved → accepted → ordered
 * plus terminal states: expired, revoked, cancelled, archived.
 *
 * Supersession lives at the VERSION level (an issued version becomes
 * "superseded" when a newer version of the same packet is published), never
 * by rewriting an issued version.
 *
 * @package TradeSheetStudio\Packets
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Packets;

use TradeSheetStudio\Support\ValidationException;

final class PacketStateMachine {

        public const DRAFT     = 'draft';
        public const ISSUED    = 'issued';
        public const SUBMITTED = 'submitted';
        public const IN_REVIEW = 'in_review';
        public const APPROVED  = 'approved';
        public const ACCEPTED  = 'accepted';
        public const ORDERED   = 'ordered';
        public const EXPIRED   = 'expired';
        public const REVOKED   = 'revoked';
        public const CANCELLED = 'cancelled';
        public const ARCHIVED  = 'archived';

        private const TRANSITIONS = array(
                self::DRAFT     => array( self::ISSUED, self::CANCELLED ),
                self::ISSUED    => array( self::DRAFT, self::SUBMITTED, self::EXPIRED, self::REVOKED, self::CANCELLED, self::ARCHIVED ),
                self::SUBMITTED => array( self::DRAFT, self::IN_REVIEW, self::EXPIRED, self::REVOKED, self::CANCELLED, self::ARCHIVED ),
                self::IN_REVIEW => array( self::DRAFT, self::APPROVED, self::ISSUED, self::EXPIRED, self::REVOKED, self::CANCELLED, self::ARCHIVED ),
                self::APPROVED  => array( self::DRAFT, self::ACCEPTED, self::IN_REVIEW, self::EXPIRED, self::REVOKED, self::CANCELLED, self::ARCHIVED ),
                self::ACCEPTED  => array( self::ORDERED, self::EXPIRED, self::REVOKED, self::CANCELLED, self::ARCHIVED ),
                self::ORDERED   => array( self::ARCHIVED ),
                self::EXPIRED   => array( self::ARCHIVED ),
                self::REVOKED   => array( self::ARCHIVED ),
                self::CANCELLED => array( self::ARCHIVED ),
                self::ARCHIVED  => array(),
        );

        /**
         * States in which a buyer portal and quote submissions remain live.
         */
        public const LIVE_STATES = array( self::ISSUED, self::SUBMITTED, self::IN_REVIEW, self::APPROVED, self::ACCEPTED );

        /**
         * @return list<string>
         */
        public static function states(): array {
                return array_keys( self::TRANSITIONS );
        }

        public static function can( string $from, string $to ): bool {
                if ( ! isset( self::TRANSITIONS[ $from ] ) ) {
                        return false;
                }

                return in_array( $to, self::TRANSITIONS[ $from ], true );
        }

        public static function assert( string $from, string $to ): void {
                if ( ! self::can( $from, $to ) ) {
                        throw new ValidationException(
                                sprintf(
                                        /* translators: 1: from state, 2: to state. */
                                        __( 'Illegal packet state transition "%1$s" → "%2$s".', 'tradesheet-studio' ),
                                        $from,
                                        $to
                                )
                        );
                }
        }

        public static function isTerminal( string $state ): bool {
                return ! isset( self::TRANSITIONS[ $state ] ) || array() === self::TRANSITIONS[ $state ] && self::ORDERED !== $state;
        }

        public static function isLive( string $state ): bool {
                return in_array( $state, self::LIVE_STATES, true );
        }
}
