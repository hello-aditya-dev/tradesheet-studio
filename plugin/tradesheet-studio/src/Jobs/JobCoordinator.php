<?php
/**
 * Background job coordination via WooCommerce's Action Scheduler, with a
 * WP-Cron fallback when Action Scheduler is unavailable.
 *
 * Jobs are tracked in tss_jobs with attempts, explicit error states and
 * checkpoints; interrupted jobs are recovered by the hourly sweep.
 *
 * @package TradeSheetStudio\Jobs
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Jobs;

use TradeSheetStudio\Core\Plugin;
use TradeSheetStudio\Database\Schema;
use TradeSheetStudio\Support\Utils;

final class JobCoordinator {

	public function __construct( private Plugin $plugin ) {
	}

	public function register(): void {
		add_action( 'tss_job_async', array( $this, 'runJob' ) );
		add_action( 'tss_job_fallback', array( $this, 'runJob' ) );
	}

	public function enqueue( string $type, array $payload, int $delaySeconds = 0 ): int {
		global $wpdb;

		$now = Utils::nowDb();

		$wpdb->insert(
			Schema::tableName( 'tss_jobs' ),
			array(
				'type'         => $type,
				'payload'      => Utils::jsonEncode( $payload ),
				'status'       => 'queued',
				'max_attempts' => 3,
				'scheduled_at' => $now,
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		$jobId = (int) $wpdb->insert_id;

		if ( $jobId > 0 ) {
			$timestamp = time() + max( 0, $delaySeconds );

			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( $timestamp, 'tss_job_async', array( 'job_id' => $jobId ), 'tradesheet-studio' );
			} else {
				wp_schedule_single_event( $timestamp, 'tss_job_fallback', array( 'job_id' => $jobId ) );
			}
		}

		return $jobId;
	}

	public function runJob( int $jobId ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
		$job = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_jobs' ) . ' WHERE id = %d', $jobId ), ARRAY_A );

		if ( ! is_array( $job ) || in_array( (string) $job['status'], array( 'done', 'cancelled' ), true ) ) {
			return;
		}

		$now = Utils::nowDb();

		$wpdb->update(
			Schema::tableName( 'tss_jobs' ),
			array(
				'status'     => 'running',
				'attempts'   => (int) $job['attempts'] + 1,
				'started_at' => $now,
				'updated_at' => $now,
			),
			array( 'id' => $jobId ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		try {
			$payload = json_decode( (string) ( $job['payload'] ?? '' ), true );
			$payload = is_array( $payload ) ? $payload : array();

			switch ( (string) $job['type'] ) {
				case 'generate_documents':
					$versionId = (int) ( $payload['version_id'] ?? 0 );
					$template  = isset( $payload['template'] ) ? (string) $payload['template'] : null;

					if ( $versionId < 1 ) {
						throw new \InvalidArgumentException( 'Missing version_id in job payload.' );
					}

					$this->plugin->documents()->generateForVersion( $versionId, $this->plugin->actorId(), $template );
					break;

				default:
					throw new \InvalidArgumentException( 'Unknown job type: ' . (string) $job['type'] );
			}

			$wpdb->update(
				Schema::tableName( 'tss_jobs' ),
				array(
					'status'      => 'done',
					'finished_at' => Utils::nowDb(),
					'updated_at'  => Utils::nowDb(),
				),
				array( 'id' => $jobId ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		} catch ( \Throwable $e ) {
			$attempts = (int) $job['attempts'] + 1;
			$retry    = $attempts < (int) $job['max_attempts'];

			$wpdb->update(
				Schema::tableName( 'tss_jobs' ),
				array(
					'status'      => $retry ? 'queued' : 'failed',
					'error'       => mb_substr( $e->getMessage(), 0, 2000 ),
					'finished_at' => $retry ? null : Utils::nowDb(),
					'updated_at'  => Utils::nowDb(),
				),
				array( 'id' => $jobId ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			if ( $retry ) {
				$this->enqueue( (string) $job['type'], is_array( json_decode( (string) $job['payload'], true ) ) ? (array) json_decode( (string) $job['payload'], true ) : array(), 60 );
			}
		}
	}

	public function cancel( int $jobId ): bool {
		global $wpdb;

		$updated = $wpdb->update(
			Schema::tableName( 'tss_jobs' ),
			array(
				'status'     => 'cancelled',
				'updated_at' => Utils::nowDb(),
			),
			array(
				'id'     => $jobId,
				'status' => 'queued',
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);

		return false !== $updated;
	}

	public function retry( int $jobId ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
		$job = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_jobs' ) . ' WHERE id = %d', $jobId ), ARRAY_A );

		if ( ! is_array( $job ) || 'failed' !== (string) $job['status'] ) {
			return false;
		}

		$payload = json_decode( (string) $job['payload'], true );

		$this->enqueue( (string) $job['type'], is_array( $payload ) ? $payload : array() );

		return true;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public function listJobs( string $status = '', int $page = 1, int $perPage = 20 ): array {
		global $wpdb;

		$perPage = max( 1, min( 100, $perPage ) );
		$offset  = ( max( 1, $page ) - 1 ) * $perPage;

		if ( '' !== $status ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_jobs' ) . ' WHERE status = %s ORDER BY updated_at DESC LIMIT %d OFFSET %d', $status, $perPage, $offset ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::tableName( 'tss_jobs' ) . ' ORDER BY updated_at DESC LIMIT %d OFFSET %d', $perPage, $offset ), ARRAY_A );
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Hourly reliability sweep: expiry, retention, recovery, cleanup.
	 */
	public function sweep(): void {
		$this->plugin->packets()->expireDue();
		$this->plugin->quotes()->expireDue();
		$this->plugin->buyerAccess()->cleanup();
		$this->plugin->documents()->retentionCleanup();
		$this->plugin->orders()->recoverySweep();
		$this->staleJobSweep();

		update_option( 'tss_last_sweep_at', Utils::nowDb(), false );
	}

	/**
	 * Jobs stuck in "running" for over 30 minutes are marked failed (their
	 * retry action remains available).
	 */
	public function staleJobSweep(): void {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - 1800 );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from internal fixed list.
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Schema::tableName( 'tss_jobs' ) . " SET status = 'failed', error = %s, updated_at = %s WHERE status = 'running' AND started_at < %s", 'Job timed out (stale running state).', Utils::nowDb(), $cutoff ) );
	}
}
