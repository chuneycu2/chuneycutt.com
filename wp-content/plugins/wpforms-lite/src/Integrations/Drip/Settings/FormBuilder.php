<?php

namespace WPForms\Integrations\Drip\Settings;

use RuntimeException;
use WPForms\Integrations\Drip\Account;
use WPForms\Integrations\Drip\Api\Client;
use WPForms\Integrations\Drip\Helpers;
use WPForms\Providers\Provider\Settings\FormBuilder as FormBuilderAbstract;
use WPForms\Providers\Provider\Status;

/**
 * Drip in the Form Builder.
 *
 * @since 2.0.5
 */
class FormBuilder extends FormBuilderAbstract {

	/**
	 * Builder templates rendered from the templates directory.
	 *
	 * @since 2.0.5
	 */
	private const TEMPLATES = [
		'connection',
		'events',
		'subscriber-subscribe',
		'subscriber-unsubscribe',
		'subscriber-delete',
		'campaigns-add',
		'campaigns-delete',
	];

	/**
	 * Drip sign-up page offered when no Drip account is connected.
	 *
	 * @since 2.0.5
	 */
	private const SIGN_UP_URL = 'https://wpforms.com/drip-signup';

	/**
	 * Register all hooks.
	 *
	 * @since 2.0.5
	 */
	protected function init_hooks() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		parent::init_hooks();

		add_filter( 'wpforms_save_form_args', [ $this, 'save_form' ], 11, 3 );
		add_filter( 'wpforms_builder_save_form_response_data', [ $this, 'refresh_connections' ], 10, 3 );

		$slug = $this->core->slug;

		add_filter( "wpforms_providers_provider_settings_formbuilder_display_content_default_screen_{$slug}", [ $this, 'builder_settings_default_content' ] );

		add_filter( "wpforms_providers_settings_builder_ajax_connections_get_{$slug}", [ $this, 'ajax_connections_get' ] );
		add_filter( "wpforms_providers_settings_builder_ajax_accounts_get_{$slug}", [ $this, 'ajax_accounts_get' ] );
		add_filter( "wpforms_providers_settings_builder_ajax_events_get_{$slug}", [ $this, 'ajax_events_get' ] );
		add_filter( "wpforms_providers_settings_builder_ajax_campaigns_get_{$slug}", [ $this, 'ajax_campaigns_get' ] );
		add_filter( "wpforms_providers_settings_builder_ajax_account_template_get_{$slug}", [ $this, 'ajax_account_template_get' ] );
		add_filter( "wpforms_providers_settings_builder_ajax_account_save_{$slug}", [ $this, 'ajax_account_save' ] );
	}

	/**
	 * Enqueue the builder script.
	 *
	 * @since 2.0.5
	 */
	public function enqueue_assets() {

		parent::enqueue_assets();

		$min = wpforms_get_min_suffix();

		wp_enqueue_script(
			'wpforms-drip-builder',
			WPFORMS_PLUGIN_URL . "assets/js/integrations/drip/builder{$min}.js",
			[ 'underscore', 'wpforms-admin-builder-providers' ],
			WPFORMS_VERSION,
			true
		);

		wp_localize_script(
			'wpforms-drip-builder',
			'wpformsDripBuilderVars',
			[
				'l10n' => [
					'custom_field_value'       => esc_html__( 'Custom Value', 'wpforms-lite' ),
					'custom_field_placeholder' => esc_html__( 'Field Name', 'wpforms-lite' ),
				],
			]
		);
	}

	/**
	 * Pre-process the provider data before the form is saved.
	 *
	 * @since 2.0.5
	 *
	 * @param array|mixed $form Form array which is usable with `wp_update_post()`.
	 * @param array       $data Data retrieved from $_POST and processed.
	 * @param array       $args Update form arguments.
	 *
	 * @return array
	 * @noinspection PhpMissingParamTypeInspection
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function save_form( $form, $data, $args ): array {

		$form      = (array) $form;
		$form_data = json_decode( stripslashes( $form['post_content'] ), true );

		if ( ! empty( $form_data['providers'][ $this->core->slug ] ) ) {
			$modified_form_data = $this->modify_form_data( $form_data );

			if ( ! empty( $modified_form_data ) ) {
				$form['post_content'] = wpforms_encode( $modified_form_data );

				return $form;
			}
		}

		// The connections were locked (saved before they loaded) or the save came from another panel:
		// keep the connections the form already had.
		$form_handler = wpforms()->obj( 'form' );
		$prev_form    = ! empty( $data['id'] ) && $form_handler ? $form_handler->get( $data['id'], [ 'content_only' => true ] ) : [];

		if ( ! empty( $prev_form['providers'][ $this->core->slug ] ) ) {
			$form_data['providers']                      = (array) ( $form_data['providers'] ?? [] );
			$form_data['providers'][ $this->core->slug ] = $prev_form['providers'][ $this->core->slug ];
			$form['post_content']                        = wpforms_encode( $form_data );
		}

		return $form;
	}

	/**
	 * Normalise the connections before they are saved, unless they are locked.
	 *
	 * @since 2.0.5
	 *
	 * @param array $form_data Form content.
	 *
	 * @return array|null Null when the connections are locked.
	 */
	private function modify_form_data( array $form_data ): ?array {

		$lock = '__lock__';

		// The user saved while the connections were still loading, so the DOM did not carry them.
		if ( absint( $form_data['providers'][ $this->core->slug ][ $lock ] ?? 0 ) === 1 ) {
			return null;
		}

		unset( $form_data['providers'][ $this->core->slug ][ $lock ] );

		foreach ( $form_data['providers'][ $this->core->slug ] as $connection_id => $connection ) {
			$connection['id'] = $connection_id;

			if ( ! empty( $connection['action'] ) ) {
				$connection = $this->sanitize_connection( (array) $connection );
			}

			$form_data['providers'][ $this->core->slug ][ $connection_id ] = $connection;
		}

		return $form_data;
	}

	/**
	 * Normalise one connection.
	 *
	 * @since 2.0.5
	 *
	 * @param array $connection Connection data.
	 *
	 * @return array
	 */
	private function sanitize_connection( array $connection ): array {

		$this->sanitize_connection_fields_meta( $connection );
		$this->sanitize_connection_conditionals( $connection );

		if ( isset( $connection['base_lead_score'] ) ) {
			$connection['base_lead_score'] = (int) $connection['base_lead_score'];
		}

		if ( ! empty( $connection['fields_meta'] ) ) {
			$connection['fields_meta'] = $this->sanitize_fields_meta( $connection['fields_meta'] );
		}

		foreach ( [ 'add', 'delete' ] as $tags_key ) {
			if ( isset( $connection['tags'][ $tags_key ] ) && ! is_array( $connection['tags'][ $tags_key ] ) ) {
				$connection['tags'][ $tags_key ] = array_map( 'sanitize_text_field', explode( ',', $connection['tags'][ $tags_key ] ) );
			}
		}

		if ( $connection['action'] === 'event' ) {
			$connection = $this->sanitize_event( $connection );
		}

		if ( $connection['action'] === 'campaign_sub' && ! empty( $connection['campaign_id'] ) ) {
			$connection['campaign_id'] = (int) $connection['campaign_id'];
		}

		return $connection;
	}

	/**
	 * Keep the custom fields Drip can store: a valid identifier mapped to a form field or sub-field.
	 *
	 * @since 2.0.5
	 *
	 * @param array $fields_meta Mapped custom fields, already reduced to non-empty name and field id pairs.
	 *
	 * @return array
	 */
	private function sanitize_fields_meta( array $fields_meta ): array {

		$sanitized = [];

		foreach ( $fields_meta as $property ) {
			// `custom` is the select option that reveals the free-text input, never a field name.
			if ( $property['name'] === 'custom' ) {
				continue;
			}

			$name = Helpers::sanitize_field_id( $property['name'] );

			if ( $name === '' ) {
				continue;
			}

			// A plain id maps the whole field, `1.first` maps a sub-field.
			$sanitized[] = [
				'name'     => $name,
				'field_id' => is_numeric( $property['field_id'] ) ? (int) $property['field_id'] : wpforms_sanitize_key( $property['field_id'] ),
			];
		}

		return $sanitized;
	}

	/**
	 * A new event name typed by the user replaces the selected one.
	 *
	 * @since 2.0.5
	 *
	 * @param array $connection Connection data.
	 *
	 * @return array
	 */
	private function sanitize_event( array $connection ): array {

		if ( ! empty( $connection['events']['name'] ) ) {
			$connection['events']['name'] = sanitize_text_field( $connection['events']['name'] );
		}

		if ( ! empty( $connection['events']['new'] ) ) {
			$connection['events']['name'] = ucfirst( sanitize_text_field( $connection['events']['new'] ) );
		}

		unset( $connection['events']['new'] );

		return $connection;
	}

	/**
	 * Hand the saved connections back to the builder after a save, so a reload is not needed.
	 *
	 * @since 2.0.5
	 *
	 * @param array|mixed $response_data Save response data.
	 * @param int         $form_id       Form ID.
	 * @param array       $data          Saved form data.
	 *
	 * @return array
	 */
	public function refresh_connections( $response_data, int $form_id, array $data ): array {

		$response_data = (array) $response_data;

		if ( empty( $data['providers'][ $this->core->slug ] ) ) {
			return $response_data;
		}

		$form_handler = wpforms()->obj( 'form' );

		$this->form_data = $form_handler ? (array) $form_handler->get( $form_id, [ 'content_only' => true ] ) : [];

		$response_data[ $this->core->slug ] = $this->ajax_connections_get();

		return $response_data;
	}

	/**
	 * The saved connections, newest first, with their conditional logic, the Drip fields and the accounts.
	 *
	 * @since 2.0.5
	 *
	 * @return array
	 */
	public function ajax_connections_get(): array {

		$data = [
			'connections'     => isset( $this->form_data['providers'][ $this->core->slug ] )
				? array_reverse( (array) $this->form_data['providers'][ $this->core->slug ], true )
				: [],
			'fields_meta'     => Helpers::get_predefined_custom_fields(),
			'conditionals'    => [],
			// The block of a connection that is not saved yet; the builder script fills the id in.
			'conditional_new' => $this->get_conditional_block( '%connection_id%' ),
		];

		foreach ( $data['connections'] as $connection ) {
			if ( empty( $connection['id'] ) ) {
				continue;
			}

			$data['conditionals'][ $connection['id'] ] = $this->get_conditional_block( (string) $connection['id'] );
		}

		return array_merge( $data, $this->ajax_accounts_get() );
	}

	/**
	 * The conditional logic block of a connection.
	 *
	 * Lite has no rules engine, so it gets the disabled toggle that opens the upgrade modal,
	 * the way the payment gateways show it.
	 *
	 * @since 2.0.5
	 *
	 * @param string $connection_id Connection ID, or the placeholder of a connection not saved yet.
	 *
	 * @return string
	 */
	private function get_conditional_block( string $connection_id ): string {

		if ( wpforms()->is_pro() ) {
			return (string) wpforms_conditional_logic()->builder_block(
				[
					'form'       => $this->form_data,
					'type'       => 'panel',
					'parent'     => 'providers',
					'panel'      => $this->core->slug,
					'subsection' => $connection_id,
				],
				false
			);
		}

		$toggle = (string) wpforms_panel_field(
			'toggle',
			$this->core->slug,
			'conditional_logic',
			$this->form_data,
			esc_html__( 'Enable Conditional Logic', 'wpforms-lite' ),
			[
				'parent'      => 'providers',
				'subsection'  => $connection_id,
				// The education script turns the click into the upgrade modal; a disabled input would swallow it.
				'input_class' => 'education-modal',
				'pro_badge'   => true,
				// Rules saved on a paid plan stay in the form, but the toggle must not show as on.
				'value'       => '',
				'data'        => [
					'action'      => 'upgrade',
					'name'        => esc_html__( 'Smart Conditional Logic', 'wpforms-lite' ),
					'utm-content' => 'Builder Drip Conditional Logic',
					'license'     => 'pro',
				],
			],
			false
		);

		// The wrapper the rules block has on Pro, so the connection styles space it the same way.
		return '<div class="wpforms-conditional-block wpforms-conditional-block-panel" data-type="panel">' . $toggle . '</div>';
	}

	/**
	 * The Drip accounts reachable with each stored token.
	 *
	 * @since 2.0.5
	 *
	 * @return array
	 */
	public function ajax_accounts_get(): array {

		$data = [ 'accounts' => [] ];

		foreach ( (array) wpforms_get_providers_options( $this->core->slug ) as $option_id => $option ) {
			if ( empty( $option['api_token'] ) ) {
				continue;
			}

			$response = ( new Client( (string) $option['api_token'] ) )->get( 'accounts' );

			if ( $response->has_errors() ) {
				continue;
			}

			$data['accounts'][ $option_id ] = $response->get( 'accounts' );
		}

		return $data;
	}

	/**
	 * The custom events of the connection's account, with the saved event listed before Drip knows it.
	 *
	 * @since 2.0.5
	 *
	 * @return array|null Null when the request is incomplete or Drip rejected it.
	 */
	public function ajax_events_get(): ?array {

		$request = $this->get_connection_request();

		if ( ! $request ) {
			return null;
		}

		$saved_event = $this->form_data['providers'][ $this->core->slug ][ $request['connection_id'] ]['events']['name'] ?? '';
		$events      = $saved_event !== '' ? [ $saved_event ] : [];
		$response    = $request['client']->get( 'event_actions' );

		if ( $response->has_errors() ) {
			return $events ? [ 'events' => [ $request['connection_id'] => $events ] ] : null;
		}

		$events = array_values( array_unique( array_merge( $events, (array) $response->get( 'event_actions' ) ) ) );

		return [ 'events' => [ $request['connection_id'] => $events ] ];
	}

	/**
	 * The Email Series campaigns of the connection's account.
	 *
	 * @since 2.0.5
	 *
	 * @return array|null Null when the request is incomplete or Drip rejected it.
	 */
	public function ajax_campaigns_get(): ?array {

		$request = $this->get_connection_request();

		if ( ! $request ) {
			return null;
		}

		$response = $request['client']->get(
			'campaigns',
			[
				'status'    => 'all',
				'sort'      => 'name',
				'direction' => 'asc',
			]
		);

		if ( $response->has_errors() ) {
			return null;
		}

		return [ 'campaigns' => [ $request['connection_id'] => $response->get( 'campaigns' ) ] ];
	}

	/**
	 * The connection the builder asks data for, with an API client bound to its account.
	 *
	 * @since 2.0.5
	 *
	 * @return array Empty when the request lacks the account, the token key or the connection id.
	 */
	private function get_connection_request(): array {

		// The shared providers AJAX entry point verified the builder nonce before dispatching this task.
		$account_id    = isset( $_POST['connection_account_id'] ) ? absint( $_POST['connection_account_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$option_id     = isset( $_POST['connection_option_id'] ) ? sanitize_key( $_POST['connection_option_id'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$connection_id = isset( $_POST['connection_id'] ) ? sanitize_key( $_POST['connection_id'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$options       = (array) wpforms_get_providers_options( $this->core->slug );

		if ( ! $account_id || $option_id === '' || $connection_id === '' || empty( $options[ $option_id ]['api_token'] ) ) {
			return [];
		}

		return [
			'connection_id' => $connection_id,
			'client'        => new Client( (string) $options[ $option_id ]['api_token'], (string) $account_id ),
		];
	}

	/**
	 * The Add New Account modal content.
	 *
	 * @since 2.0.5
	 *
	 * @return array
	 */
	public function ajax_account_template_get(): array {

		return [
			'title'   => esc_html__( 'New Drip Account', 'wpforms-lite' ),
			'content' => Account::get_fields_html(),
			'type'    => 'blue',
		];
	}

	/**
	 * Connect a new account from the builder.
	 *
	 * @since 2.0.5
	 *
	 * @return array Empty on success, otherwise the error message under `error`.
	 */
	public function ajax_account_save(): array {

		// The shared providers AJAX entry point verified the builder nonce before dispatching this task.
		$name  = isset( $_POST['acc_name'] ) ? sanitize_text_field( wp_unslash( $_POST['acc_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$token = isset( $_POST['acc_token'] ) ? sanitize_text_field( wp_unslash( $_POST['acc_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		try {
			( new Account() )->connect( $token, $name );
		} catch ( RuntimeException $e ) {
			return [ 'error' => $e->getMessage() ];
		}

		return [];
	}

	/**
	 * Display Drip in the Marketing panel sidebar with the Recommended badge,
	 * using the same item markup as the recommended payment integrations.
	 *
	 * @since 2.0.5
	 */
	public function display_sidebar() {

		$form_id = $this->form_data['id'] ?? 0;

		echo wpforms_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'builder/payment/sidebar',
			[
				'configured'  => ! empty( $form_id ) && Status::init( $this->core->slug )->is_ready( $form_id ) ? 'configured' : '',
				'slug'        => $this->core->slug,
				'icon'        => $this->core->icon,
				'name'        => $this->core->name,
				'recommended' => true,
			],
			true
		);
	}

	/**
	 * Offer a free Drip account on the provider default screen while no account is connected,
	 * the way Constant Contact does.
	 *
	 * @since 2.0.5
	 *
	 * @param string|mixed $content Default screen content.
	 *
	 * @return string
	 */
	public function builder_settings_default_content( $content ): string {

		$content = (string) $content;

		if ( ! empty( wpforms_get_providers_options( $this->core->slug ) ) ) {
			return $content;
		}

		return $content . sprintf(
			'<p class="wpforms-builder-drip-provider-sign-up"><a href="%1$s" class="wpforms-btn wpforms-btn-md wpforms-btn-orange" target="_blank" rel="noopener noreferrer">%2$s</a></p>',
			esc_url( self::SIGN_UP_URL ),
			esc_html__( 'Try Drip for Free', 'wpforms-lite' )
		);
	}

	/**
	 * Print the Underscore templates of the connection blocks.
	 *
	 * @since 2.0.5
	 */
	public function builder_custom_templates() {

		$args = [
			'slug'                 => $this->core->slug,
			'email_field_html'     => $this->get_email_field_html( 'email' ),
			'new_email_field_html' => $this->get_email_field_html( 'new_email' ),
		];

		foreach ( self::TEMPLATES as $template ) {
			?>
			<script type="text/html" id="tmpl-wpforms-<?php echo esc_attr( $this->core->slug ); ?>-builder-content-connection<?php echo $template === 'connection' ? '' : '-' . esc_attr( $template ); ?>">
				<?php echo wpforms_render( 'integrations/drip/builder/' . $template, $args, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</script>
			<?php
		}
	}

	/**
	 * A form-field select mapped to the subscriber email or the new email.
	 *
	 * @since 2.0.5
	 *
	 * @param string $field Either `email` or `new_email`.
	 *
	 * @return string
	 */
	private function get_email_field_html( string $field ): string {

		$is_main = $field === 'email';

		$args = [
			'parent'      => 'providers',
			// The connection id placeholder nests the field under `[fields]`; the builder script replaces it.
			'subsection'  => '%connection_id%][fields',
			'field_map'   => [ 'email' ],
			'placeholder' => $is_main
				? esc_html__( '--- Select Email Field ---', 'wpforms-lite' )
				: esc_html__( '--- Select New Email Field ---', 'wpforms-lite' ),
			'input_id'    => 'wpforms-panel-field-' . $this->core->slug . '-%connection_id%-' . ( $is_main ? 'email' : 'email-new' ),
		];

		if ( $is_main ) {
			$args['after_tooltip'] = '<span class="required">*</span>';
			$args['input_class']   = 'wpforms-required';
		}

		return (string) wpforms_panel_field(
			'select',
			$this->core->slug,
			$field,
			$this->form_data,
			$is_main ? esc_html__( 'Subscriber Email', 'wpforms-lite' ) : esc_html__( 'New Subscriber Email', 'wpforms-lite' ),
			$args,
			false
		);
	}
}
