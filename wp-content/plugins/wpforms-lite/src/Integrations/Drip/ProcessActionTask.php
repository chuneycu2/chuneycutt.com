<?php

namespace WPForms\Integrations\Drip;

use WPForms\Integrations\Drip\Api\Client;
// phpcs:ignore WPForms.PHP.UseStatement.UnusedUseStatement
use WPForms\Integrations\Drip\Api\Response;
use WPForms\Tasks\Meta;

/**
 * Async task that sends one Drip connection of one submission to the Drip API.
 *
 * @since 2.0.5
 */
class ProcessActionTask {

	/**
	 * Async task action. Kept from the Drip addon so tasks it queued before the upgrade run here.
	 *
	 * @since 2.0.5
	 */
	const ACTION = 'wpforms_drip_process_action';

	/**
	 * Connection data.
	 *
	 * @since 2.0.5
	 *
	 * @var array
	 */
	private $connection = [];

	/**
	 * Submitted fields.
	 *
	 * @since 2.0.5
	 *
	 * @var array
	 */
	private $fields = [];

	/**
	 * Form data and settings.
	 *
	 * @since 2.0.5
	 *
	 * @var array
	 */
	private $form_data = [];

	/**
	 * Entry ID, 0 on Lite.
	 *
	 * @since 2.0.5
	 *
	 * @var int
	 */
	private $entry_id = 0;

	/**
	 * Submit-time context: the user id and the IP address the connection asked for.
	 *
	 * @since 2.0.5
	 *
	 * @var array
	 */
	private $context = [];

	/**
	 * Subscriber email, lower-cased.
	 *
	 * @since 2.0.5
	 *
	 * @var string
	 */
	private $email = '';

	/**
	 * API client bound to the connection's account.
	 *
	 * @since 2.0.5
	 *
	 * @var Client
	 */
	private $client;

	/**
	 * Last API response.
	 *
	 * @since 2.0.5
	 *
	 * @var Response|null
	 */
	private $response;

	/**
	 * Register hooks.
	 *
	 * @since 2.0.5
	 */
	public function hooks() {

		add_action( self::ACTION, [ $this, 'process' ] );
	}

	/**
	 * Process the task.
	 *
	 * @since 2.0.5
	 *
	 * @param int|mixed $meta_id Task meta ID.
	 */
	public function process( $meta_id ) {

		$meta = $this->get_task_meta( (int) $meta_id );

		// The addon queued four items; core adds the submit-time context as the fifth.
		if ( count( $meta ) !== 4 && count( $meta ) !== 5 ) {
			return;
		}

		list( $connection, $fields, $form_data, $entry_id ) = $meta;

		$connection     = (array) $connection;
		$fields         = (array) $fields;
		$options        = wpforms_get_providers_options( Core::SLUG );
		$option_id      = $connection['option_id'] ?? '';
		$email_field_id = $connection['fields']['email'] ?? '';

		// Without a token, an account and an email there is nothing Drip can take.
		if (
			empty( $options[ $option_id ]['api_token'] ) ||
			empty( $connection['account_id'] ) ||
			$email_field_id === '' ||
			empty( $fields[ $email_field_id ]['value'] )
		) {
			return;
		}

		$this->connection = $connection;
		$this->fields     = $fields;
		$this->form_data  = (array) $form_data;
		$this->entry_id   = (int) $entry_id;
		$this->context    = isset( $meta[4] ) ? (array) $meta[4] : [];
		$this->email      = strtolower( (string) $fields[ $email_field_id ]['value'] );
		$this->client     = new Client( (string) $options[ $option_id ]['api_token'], (string) $connection['account_id'] );
		$this->response   = null;

		$this->run_action();
	}

	/**
	 * Get the task payload.
	 *
	 * @since 2.0.5
	 *
	 * @param int $meta_id Task meta ID.
	 *
	 * @return array
	 */
	protected function get_task_meta( int $meta_id ): array {

		$meta = ( new Meta() )->get( $meta_id );

		if ( empty( $meta ) || empty( $meta->data ) || ! is_array( $meta->data ) ) {
			return [];
		}

		return $meta->data;
	}

	/**
	 * Run the connection action and log a failed response.
	 *
	 * @since 2.0.5
	 */
	private function run_action() {

		$methods = [
			'event'                  => 'process_event_action',
			'subscriber_delete'      => 'process_subscriber_delete_action',
			'subscriber_subscribe'   => 'process_subscriber_subscribe_action',
			'subscriber_unsubscribe' => 'process_subscriber_unsubscribe_action',
			'campaign_sub'           => 'process_campaign_subscribe_action',
			'campaign_unsub'         => 'process_campaign_unsubscribe_action',
		];

		$method = $methods[ $this->connection['action'] ?? '' ] ?? '';

		if ( $method === '' ) {
			return;
		}

		$this->$method();

		if ( $this->response && $this->response->has_errors() ) {
			$this->log_errors( $this->response->get_error_message() );
		}
	}

	/**
	 * Record a custom event for the subscriber.
	 *
	 * @since 2.0.5
	 */
	private function process_event_action() {

		if ( empty( $this->connection['events']['name'] ) ) {
			return;
		}

		$this->response = $this->client->post(
			'events',
			[
				'events' => [
					[
						'email'      => $this->email,
						'action'     => $this->connection['events']['name'],
						'prospect'   => $this->is_prospect(),
						'properties' => $this->get_properties(),
					],
				],
			]
		);
	}

	/**
	 * Delete the subscriber.
	 *
	 * @since 2.0.5
	 */
	private function process_subscriber_delete_action() {

		$this->response = $this->client->delete( $this->get_subscriber_endpoint() );
	}

	/**
	 * Create or update the subscriber.
	 *
	 * @since 2.0.5
	 */
	private function process_subscriber_subscribe_action() {

		$subscriber = array_merge(
			$this->get_subscriber_payload(),
			[
				'ip_address'  => $this->get_ip_address(),
				'remove_tags' => $this->connection['tags']['delete'] ?? [],
				'status'      => 'active',
			]
		);

		$new_email_field_id = $this->connection['fields']['new_email'] ?? '';
		$new_email          = $new_email_field_id !== '' && isset( $this->fields[ $new_email_field_id ]['value'] )
			? strtolower( (string) $this->fields[ $new_email_field_id ]['value'] )
			: '';

		if ( $new_email !== '' && $new_email !== $this->email ) {
			$subscriber['new_email'] = $new_email;
		}

		$this->response = $this->client->post( 'subscribers', [ 'subscribers' => [ $subscriber ] ] );
	}

	/**
	 * Unsubscribe the subscriber from all mailings.
	 *
	 * @since 2.0.5
	 */
	private function process_subscriber_unsubscribe_action() {

		$this->response = $this->client->post( $this->get_subscriber_endpoint( '/unsubscribe_all' ) );
	}

	/**
	 * Subscribe to an Email Series campaign: the subscriber has to exist before the enrollment.
	 *
	 * @since 2.0.5
	 */
	private function process_campaign_subscribe_action() {

		if ( empty( $this->connection['campaign_id'] ) ) {
			return;
		}

		$this->response = $this->client->post(
			'subscribers',
			[
				'subscribers' => [
					[
						'email'  => $this->email,
						'status' => 'active',
					],
				],
			]
		);

		if ( $this->response->has_errors() ) {
			return;
		}

		$this->response = $this->client->post(
			'campaigns/' . (int) $this->connection['campaign_id'] . '/subscribers',
			[ 'subscribers' => [ $this->get_subscriber_payload() ] ]
		);
	}

	/**
	 * Unsubscribe from one Email Series campaign, or from all of them.
	 *
	 * @since 2.0.5
	 */
	private function process_campaign_unsubscribe_action() {

		if ( empty( $this->connection['campaign_id'] ) ) {
			return;
		}

		$args = $this->connection['campaign_id'] === 'all' ? [] : [ 'campaign_id' => $this->connection['campaign_id'] ];

		$this->response = $this->client->post( $this->get_subscriber_endpoint( '/remove' ), $args );
	}

	/**
	 * The subscriber endpoint for the submitted email, encoded as one path segment so it cannot leave `subscribers/`.
	 *
	 * @since 2.0.5
	 *
	 * @param string $suffix Sub-resource appended after the email, e.g. `/remove`.
	 *
	 * @return string
	 */
	private function get_subscriber_endpoint( string $suffix = '' ): string {

		return 'subscribers/' . rawurlencode( $this->email ) . $suffix;
	}

	/**
	 * The subscriber fields every subscribe-type action sends, with the GDPR consent when it applies.
	 *
	 * @since 2.0.5
	 *
	 * @return array
	 */
	private function get_subscriber_payload(): array {

		return array_merge(
			[
				'email'           => $this->email,
				'user_id'         => $this->get_user_id(),
				'tags'            => $this->connection['tags']['add'] ?? [],
				'prospect'        => $this->is_prospect(),
				'base_lead_score' => $this->get_base_lead_score(),
				'custom_fields'   => $this->get_properties(),
			],
			$this->get_gdpr_options()
		);
	}

	/**
	 * Whether the connection marks the subscriber as a prospect.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	private function is_prospect(): bool {

		return ! empty( $this->connection['prospect'] );
	}

	/**
	 * The WordPress user id, when the connection asks for it and the visitor was logged in.
	 *
	 * @since 2.0.5
	 *
	 * @return int|string Empty string when unavailable, as the addon sent it.
	 */
	private function get_user_id() {

		if ( empty( $this->connection['user_id'] ) || empty( $this->context['user_id'] ) ) {
			return '';
		}

		return (int) $this->context['user_id'];
	}

	/**
	 * The visitor IP address, when the connection asks for it and the settings allow collecting it.
	 *
	 * @since 2.0.5
	 *
	 * @return string
	 */
	private function get_ip_address(): string {

		if ( empty( $this->connection['ip_address'] ) || empty( $this->context['ip_address'] ) ) {
			return '';
		}

		return (string) $this->context['ip_address'];
	}

	/**
	 * The starting lead score, Drip's default when the connection has none.
	 *
	 * @since 2.0.5
	 *
	 * @return int
	 */
	private function get_base_lead_score(): int {

		return isset( $this->connection['base_lead_score'] ) ? (int) $this->connection['base_lead_score'] : 30;
	}

	/**
	 * GDPR consent fields, when the setting is on and the form has a GDPR checkbox.
	 *
	 * @since 2.0.5
	 *
	 * @return array
	 */
	private function get_gdpr_options(): array {

		$field_ids = array_flip( wp_list_pluck( $this->fields, 'type', 'id' ) );

		if ( ! wpforms_setting( 'gdpr' ) || ! isset( $field_ids['gdpr-checkbox'] ) ) {
			return [];
		}

		$consent_message = $this->fields[ $field_ids['gdpr-checkbox'] ]['value'] ?? '';

		return [
			'eu_consent'         => $consent_message !== '' ? 'granted' : 'denied',
			'eu_consent_message' => $consent_message,
		];
	}

	/**
	 * Custom field values Drip stores on the subscriber or the event.
	 *
	 * @since 2.0.5
	 *
	 * @return array
	 */
	private function get_properties(): array {

		$properties = [
			'form_id'         => (int) ( $this->form_data['id'] ?? 0 ),
			'entry_id'        => $this->entry_id,
			'connection_name' => $this->connection['name'] ?? '',
		];

		// Lite stores no entry, nor does a Pro form with entry storage off: an id of 0 would point nowhere.
		if ( $this->entry_id <= 0 ) {
			unset( $properties['entry_id'] );
		}

		foreach ( (array) ( $this->connection['fields_meta'] ?? [] ) as $property ) {
			if ( empty( $property['name'] ) || ! isset( $property['field_id'] ) || $property['field_id'] === '' ) {
				continue;
			}

			$property_name  = Helpers::sanitize_field_id( (string) $property['name'] );
			$field_id_parts = explode( '.', (string) $property['field_id'] );

			if ( $property_name === '' ) {
				continue;
			}

			$properties[ $property_name ] = $this->get_formatted_field_value( (int) $field_id_parts[0], $field_id_parts[1] ?? '' );
		}

		return $properties;
	}

	/**
	 * A field value, or one of its sub-fields, in the shape Drip receives it.
	 *
	 * @since 2.0.5
	 *
	 * @param int    $field_id     Field ID.
	 * @param string $subfield_key Sub-field key, e.g. `first` of a Name field.
	 *
	 * @return string
	 */
	private function get_formatted_field_value( int $field_id, string $subfield_key ): string {

		if ( empty( $this->fields[ $field_id ]['type'] ) ) {
			return '';
		}

		$field = $this->fields[ $field_id ];

		if ( $subfield_key !== '' && $subfield_key !== 'full' && isset( $field[ $subfield_key ] ) ) {
			return (string) $field[ $subfield_key ];
		}

		// Choice payment fields: the labels only, one per line like the Checkbox field.
		if ( in_array( $field['type'], [ 'payment-checkbox', 'payment-multiple', 'payment-select' ], true ) ) {
			return str_replace( "\r\n", "\n", (string) ( $field['value_choice'] ?? '' ) );
		}

		if ( in_array( $field['type'], [ 'payment-single', 'payment-total' ], true ) ) {
			return (string) wpforms_decode_string( $field['value'] ?? '' );
		}

		return (string) ( $field['value'] ?? '' );
	}

	/**
	 * Log a failed request with the connection and the response.
	 *
	 * @since 2.0.5
	 *
	 * @param string $error_message Error message.
	 */
	private function log_errors( string $error_message ) {

		wpforms_log(
			sprintf( 'Submission to Drip failed (#%d).', $this->entry_id ),
			[
				'drip'       => $error_message,
				'connection' => $this->connection,
				'response'   => $this->response ? $this->response->get_body() : [],
			],
			[
				'type'    => [ 'provider', 'error' ],
				'parent'  => $this->entry_id,
				'form_id' => (int) ( $this->form_data['id'] ?? 0 ),
			]
		);
	}
}
