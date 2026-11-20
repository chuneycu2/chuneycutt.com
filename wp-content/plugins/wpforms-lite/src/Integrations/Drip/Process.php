<?php

namespace WPForms\Integrations\Drip;

use WPForms\Providers\Provider\Process as ProviderProcess;

/**
 * Queues one async task per Drip connection of a submitted form.
 *
 * @since 2.0.5
 */
class Process extends ProviderProcess {

	/**
	 * Receive all wpforms_process_complete params and queue the connections.
	 *
	 * @since 2.0.5
	 *
	 * @param array $fields    Submitted fields.
	 * @param array $entry     Submitted form content.
	 * @param array $form_data Form data and settings.
	 * @param int   $entry_id  Saved entry ID, 0 on Lite.
	 */
	public function process( $fields, $entry, $form_data, $entry_id ) {

		if ( empty( $form_data['providers'][ $this->core->slug ] ) ) {
			return;
		}

		$this->fields    = (array) $fields;
		$this->entry     = (array) $entry;
		$this->form_data = (array) $form_data;
		$this->entry_id  = (int) $entry_id;

		foreach ( (array) $form_data['providers'][ $this->core->slug ] as $connection_id => $connection ) {
			if ( $connection_id === '__lock__' ) {
				continue;
			}

			$this->process_connection( (array) $connection );
		}
	}

	/**
	 * Queue one connection, unless it is incomplete or its conditional logic stops it.
	 *
	 * @since 2.0.5
	 *
	 * @param array $connection Connection data.
	 */
	private function process_connection( array $connection ) {

		if ( empty( $connection['action'] ) || empty( $connection['account_id'] ) ) {
			return;
		}

		if ( ! $this->process_conditionals( $this->fields, $this->form_data, $connection ) ) {
			wpforms_log(
				sprintf( 'The Drip connection %s was not processed due to conditional logic.', $connection['name'] ?? '' ),
				$this->fields,
				[
					'type'    => [ 'provider', 'conditional_logic' ],
					'parent'  => $this->entry_id,
					'form_id' => (int) ( $this->form_data['id'] ?? 0 ),
				]
			);

			return;
		}

		$tasks = wpforms()->obj( 'tasks' );

		if ( ! $tasks ) {
			return;
		}

		$tasks
			->create( ProcessActionTask::ACTION )
			->async()
			->params( $connection, $this->fields, $this->form_data, $this->entry_id, $this->get_submission_context( $connection ) )
			->register();
	}

	/**
	 * The user id and the IP the connection asks for, captured now: the task runs without the
	 * request, and Lite stores no entry to read them from later.
	 *
	 * @since 2.0.5
	 *
	 * @param array $connection Connection data.
	 *
	 * @return array
	 */
	private function get_submission_context( array $connection ): array {

		$collect_ip = ! empty( $connection['ip_address'] ) && wpforms_is_collecting_ip_allowed( $this->form_data );

		return [
			'user_id'    => empty( $connection['user_id'] ) ? 0 : (int) get_current_user_id(),
			'ip_address' => $collect_ip ? (string) wpforms_get_ip() : '',
		];
	}
}
