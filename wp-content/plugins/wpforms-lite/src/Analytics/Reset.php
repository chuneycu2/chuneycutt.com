<?php

namespace WPForms\Analytics;

use RuntimeException;
use Throwable;
use WPForms\Admin\Dashboard\Cache;
use WPForms\Db\Analytics\DB;

/**
 * Deletes all analytics data collected for a form.
 *
 * @since 2.0.5
 */
class Reset {

	/**
	 * Initialize.
	 *
	 * @since 2.0.5
	 */
	public function init(): void {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.5
	 */
	private function hooks(): void {

		add_action( 'wpforms_delete_form', [ $this, 'delete_forms_data' ] );
	}

	/**
	 * Drop the analytics data of permanently deleted forms, which nothing can reach any more.
	 *
	 * @since 2.0.5
	 *
	 * @param array $ids Deleted form IDs.
	 */
	public function delete_forms_data( $ids ): void {

		$deleted = false;

		foreach ( (array) $ids as $form_id ) {
			if ( $this->delete_form_rows( (int) $form_id ) ) {
				$deleted = true;
			}
		}

		if ( $deleted ) {
			$this->clear_caches();
		}
	}

	/**
	 * Delete every analytics row of a form and clear the caches that serve them.
	 *
	 * @since 2.0.5
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return bool False when the deletion failed and was rolled back.
	 */
	public function reset_form( int $form_id ): bool {

		if ( ! $this->delete_form_rows( $form_id ) ) {
			return false;
		}

		$this->clear_caches();

		return true;
	}

	/**
	 * Delete every analytics row of a form in one transaction, leaving the caches to the caller.
	 *
	 * @since 2.0.5
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return bool False when the deletion failed and was rolled back.
	 */
	private function delete_form_rows( int $form_id ): bool {

		global $wpdb;

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		try {
			$this->delete_rows( $form_id );

			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} catch ( Throwable $e ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			wpforms_log(
				'Analytics reset: transaction rolled back',
				[
					'message' => $e->getMessage(),
				],
				[
					'type'    => [ 'error' ],
					'form_id' => $form_id,
					'force'   => true,
				]
			);

			return false;
		}

		return true;
	}

	/**
	 * Delete the form's rows from the form-level analytics tables.
	 *
	 * @since 2.0.5
	 *
	 * @param int $form_id Form ID.
	 *
	 * @throws RuntimeException When a DELETE fails, so the surrounding transaction rolls back.
	 */
	protected function delete_rows( int $form_id ): void {

		global $wpdb;

		$snaps = DB::snapshots_table();
		$forms = DB::forms_table();

		// Unprocessed snapshots go too: today's figures are read live from them,
		// and the next aggregation run would add them back to the totals.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$this->run_queries(
			[
				$wpdb->prepare( "DELETE FROM $snaps WHERE form_id = %d", $form_id ),
				$wpdb->prepare( "DELETE FROM $forms WHERE form_id = %d", $form_id ),
			]
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Run prepared DELETEs in order, stopping at the first one the database refuses.
	 *
	 * @since 2.0.5
	 *
	 * @param array $queries Prepared queries.
	 *
	 * @throws RuntimeException When a DELETE fails, so the surrounding transaction rolls back.
	 */
	protected function run_queries( array $queries ): void {

		global $wpdb;

		foreach ( $queries as $query ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			if ( $wpdb->query( $query ) === false ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message routed to the error log, not HTML output.
				throw new RuntimeException( 'Analytics reset: DELETE failed. ' . $wpdb->last_error );
			}
		}
	}

	/**
	 * Clear the caches that would keep serving the deleted figures.
	 *
	 * @since 2.0.5
	 */
	protected function clear_caches(): void {

		Cache::invalidate_all_presets();
	}
}
