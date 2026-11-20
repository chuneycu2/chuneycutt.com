<?php

namespace WPForms\Frontend\ThemesEditor;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WPForms\Frontend\Themes\Schema;
use WPForms\Frontend\Themes\SettingsSanitizer;
use WPForms\Forms\Locator;
use WPForms\Frontend\ThemesEditor;

/**
 * REST routes of the front-end themes editor.
 *
 * @since 2.0.5
 */
class RestApi {

	/**
	 * REST namespace.
	 *
	 * @since 2.0.5
	 *
	 * @var string
	 */
	private const ROUTE_NAMESPACE = 'wpforms/v1';

	/**
	 * Most forms one save may carry; a page holds a handful, a busy one about twenty.
	 *
	 * @since 2.0.5
	 *
	 * @var int
	 */
	private const MAX_FORMS = 50;

	/**
	 * Editor.
	 *
	 * @since 2.0.5
	 *
	 * @var ThemesEditor
	 */
	private $editor;

	/**
	 * Constructor.
	 *
	 * @since 2.0.5
	 *
	 * @param ThemesEditor $editor Editor.
	 */
	public function __construct( ThemesEditor $editor ) {

		$this->editor = $editor;
	}

	/**
	 * Register the routes.
	 *
	 * @since 2.0.5
	 */
	public function register_routes(): void {

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/themes-editor/badge/',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'record_badge' ],
				'permission_callback' => [ $this, 'badge_permissions_check' ],
			]
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/themes-editor/save/',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'save' ],
				'permission_callback' => [ $this, 'save_permissions_check' ],
				'args'                => [
					'forms' => [
						'type'              => 'array',
						'required'          => true,
						'validate_callback' => [ $this, 'validate_forms' ],
					],
				],
			]
		);
	}

	/**
	 * Allow logged-in users only.
	 *
	 * @since 2.0.5
	 *
	 * @return true|WP_Error
	 */
	private function logged_in_permissions_check() {

		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', esc_html__( 'This route is private.', 'wpforms-lite' ), [ 'status' => 401 ] );
		}

		return true;
	}

	/**
	 * Allow the badge click of users who get the admin bar item, the ones who can edit forms.
	 *
	 * @since 2.0.5
	 *
	 * @return true|WP_Error
	 */
	public function badge_permissions_check() {

		$logged_in = $this->logged_in_permissions_check();

		if ( $logged_in !== true ) {
			return $logged_in;
		}

		if ( ! wpforms_current_user_can( 'edit_forms' ) ) {
			return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot edit forms.', 'wpforms-lite' ), [ 'status' => 403 ] );
		}

		return true;
	}

	/**
	 * Record the first admin bar click.
	 *
	 * @since 2.0.5
	 *
	 * @return WP_REST_Response
	 */
	public function record_badge(): WP_REST_Response {

		$this->editor->get_admin_bar()->record_click( get_current_user_id() );

		return new WP_REST_Response( [ 'recorded' => true ], 200 );
	}

	/**
	 * Validate the forms payload: a non-empty list of `{ id, settings }`, each embed or form once.
	 *
	 * @since 2.0.5
	 *
	 * @param mixed $forms Forms payload.
	 *
	 * @return bool
	 */
	public function validate_forms( $forms ): bool {

		if ( ! is_array( $forms ) || empty( $forms ) || count( $forms ) > self::MAX_FORMS ) {
			return false;
		}

		$keys = [];

		foreach ( $forms as $form ) {
			if ( ! $this->is_valid_form_item( $form ) ) {
				return false;
			}

			// The panel sends every item once; a repeated one would be read and written again for one result.
			$key = ( $form['embed'] ?? '' ) !== '' ? $form['embed'] : 'form:' . absint( $form['id'] );

			if ( isset( $keys[ $key ] ) ) {
				return false;
			}

			$keys[ $key ] = true;
		}

		return true;
	}

	/**
	 * Whether one item of the forms payload is well-formed.
	 *
	 * @since 2.0.5
	 *
	 * @param mixed $form Item.
	 *
	 * @return bool
	 */
	private function is_valid_form_item( $form ): bool {

		if ( ! is_array( $form ) || absint( $form['id'] ?? 0 ) === 0 || ! is_array( $form['settings'] ?? null ) ) {
			return false;
		}

		if ( isset( $form['embed'] ) && ! is_string( $form['embed'] ) ) {
			return false;
		}

		return ! isset( $form['context'] ) || is_string( $form['context'] );
	}

	/**
	 * Allow the save when every form in the request is editable.
	 *
	 * @since 2.0.5
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return true|WP_Error
	 */
	public function save_permissions_check( WP_REST_Request $request ) {

		$logged_in = $this->logged_in_permissions_check();

		if ( $logged_in !== true ) {
			return $logged_in;
		}

		if ( ! $this->editor->is_supported() ) {
			return new WP_Error( 'wpforms_themes_unsupported', esc_html__( 'Themes are not supported with the current settings.', 'wpforms-lite' ), [ 'status' => 400 ] );
		}

		foreach ( $this->get_forms_param( $request ) as $form ) {
			if ( ! $this->editor->can_edit( $form['id'] ) ) {
				return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot edit one of these forms.', 'wpforms-lite' ), [ 'status' => 403 ] );
			}

			$form_data = $this->get_form_data( $form['id'] );

			// Checked before any write: a missing form must not leave the earlier forms saved.
			if ( empty( $form_data ) ) {
				return new WP_Error( 'rest_not_found', esc_html__( 'One of these forms does not exist.', 'wpforms-lite' ), [ 'status' => 404 ] );
			}

			if ( ! $this->editor->is_form_supported( $form_data ) ) {
				return new WP_Error( 'wpforms_themes_unsupported_form', esc_html__( 'Themes are not supported for one of these forms.', 'wpforms-lite' ), [ 'status' => 400 ] );
			}

			$embed_error = $this->check_embed( $form['embed'], $form['id'] );

			if ( $embed_error ) {
				return $embed_error;
			}
		}

		return true;
	}

	/**
	 * Check an item's embed key: well-formed, the host editable, the form embedded there.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key     Embed key; '' means the form default.
	 * @param int    $form_id Form ID.
	 *
	 * @return WP_Error|null
	 */
	private function check_embed( string $key, int $form_id ): ?WP_Error {

		if ( $key === '' ) {
			return null;
		}

		$error = $this->editor->get_embeds()->get_override_error( $key, $form_id );

		if ( $error === Embeds::ERROR_INVALID ) {
			return new WP_Error( 'wpforms_themes_invalid_embed', esc_html__( 'This embed is not valid.', 'wpforms-lite' ), [ 'status' => 400 ] );
		}

		if ( $error === Embeds::ERROR_FORBIDDEN ) {
			return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot edit the page of one of these embeds.', 'wpforms-lite' ), [ 'status' => 403 ] );
		}

		if ( $error === Embeds::ERROR_NOT_FOUND ) {
			return new WP_Error( 'rest_not_found', esc_html__( 'One of these forms is not embedded where the save says.', 'wpforms-lite' ), [ 'status' => 404 ] );
		}

		return null;
	}

	/**
	 * Get a stored form's data, empty for a missing form or a post of another type.
	 *
	 * @since 2.0.5
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array
	 */
	private function get_form_data( int $form_id ): array {

		$form_handler = wpforms()->obj( 'form' );
		$form_data    = $form_handler ? $form_handler->get( $form_id, [ 'content_only' => true ] ) : [];

		return is_array( $form_data ) ? $form_data : [];
	}

	/**
	 * Save the theme of every form in the request.
	 *
	 * @since 2.0.5
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function save( WP_REST_Request $request ): WP_REST_Response {

		$results = [];

		foreach ( $this->get_forms_param( $request ) as $form ) {
			$result_key = $form['embed'] !== '' ? $form['embed'] : $form['id'];
			$saved      = $form['embed'] !== ''
				? $this->save_embed( $form['embed'], $form['id'], $form['settings'], $form['context'], $form['block_css'] )
				: $this->save_form( $form['id'], $form['settings'], $form['context'] );

			$results[ $result_key ] = [ 'saved' => $saved ];

			if ( ! $saved ) {
				return new WP_REST_Response( [ 'forms' => $results ], 500 );
			}
		}

		return new WP_REST_Response( [ 'forms' => $results ], 200 );
	}

	/**
	 * Sanitize and write one form's theme.
	 *
	 * @since 2.0.5
	 *
	 * @param int    $form_id  Form ID.
	 * @param array  $settings Incoming theme settings.
	 * @param string $context  Surface context.
	 *
	 * @return bool
	 */
	private function save_form( int $form_id, array $settings, string $context ): bool {

		$form_data = $this->get_form_data( $form_id );

		if ( empty( $form_data ) ) {
			return false;
		}

		$sanitizer   = SettingsSanitizer::get_instance();
		$stored      = $this->editor->get_data()->get_stored_theme( $form_data, $context );
		$clean       = $sanitizer->sanitize( $settings, $stored, Schema::STORAGE_FORM, $context );
		$clean       = $this->drop_unknown_theme( $clean );
		$surface_key = Schema::get_surface_key( $context );
		$surface     = $sanitizer->drop_followed( $sanitizer->sanitize( $settings, (array) ( $form_data['settings'][ $surface_key ] ?? [] ), Schema::STORAGE_SURFACE, $context ), $clean, $context );
		$updates     = [
			'themes'                  => $clean,
			ThemesEditor::APPLIED_KEY => '1',
		];
		$schema      = wpforms()->obj( 'themes_schema' );
		$is_owner    = $schema instanceof Schema ? $schema->is_theme_owner( $context ) : $context !== Schema::CONTEXT_DEFAULT;

		if ( $is_owner ) {
			// A context owning its theme keeps it in its slot; the form default stays as it is.
			unset( $updates['themes'] );

			$updates[ $surface_key ] = $clean + $surface;
		} elseif ( $surface ) {
			// A context without surface controls sanitizes to nothing and keeps no slot.
			$updates[ $surface_key ] = $surface;
		}

		$result = wpforms()->obj( 'form' )->update_settings( $form_id, $updates, [ 'skip_revision' => true ] );

		if ( ! $result || is_wp_error( $result ) ) {
			return false;
		}

		$this->purge_form_locations( $form_id );

		/**
		 * Fires after the front-end themes editor saved a form's theme.
		 *
		 * @since 2.0.5
		 *
		 * @param int    $form_id  Form ID.
		 * @param array  $settings Sanitized theme settings.
		 * @param string $context  Surface context.
		 */
		do_action( 'wpforms_frontend_themes_editor_rest_api_save_form_after', $form_id, $clean, $context );

		return true;
	}

	/**
	 * Clean the post cache of every post embedding the form, when a site opts in.
	 *
	 * @since 2.0.5
	 *
	 * @param int $form_id Form ID.
	 */
	private function purge_form_locations( int $form_id ): void {

		/**
		 * Filter whether a form theme save cleans the post cache of every post embedding the form.
		 *
		 * Off by default: a form embedded on many pages would purge them all on each save.
		 * Most page cache plugins purge a page when its post cache is cleaned.
		 *
		 * @since 2.0.5
		 *
		 * @param bool $purge   Whether to clean the post cache.
		 * @param int  $form_id Form ID.
		 */
		if ( ! apply_filters( 'wpforms_frontend_themes_editor_rest_api_purge_form_locations', false, $form_id ) ) {
			return;
		}

		$locations = (array) get_post_meta( $form_id, Locator::LOCATIONS_META, true );

		// Widget locations carry string ids and have no post to clean.
		$post_ids = array_filter( array_map( 'absint', array_column( $locations, 'id' ) ) );

		foreach ( array_unique( $post_ids ) as $post_id ) {
			clean_post_cache( $post_id );
		}
	}

	/**
	 * Sanitize and write one embed's override.
	 *
	 * The stored fallback is the existing override, not the form: the override's
	 * Custom CSS is the embed's own, added to the form's, and a key the user may
	 * not edit keeps the override's value, '' on a first save.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key          Embed key.
	 * @param int    $form_id      Form ID.
	 * @param array  $settings     Incoming theme settings.
	 * @param string $context      Surface context.
	 * @param bool   $is_block_css Whether the field held the block's own Custom CSS.
	 *
	 * @return bool
	 */
	private function save_embed( string $key, int $form_id, array $settings, string $context, bool $is_block_css ): bool {

		$overrides = wpforms()->obj( 'themes_editor_overrides' );
		$stored    = (array) ( $overrides->get( $key )['settings'] ?? [] );
		$clean     = SettingsSanitizer::get_instance()->sanitize( $settings, $stored, Schema::STORAGE_FORM, $context );
		$clean     = $this->drop_unknown_theme( $clean );
		$schema    = wpforms()->obj( 'themes_schema' );

		// The panel showed a block's own Custom CSS in the field, so a save that could edit it now carries it.
		$owns_custom_css = array_key_exists( 'customCss', $settings ) && $schema instanceof Schema && $schema->can_edit( 'customCss', $context );

		if ( ! $overrides->set( $key, $form_id, $clean, $owns_custom_css, $is_block_css ) ) {
			return false;
		}

		/**
		 * Fires after the front-end themes editor saved an embed's override.
		 *
		 * @since 2.0.5
		 *
		 * @param string $key      Embed key.
		 * @param int    $form_id  Form ID.
		 * @param array  $settings Sanitized theme settings.
		 * @param string $context  Surface context.
		 */
		do_action( 'wpforms_frontend_themes_editor_rest_api_save_embed_after', $key, $form_id, $clean, $context );

		return true;
	}

	/**
	 * Drop a theme slug the site has no theme for.
	 *
	 * The panel writes a custom theme's fork before the form that selects it,
	 * but a non-admin cannot save custom themes, so their fork never reaches
	 * the server. Left standing, the dangling slug would style this form
	 * from whatever a later fork happens to save under the same name.
	 *
	 * @since 2.0.5
	 *
	 * @param array $settings Sanitized theme settings.
	 *
	 * @return array
	 */
	private function drop_unknown_theme( array $settings ): array {

		$slug = (string) ( $settings['wpformsTheme'] ?? '' );

		if ( $slug === '' || $this->editor->get_themes_data()->get_theme( $slug ) ) {
			return $settings;
		}

		$settings['wpformsTheme']  = '';
		$settings['isCustomTheme'] = '';

		return $settings;
	}

	/**
	 * Normalize the forms payload.
	 *
	 * The context is checked against the stored form, never taken from the request as is: its controls decide what the save may write.
	 *
	 * @since 2.0.5
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return array List of [ id, context, settings, embed, block_css ].
	 */
	private function get_forms_param( WP_REST_Request $request ): array {

		$forms = [];

		foreach ( (array) $request->get_param( 'forms' ) as $form ) {
			$form    = (array) $form;
			$form_id = absint( $form['id'] ?? 0 );
			$embed   = sanitize_text_field( (string) ( $form['embed'] ?? '' ) );

			$forms[] = [
				'id'        => $form_id,
				'context'   => $this->get_item_context( $form, $embed, $this->get_form_data( $form_id ) ),
				'settings'  => is_array( $form['settings'] ?? null ) ? $form['settings'] : [],
				'embed'     => $embed,
				'block_css' => ! empty( $form['blockCss'] ),
			];
		}

		return $forms;
	}

	/**
	 * Resolve the context an item is saved in.
	 *
	 * An embed is the form on someone else's page, so it is always `default`.
	 * A form default takes the context the panel sent when the form allows
	 * it, and the form-derived context when the item carries none, or one the
	 * form does not allow.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $form      Item of the forms payload.
	 * @param string $embed     Embed key, empty for a form default.
	 * @param array  $form_data Stored form data.
	 *
	 * @return string
	 */
	private function get_item_context( array $form, string $embed, array $form_data ): string {

		if ( $embed !== '' ) {
			return Schema::CONTEXT_DEFAULT;
		}

		$requested = sanitize_key( (string) ( $form['context'] ?? '' ) );

		if ( $requested !== '' && in_array( $requested, $this->editor->get_contexts( $form_data ), true ) ) {
			return $requested;
		}

		return $this->editor->get_context( $form_data );
	}
}
