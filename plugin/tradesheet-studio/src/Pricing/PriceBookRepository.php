<?php
/**
 * Price book persistence. All queries use $wpdb->prepare.
 *
 * Customer assignment is stored in user meta (_tss_pricebook_id): one
 * assigned price book per customer, per the documented precedence model.
 *
 * @package TradeSheetStudio\Pricing
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Pricing;

use TradeSheetStudio\Database\Schema;
use TradeSheetStudio\Support\Money;
use TradeSheetStudio\Support\Utils;

final class PriceBookRepository {

        public function create( string $name, string $description, string $status, string $currency, int $priority, ?string $validFrom, ?string $validTo ): int {
                global $wpdb;

                $now = Utils::nowDb();

                $wpdb->insert(
                        Schema::tableName( 'tss_pricebooks' ),
                        array(
                                'name'        => mb_substr( sanitize_text_field( $name ), 0, 191 ),
                                'description' => mb_substr( sanitize_textarea_field( $description ), 0, 2000 ),
                                'status'      => in_array( $status, array( 'active', 'inactive' ), true ) ? $status : 'active',
                                'currency'    => mb_substr( $currency, 0, 3 ),
                                'priority'    => max( -99, min( 99, $priority ) ),
                                'valid_from'  => $validFrom,
                                'valid_to'    => $validTo,
                                'created_at'  => $now,
                                'updated_at'  => $now,
                        ),
                        array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
                );

                return (int) $wpdb->insert_id;
        }

        /**
         * @param array<string,mixed> $fields
         */
        public function update( int $id, array $fields ): bool {
                global $wpdb;

                $allowed = array( 'name', 'description', 'status', 'currency', 'priority', 'valid_from', 'valid_to' );
                $data    = array();
                $format  = array();

                foreach ( $fields as $key => $value ) {
                        if ( ! in_array( $key, $allowed, true ) ) {
                                continue;
                        }
                        switch ( $key ) {
                                case 'name':
                                        $data[ $key ] = mb_substr( sanitize_text_field( (string) $value ), 0, 191 );
                                        $format[]     = '%s';
                                        break;
                                case 'description':
                                        $data[ $key ] = mb_substr( sanitize_textarea_field( (string) $value ), 0, 2000 );
                                        $format[]     = '%s';
                                        break;
                                case 'status':
                                        if ( in_array( (string) $value, array( 'active', 'inactive' ), true ) ) {
                                                $data[ $key ] = (string) $value;
                                                $format[]     = '%s';
                                        }
                                        break;
                                case 'currency':
                                        $data[ $key ] = mb_substr( strtoupper( sanitize_text_field( (string) $value ) ), 0, 3 );
                                        $format[]     = '%s';
                                        break;
                                case 'priority':
                                        $data[ $key ] = max( -99, min( 99, (int) $value ) );
                                        $format[]     = '%d';
                                        break;
                                case 'valid_from':
                                case 'valid_to':
                                        if ( null === $value || '' === $value ) {
                                                $data[ $key ] = null;
                                                $format[]     = '%s';
                                        } else {
                                                $data[ $key ] = (string) $value;
                                                $format[]     = '%s';
                                        }
                                        break;
                        }
                }

                if ( array() === $data ) {
                        return false;
                }

                $data['updated_at'] = Utils::nowDb();
                $format[]           = '%s';

                $updated = $wpdb->update( Schema::tableName( 'tss_pricebooks' ), $data, array( 'id' => $id ), $format, array( '%d' ) );

                return false !== $updated;
        }

        /**
         * @return array<string,mixed>|null
         */
        public function get( int $id ): ?array {
                global $wpdb;

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_pricebooks' ) . ' WHERE id = %d', $id ), ARRAY_A );

                return is_array( $row ) ? $row : null;
        }

        /**
         * @return list<array<string,mixed>>
         */
        public function listBooks( ?string $status = null ): array {
                global $wpdb;

                if ( null !== $status && in_array( $status, array( 'active', 'inactive' ), true ) ) {
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_pricebooks' ) . ' WHERE status = %s ORDER BY priority DESC, name ASC', $status ), ARRAY_A );
                } else {
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                        $rows = $wpdb->get_results( 'SELECT * FROM ' . Schema::tableName( 'tss_pricebooks' ) . ' ORDER BY priority DESC, name ASC', ARRAY_A );
                }

                return is_array( $rows ) ? $rows : array();
        }

        public function delete( int $id ): bool {
                global $wpdb;

                $wpdb->delete( Schema::tableName( 'tss_pricebooks' ), array( 'id' => $id ), array( '%d' ) );
                $wpdb->delete( Schema::tableName( 'tss_price_rules' ), array( 'pricebook_id' => $id ), array( '%d' ) );

                foreach ( $this->assignments( $id ) as $customerId ) {
                        $this->unassignCustomer( (int) $customerId );
                }

                return true;
        }

        public function duplicate( int $id ): int {
                $book = $this->get( $id );
                if ( null === $book ) {
                        return 0;
                }

                $newId = $this->create(
                        sprintf( '%s (%s)', $book['name'], __( 'copy', 'tradesheet-studio' ) ),
                        (string) $book['description'],
                        'inactive',
                        (string) $book['currency'],
                        (int) $book['priority'],
                        $book['valid_from'],
                        $book['valid_to']
                );

                foreach ( $this->rulesForBook( $id ) as $rule ) {
                        $rule['pricebook_id'] = $newId;
                        $rule['id']           = null;
                        $this->upsertRule( PriceRule::fromRow( $rule ) );
                }

                return $newId;
        }

        /**
         * @return list<array<string,mixed>>
         */
        public function rulesForBook( int $bookId ): array {
                global $wpdb;

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_price_rules' ) . ' WHERE pricebook_id = %d ORDER BY product_id ASC, variation_id ASC, customer_id ASC', $bookId ), ARRAY_A );

                return is_array( $rows ) ? array_map( array( $this, 'normalizeRuleRow' ), $rows ) : array();
        }

        public function upsertRule( PriceRule $rule ): int {
                global $wpdb;

                $now  = Utils::nowDb();
                $data = $rule->toRow();

                // Portable upsert on the (pricebook, customer, product, variation)
                // scope key: explicit select + insert/update. wpdb maps null values to
                // real NULLs in insert/update (unlike prepare('%s'), which yields '').n                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $existingId = (int) $wpdb->get_var(
                        $wpdb->prepare(
                                'SELECT id FROM ' . Schema::tableName( 'tss_price_rules' ) . ' WHERE pricebook_id = %d AND customer_id = %d AND product_id = %d AND variation_id = %d',
                                $rule->pricebookId,
                                $rule->customerId,
                                $rule->productId,
                                $rule->variationId
                        )
                );

                if ( $existingId > 0 ) {
                        $data['updated_at'] = $now;

                        $wpdb->update(
                                Schema::tableName( 'tss_price_rules' ),
                                $data,
                                array( 'id' => $existingId ),
                                null,
                                array( '%d' )
                        );

                        return $existingId;
                }

                $data['created_at'] = $now;
                $data['updated_at'] = $now;

                $wpdb->insert( Schema::tableName( 'tss_price_rules' ), $data );

                return (int) $wpdb->insert_id;
        }

        public function deleteRule( int $ruleId ): bool {
                global $wpdb;

                return false !== $wpdb->delete( Schema::tableName( 'tss_price_rules' ), array( 'id' => $ruleId ), array( '%d' ) );
        }

        /**
         * Candidate rules for a resolution, honouring validity windows.
         *
         * @return list<array<string,mixed>>
         */
        public function candidateRules( int $bookId, int $customerId, int $productId, int $variationId ): array {
                global $wpdb;

                $now = Utils::nowDb();

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                $rows = $wpdb->get_results(
                        $wpdb->prepare(
                                'SELECT * FROM ' . Schema::tableName( 'tss_price_rules' ) .
                                ' WHERE pricebook_id = %d' .
                                ' AND customer_id IN (0, %d)' .
                                ' AND ((' . 'product_id IN (0, %d) AND variation_id = 0) OR (product_id = %d AND variation_id = %d))' .
                                " AND (valid_from IS NULL OR valid_from = '' OR valid_from <= %s)" .
                                " AND (valid_to IS NULL OR valid_to = '' OR valid_to >= %s)",
                                $bookId,
                                $customerId,
                                $productId,
                                $productId,
                                $variationId,
                                $now,
                                $now
                        ),
                        ARRAY_A
                );

                return is_array( $rows ) ? array_map( array( $this, 'normalizeRuleRow' ), $rows ) : array();
        }

        /**
         * Canonicalize rule money fields on database reads.
         *
         * @param array<string,mixed> $rule
         * @return array<string,mixed>
         */
        private function normalizeRuleRow( array $rule ): array {
                if ( null !== ( $rule['price_override'] ?? null ) && '' !== (string) $rule['price_override'] ) {
                        $rule['price_override'] = Money::fromDb( $rule['price_override'] );
                }
                if ( null !== ( $rule['percent_off'] ?? null ) && '' !== (string) $rule['percent_off'] ) {
                        $rule['percent_off'] = Money::fromDb( $rule['percent_off'], 2 );
                }

                if ( ! empty( $rule['qty_breaks'] ) ) {
                        $breaks = json_decode( (string) $rule['qty_breaks'], true );
                        if ( is_array( $breaks ) ) {
                                foreach ( $breaks as $index => $break ) {
                                        if ( is_array( $break ) && isset( $break['qty'], $break['price'] ) ) {
                                                $breaks[ $index ] = array( 'qty' => (int) $break['qty'], 'price' => Money::fromDb( $break['price'] ) );
                                        }
                                }
                                $rule['qty_breaks'] = Utils::jsonEncode( $breaks );
                        }
                }

                return $rule;
        }

        public function assignCustomer( int $bookId, int $customerId ): void {
                update_user_meta( $customerId, '_tss_pricebook_id', $bookId );
        }

        public function unassignCustomer( int $customerId ): void {
                delete_user_meta( $customerId, '_tss_pricebook_id' );
        }

        public function assignedBookId( int $customerId ): int {
                if ( $customerId < 1 ) {
                        return 0;
                }

                return (int) get_user_meta( $customerId, '_tss_pricebook_id', true );
        }

        /**
         * @return list<int>
         */
        public function assignments( int $bookId ): array {
                $users = get_users(
                        array(
                                'meta_key'   => '_tss_pricebook_id',
                                'meta_value' => (string) $bookId,
                                'fields'     => array( 'ID' ),
                                'number'     => 5000,
                        )
                );

                $ids = array();
                foreach ( $users as $user ) {
                        $ids[] = (int) $user->ID;
                }

                return $ids;
        }

        public function ruleCount( int $bookId ): int {
                global $wpdb;

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
                return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::tableName( 'tss_price_rules' ) . ' WHERE pricebook_id = %d', $bookId ) );
        }

        public function isUsable( int $bookId ): bool {
                $book = $this->get( $bookId );
                if ( null === $book || 'active' !== (string) $book['status'] ) {
                        return false;
                }

                $now = Utils::nowDb();
                if ( null !== $book['valid_from'] && (string) $book['valid_from'] > $now ) {
                        return false;
                }
                if ( null !== $book['valid_to'] && (string) $book['valid_to'] < $now ) {
                        return false;
                }

                return true;
        }
}
