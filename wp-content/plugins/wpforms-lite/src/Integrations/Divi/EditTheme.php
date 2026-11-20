<?php

namespace WPForms\Integrations\Divi;

use WP_Post;
use WPForms\Frontend\EmbedContext;
use WPForms\Frontend\ThemesEditor;
use WPForms\Frontend\ThemesEditor\Support;

/**
 * The "Edit Theme" entry of the Divi modules: localized data, Divi 4 fields, preview host.
 *
 * The button opens the host page with the panel on `shortcode:{post}:{form}`; the host is the
 * page the Visual Builder runs on.
 *
 * @since 2.0.5
 */
class EditTheme {

	/**
	 * Get the launch data for the Visual Builder of the current page.
	 *
	 * @since 2.0.5
	 *
	 * @param array $forms Forms of the module's select, WP_Post objects.
	 *
	 * @return array Launch data plus `postId`, `pageUrl`, `isThemeBuilder`, `leadForms`, `noAccessForms`.
	 */
	public function get_data( array $forms ): array {

		$editor  = wpforms()->obj( 'themes_editor' );
		$post_id = (int) get_queried_object_id();
		$data    = $editor instanceof ThemesEditor ? $editor->get_support()->get_editor_launch_data( 'Divi Settings' ) : Support::get_unavailable_launch_data();

		return $data + [
			'postId'         => $post_id,
			'pageUrl'        => $post_id ? (string) get_permalink( $post_id ) : '',
			// A Theme Builder layout renders with the page as host, which the finder cannot resolve yet.
			'isThemeBuilder' => ! empty( $_GET['et_tb'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'leadForms'      => $this->get_lead_form_ids( $forms, $editor ),
			'noAccessForms'  => $this->get_no_access_form_ids( $forms, $editor ),
		];
	}

	/**
	 * Get the ids of the forms in Lead Form Mode; the editor does not style those.
	 *
	 * @since 2.0.5
	 *
	 * @param array             $forms  Forms, WP_Post objects.
	 * @param ThemesEditor|null $editor Editor.
	 *
	 * @return int[]
	 */
	private function get_lead_form_ids( array $forms, ?ThemesEditor $editor ): array {

		// Without the addon no form is a lead form: skip decoding every form of the site.
		if ( ! $editor instanceof ThemesEditor || ! $editor->get_support()->is_lead_forms_active() ) {
			return [];
		}

		$ids = [];

		foreach ( $forms as $form ) {
			if ( $form instanceof WP_Post && $editor->get_support()->is_unstyled_lead_form_post( $form ) ) {
				$ids[] = (int) $form->ID;
			}
		}

		return $ids;
	}

	/**
	 * Get the ids of the forms the user cannot edit; the editor does not open those.
	 *
	 * Per user, so the scripts read them: Divi 4 caches the field declarations across users.
	 *
	 * @since 2.0.5
	 *
	 * @param array             $forms  Forms, WP_Post objects.
	 * @param ThemesEditor|null $editor Editor.
	 *
	 * @return int[]
	 */
	private function get_no_access_form_ids( array $forms, ?ThemesEditor $editor ): array {

		if ( ! $editor instanceof ThemesEditor ) {
			return [];
		}

		$ids = [];

		foreach ( $forms as $form ) {
			if ( $form instanceof WP_Post && ! $editor->can_edit( (int) $form->ID ) ) {
				$ids[] = (int) $form->ID;
			}
		}

		return $ids;
	}

	/**
	 * Get the Divi 4 fields of the Form Theme toggle: the sentence and the button, or the notice explaining why there is none.
	 *
	 * The button is the `wpforms_edit_theme` field type the module script registers with Divi,
	 * which renders it with the module's settings. Only site-wide facts decide here (support,
	 * lead forms); the page is the script's business.
	 *
	 * @since 2.0.5
	 *
	 * @param array $data Launch data from `get_data()`.
	 *
	 * @return array
	 */
	public function get_fields( array $data ): array {

		$strings = $this->get_strings( $data );

		// Divi builds and caches these declarations in AJAX requests, which know no page: the script decides at click time whether there is one.
		$lead_forms = array_map( 'strval', (array) ( $data['leadForms'] ?? [] ) );
		$fields     = [];

		// The lead form notice comes first, as in the block: modern markup would not make a lead form editable.
		if ( $lead_forms ) {
			$fields['form_theme_lead_form'] = $this->get_notice_field(
				sprintf( '<strong>%1$s</strong> %2$s', esc_html( $strings['lead_forms_notice_head'] ), esc_html( $strings['lead_forms_notice_text'] ) )
			) + [ 'show_if' => [ 'form_id' => $lead_forms ] ];
		}

		if ( empty( $data['supported'] ) ) {
			// Hidden on "Select form" (0) and on a lead form, as the Divi 5 group is.
			$fields['form_theme_unsupported'] = $this->get_unsupported_field( $data, $strings ) + [ 'show_if_not' => [ 'form_id' => array_merge( [ '0' ], $lead_forms ) ] ];

			return $fields;
		}

		// Hidden on "Select form" (0) and on a lead form, as the Divi 5 group is.
		$hidden_for = [ 'form_id' => array_merge( [ '0' ], $lead_forms ) ];

		$fields['form_theme'] = $this->get_notice_field( esc_html( $strings['description'] ) . ' ' . esc_html( $strings['save_hint'] ) ) + [ 'show_if_not' => $hidden_for ];

		// The button is a field type the module script registers; Divi renders it with the module's settings.
		$fields['form_theme_button'] = [
			'label'           => '',
			'type'            => 'wpforms_edit_theme',
			'option_category' => 'basic_option',
			'toggle_slug'     => 'form_theme',
			'show_if_not'     => $hidden_for,
		];

		return $fields;
	}

	/**
	 * Get the launch strings with every key the fields read present.
	 *
	 * @since 2.0.5
	 *
	 * @param array $data Launch data.
	 *
	 * @return array
	 */
	private function get_strings( array $data ): array {

		$keys = [ 'description', 'save_hint', 'button', 'lead_forms_notice_head', 'lead_forms_notice_text', 'unsupported_notice_head', 'unsupported_notice_text', 'unsupported_notice_link', 'learn_more' ];

		return array_merge( array_fill_keys( $keys, '' ), (array) ( $data['strings'] ?? [] ) );
	}

	/**
	 * Get the notice field of an unsupported site: the reason, or the generic text when a filter turned the editor off.
	 *
	 * @since 2.0.5
	 *
	 * @param array $data    Launch data.
	 * @param array $strings Launch strings.
	 *
	 * @return array
	 */
	private function get_unsupported_field( array $data, array $strings ): array {

		$reason = ! empty( $data['reason'] ) ? $data['reason'] : $strings['unsupported_notice_text'];

		return $this->get_notice_field(
			sprintf(
				'<strong>%1$s</strong> %2$s <a href="%3$s" class="wpforms-divi-notice-link" target="_blank" rel="noopener noreferrer">%4$s</a>',
				esc_html( $strings['unsupported_notice_head'] ),
				esc_html( $reason ),
				esc_url( $strings['unsupported_notice_link'] ),
				esc_html( $strings['learn_more'] )
			)
		);
	}

	/**
	 * Get a Divi 4 field that shows a message and stores nothing.
	 *
	 * @since 2.0.5
	 *
	 * @param string $message Message, escaped HTML.
	 *
	 * @return array
	 */
	private function get_notice_field( string $message ): array {

		return [
			'label'           => '',
			'type'            => 'warning',
			'value'           => true,
			'display_if'      => true,
			'message'         => $message,
			'option_category' => 'basic_option',
			'toggle_slug'     => 'form_theme',
		];
	}

	/**
	 * Render the next form under the given post as host, so the preview prints the page's override.
	 *
	 * The Visual Builder preview is an admin request: without this the host is `0` and no
	 * override matches.
	 *
	 * @since 2.0.5
	 *
	 * @param int $post_id Post the builder edits.
	 *
	 * @return void
	 */
	public function use_preview_host( int $post_id ): void { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		add_filter(
			'wpforms_frontend_embed_context_take',
			static function ( $context ) use ( $post_id ) {

				$context         = (array) $context;
				$context['type'] = EmbedContext::TYPE_DIVI;
				$context['host'] = (string) $post_id;

				return $context;
			}
		);
	}
}
