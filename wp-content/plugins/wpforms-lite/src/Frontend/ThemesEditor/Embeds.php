<?php

namespace WPForms\Frontend\ThemesEditor;

use WPForms\Frontend\EmbedContext;
use WPForms\Frontend\Themes\Schema;
use WPForms\Frontend\ThemesEditor;

/**
 * Rendered embeds the current user can edit, collected while the page renders.
 *
 * @since 2.0.5
 */
class Embeds {

	/**
	 * Reasons an override is refused: a malformed key, a host the user cannot edit, a form not embedded there.
	 *
	 * @since 2.0.5
	 */
	const ERROR_INVALID   = 'invalid';
	const ERROR_FORBIDDEN = 'forbidden';
	const ERROR_NOT_FOUND = 'not_found';

	/**
	 * Editor.
	 *
	 * @since 2.0.5
	 *
	 * @var ThemesEditor
	 */
	private $editor;

	/**
	 * Embeds keyed by embed key, in render order.
	 *
	 * On the form's own page the key is the form ID and the context is empty.
	 *
	 * @since 2.0.5
	 *
	 * @var array Key => [ 'form_data' => array, 'context' => array ].
	 */
	private $embeds = [];

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
	 * Collect an editable rendered form under its embed key and enqueue the editor assets.
	 *
	 * @since 2.0.5
	 *
	 * @param array|mixed $form_data Form data.
	 *
	 * @return void
	 */
	public function collect_form( $form_data ): void {

		$form_data     = (array) $form_data;
		$form_id       = (int) ( $form_data['id'] ?? 0 );
		$embed_context = wpforms()->obj( 'embed_context' );
		$context       = $embed_context instanceof EmbedContext ? $embed_context->current() : [];
		$key           = (string) ( $context['key'] ?? $form_id );

		if ( ! $form_id || isset( $this->embeds[ $key ] ) ) {
			return;
		}

		if ( ! $this->editor->is_form_supported( $form_data ) || ! $this->editor->can_edit( $form_id ) ) {
			return;
		}

		// An embed whose override the save would refuse is not offered; the form's own page edits the default.
		if ( ! empty( $context['key'] ) && ! $this->can_override( (string) $context['key'], $form_id ) ) {
			return;
		}

		$this->embeds[ $key ] = [
			'form_data' => $form_data,
			'context'   => $context,
		];

		$this->editor->enqueue_assets();

		$schema = wpforms()->obj( 'themes_schema' );

		// The media modal is heavy: only for users who can pick a background image here.
		if ( $schema instanceof Schema && current_user_can( 'upload_files' ) && $schema->can_edit( 'backgroundUrl', $this->editor->get_context( $form_data ) ) ) {
			wp_enqueue_media();
		}
	}

	/**
	 * Whether the current user may save an override for an embed: the host is editable and the form is in its content.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key     Embed key.
	 * @param int    $form_id Form ID.
	 *
	 * @return bool
	 */
	public function can_override( string $key, int $form_id ): bool {

		return $this->get_override_error( $key, $form_id ) === '';
	}

	/**
	 * Why an override of an embed is refused, for the panel and the save route alike.
	 *
	 * The checks run on the parsed key, not on the render context: a Divi module
	 * renders under a shortcode key, and the save route only has the key.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key     Embed key.
	 * @param int    $form_id Form ID.
	 *
	 * @return string One of the `ERROR_*` constants; '' when the override may be saved.
	 */
	public function get_override_error( string $key, int $form_id ): string {

		$embed_context = wpforms()->obj( 'embed_context' );
		$finder        = wpforms()->obj( 'themes_editor_embed_finder' );
		$embed         = $embed_context instanceof EmbedContext ? $embed_context->parse( $key ) : null;

		if ( ! $embed || ! $finder instanceof EmbedFinder ) {
			return self::ERROR_INVALID;
		}

		if ( ! $embed_context->can_edit( $embed ) ) {
			return self::ERROR_FORBIDDEN;
		}

		return $finder->has( $embed, $form_id ) ? '' : self::ERROR_NOT_FOUND;
	}

	/**
	 * Get the collected embeds.
	 *
	 * @since 2.0.5
	 *
	 * @return array Embed key => [ 'form_data', 'context' ], in render order.
	 */
	public function all(): array {

		return $this->embeds;
	}

	/**
	 * Get the collected forms: the first embed of each form.
	 *
	 * @since 2.0.5
	 *
	 * @return array Form ID => form data.
	 */
	public function get_forms(): array {

		$forms = [];

		foreach ( $this->embeds as $embed ) {
			$form_id = (int) ( $embed['form_data']['id'] ?? 0 );

			$forms[ $form_id ] = $forms[ $form_id ] ?? $embed['form_data'];
		}

		return $forms;
	}
}
