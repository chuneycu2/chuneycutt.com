<?php

namespace WPForms\Frontend\ThemesEditor;

use WP_Post;
use WPForms\Frontend\ThemesEditor;

/**
 * Site and form checks behind whether the front-end themes editor can style forms.
 *
 * @since 2.0.5
 */
class Support {

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
	 * Whether the form runs in Lead Form Mode, which owns its own styling.
	 *
	 * The stored flag outlives the addon: a form keeps `lead_forms.enable`
	 * after Lead Forms is deactivated, or when it is imported on a site
	 * without the addon, and renders as a plain form. Only the active addon
	 * makes the flag mean anything.
	 *
	 * @since 2.0.5
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return bool
	 */
	public function is_lead_form( array $form_data ): bool {

		return $this->is_lead_forms_active() && ! empty( $form_data['settings']['lead_forms']['enable'] );
	}

	/**
	 * Whether a lead form keeps its own styling because no addon lets the editor style it.
	 *
	 * The Lead Forms notices ask this, so they go away once the addon lifts
	 * the exclusion through `wpforms_frontend_themes_editor_is_form_supported`.
	 *
	 * @since 2.0.5
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return bool
	 */
	public function is_unstyled_lead_form( array $form_data ): bool {

		return $this->is_lead_form( $form_data ) && ! $this->editor->is_form_supported( $form_data );
	}

	/**
	 * Whether a form post is a lead form the editor cannot style: the form list of a builder asks per post.
	 *
	 * @since 2.0.5
	 *
	 * @param WP_Post $form Form post.
	 *
	 * @return bool
	 */
	public function is_unstyled_lead_form_post( WP_Post $form ): bool {

		// Without the addon no form is a lead form: skip decoding the form.
		return $this->is_lead_forms_active() && $this->is_unstyled_lead_form( (array) wpforms_decode( (string) $form->post_content ) );
	}

	/**
	 * Whether the Lead Forms addon is active, the only state in which a form can be a lead form.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	public function is_lead_forms_active(): bool {

		return defined( 'WPFORMS_LEAD_FORMS_PATH' );
	}

	/**
	 * Whether the form runs as a Conversational Form, whose page core cannot style by itself.
	 *
	 * The addon version that bridges the theme variables lifts this through
	 * `wpforms_frontend_themes_editor_is_form_supported`; an older addon keeps the exclusion.
	 *
	 * @since 2.0.5
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return bool
	 */
	public function is_conversational_form( array $form_data ): bool {

		return $this->is_conversational_forms_active() && ! empty( $form_data['settings']['conversational_forms_enable'] );
	}

	/**
	 * Whether the Conversational Forms addon is active.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	public function is_conversational_forms_active(): bool {

		return defined( 'WPFORMS_CONVERSATIONAL_FORMS_PATH' );
	}

	/**
	 * Whether the form runs as a Form Page, whose page core cannot style by itself.
	 *
	 * The addon version that bridges the theme variables lifts this through
	 * `wpforms_frontend_themes_editor_is_form_supported`; an older addon keeps the exclusion.
	 *
	 * @since 2.0.5
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return bool
	 */
	public function is_form_page_form( array $form_data ): bool {

		return $this->is_form_pages_active() && ! empty( $form_data['settings']['form_pages_enable'] );
	}

	/**
	 * Whether the Form Pages addon is active.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	public function is_form_pages_active(): bool {

		return defined( 'WPFORMS_FORM_PAGES_PATH' );
	}

	/**
	 * Get the human-readable reason the editor is unsupported.
	 *
	 * @since 2.0.5
	 *
	 * @return string Empty when the editor is supported, or when no setting can fix it; the caller writes that copy.
	 */
	public function get_unsupported_reason(): string {

		if ( $this->editor->is_supported() ) {
			return '';
		}

		return $this->get_legacy_styling_reason();
	}

	/**
	 * Get the human-readable reason the site renders forms with legacy styling.
	 *
	 * Unlike {@see get_unsupported_reason()}, ignores the support filter, so
	 * integrations showing their own legacy-styling notices can rely on it.
	 *
	 * @since 2.0.5
	 *
	 * @return string Empty string when the site uses modern markup and full styling.
	 */
	public function get_legacy_styling_reason(): string {

		if ( ! $this->is_modern_engine() ) {
			return __( 'Upgrade your forms to use our modern markup and unlock extensive style controls.', 'wpforms-lite' );
		}

		if ( ! $this->is_full_styling() ) {
			return __( 'Update your forms to use base and form theme styling and unlock extensive style controls.', 'wpforms-lite' );
		}

		return '';
	}

	/**
	 * Get the data an "Edit Theme" entry point needs: whether the editor is supported, why not, and its strings.
	 *
	 * The block, the Divi modules and the Elementor widget render the same button, sentence and notices.
	 *
	 * @since 2.0.5
	 *
	 * @param string $utm_medium The UTM medium of the documentation link, naming the builder it is shown in.
	 *
	 * @return array `supported`, `reason`, `strings`.
	 */
	public function get_editor_launch_data( string $utm_medium = 'Block Settings' ): array {

		return [
			'supported' => $this->editor->is_supported(),
			'reason'    => $this->get_legacy_styling_reason(),
			'strings'   => [
				'title'                   => esc_html__( 'Form Theme', 'wpforms-lite' ),
				'description'             => esc_html__( 'Themes are edited live on your site with the front-end theme editor.', 'wpforms-lite' ),
				'save_hint'               => esc_html__( 'Save the page first if you just added this form.', 'wpforms-lite' ),
				'button'                  => esc_html__( 'Edit Theme', 'wpforms-lite' ),
				'confirm_title'           => esc_html__( 'Save Changes?', 'wpforms-lite' ),
				'confirm_text'            => esc_html__( 'The page has unsaved changes. Save them and open the theme editor?', 'wpforms-lite' ),
				'confirm_save'            => esc_html__( 'Save & Continue', 'wpforms-lite' ),
				'cancel'                  => esc_html__( 'Cancel', 'wpforms-lite' ),
				'admin_bar_hint'          => esc_html__( 'Open a page that shows this form and use Edit Form Theme in the admin bar.', 'wpforms-lite' ),
				'lead_forms_notice_head'  => esc_html__( 'Form Styles are disabled because Lead Form Mode is turned on.', 'wpforms-lite' ),
				'lead_forms_notice_text'  => esc_html__( 'To change the styling for this form, open it in the form builder and edit the options in the Lead Forms settings.', 'wpforms-lite' ),
				'unsupported_notice_head' => esc_html__( 'Want to customize your form styles without editing CSS?', 'wpforms-lite' ),
				'unsupported_notice_text' => esc_html__( 'Enable modern markup in your WPForms settings to style this form in the themes editor.', 'wpforms-lite' ),
				'unsupported_notice_link' => esc_url( wpforms_utm_link( 'https://wpforms.com/docs/styling-your-forms/', $utm_medium, 'Form Styles Documentation' ) ),
				'learn_more'              => esc_html__( 'Learn more', 'wpforms-lite' ),
				'no_access_notice_head'   => esc_html__( 'You don’t have permission to edit this form’s theme.', 'wpforms-lite' ),
				'no_access_notice_text'   => esc_html__( 'Contact your site administrator for access.', 'wpforms-lite' ),
			],
		];
	}

	/**
	 * Get the launch data of a site without the themes editor: nothing to launch, nothing to say.
	 *
	 * Static because an entry point reads it exactly when there is no editor to ask.
	 *
	 * @since 2.0.5
	 *
	 * @return array `supported`, `reason`, `strings`.
	 */
	public static function get_unavailable_launch_data(): array {

		return [
			'supported' => false,
			'reason'    => '',
			'strings'   => [],
		];
	}

	/**
	 * Whether forms render with the modern markup engine.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	public function is_modern_engine(): bool {

		return wpforms_get_render_engine() === 'modern';
	}

	/**
	 * Whether forms load the full "Base and Form Theme Styling".
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	public function is_full_styling(): bool {

		return (int) wpforms_setting( 'disable-css', '1' ) === 1;
	}
}
