<?php

namespace WPForms\Admin\Builder\Settings;

use WPForms\Frontend\ThemesEditor;
use WPForms_Builder_Panel_Settings;

/**
 * Themes panel.
 *
 * @since 1.8.8
 */
class Themes {

	/**
	 * Init class.
	 *
	 * @since 1.8.8
	 */
	public function init(): void {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.8.8
	 */
	private function hooks(): void {

		add_action( 'wpforms_form_settings_panel_content', [ $this, 'launcher_panel_content' ] );

		// After the builder registers its scripts (PHP_INT_MAX), so the
		// inline script attaches to the wpforms-builder handle.
		add_action( 'admin_enqueue_scripts', [ $this, 'launcher_enqueues' ], PHP_INT_MAX );
	}

	/**
	 * Whether the front-end themes editor is available for this site.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	private function is_editor_supported(): bool {

		$editor = $this->get_editor();

		return $editor !== null && $editor->is_supported();
	}

	/**
	 * Get the front-end themes editor instance.
	 *
	 * @since 2.0.5
	 *
	 * @return ThemesEditor|null
	 */
	private function get_editor(): ?ThemesEditor {

		$editor = wpforms()->obj( 'themes_editor' );

		return $editor instanceof ThemesEditor ? $editor : null;
	}

	/**
	 * Render the Themes panel as a front-end editor launcher.
	 *
	 * @since 2.0.5
	 *
	 * @param WPForms_Builder_Panel_Settings $instance Settings panel instance.
	 *
	 * @noinspection HtmlUnknownTarget
	 */
	public function launcher_panel_content( WPForms_Builder_Panel_Settings $instance ): void {

		$form_data    = (array) $instance->form_data;
		$editor       = $this->get_editor();
		$support      = $editor !== null ? $editor->get_support() : null;
		$notice       = $this->get_addon_notice( $editor, $form_data );
		$is_lead_form = $notice === 'lead_forms';
		$is_cf_form   = $notice === 'conversational_forms';
		$is_fp_form   = $notice === 'form_pages';

		?>
		<div class="wpforms-panel-content-section wpforms-panel-content-section-themes">
			<div class="wpforms-panel-content-section-title">
				<?php esc_html_e( 'Form Themes', 'wpforms-lite' ); ?>
			</div>

			<?php // All blocks render: the Themes click shows one by the current Lead Form Mode and Conversational Forms toggles. ?>
			<div class="wpforms-builder-themes-lf-content"<?php echo $is_lead_form ? '' : ' hidden'; ?>>
				<?php $this->render_lead_forms_notice(); ?>
			</div>

			<?php if ( $support !== null && $support->is_conversational_forms_active() ) : ?>
				<div class="wpforms-builder-themes-cf-content"<?php echo $is_cf_form ? '' : ' hidden'; ?>>
					<?php $this->render_conversational_forms_notice(); ?>
				</div>
			<?php endif; ?>

			<?php if ( $support !== null && $support->is_form_pages_active() ) : ?>
				<div class="wpforms-builder-themes-fp-content"<?php echo $is_fp_form ? '' : ' hidden'; ?>>
					<?php $this->render_form_pages_notice(); ?>
				</div>
			<?php endif; ?>

			<div class="wpforms-builder-themes-launcher-content"<?php echo $notice !== '' ? ' hidden' : ''; ?>>
				<?php $this->render_launcher( $editor, absint( $form_data['id'] ?? 0 ) ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the launcher button, or the notice explaining why the editor cannot run.
	 *
	 * @since 2.0.5
	 *
	 * @param ThemesEditor|null $editor  Front-end editor, null when it is not registered.
	 * @param int               $form_id Form ID.
	 */
	private function render_launcher( ?ThemesEditor $editor, int $form_id ): void {

		if ( ! $this->is_editor_supported() || $editor === null ) {
			$this->render_unsupported_notice();

			return;
		}

		?>
		<p>
			<?php esc_html_e( 'Themes are edited live on your site with the front-end theme editor.', 'wpforms-lite' ); ?>
		</p>

		<a href="<?php echo esc_url( $editor->get_editor_url( $form_id ) ); ?>" target="_blank" rel="noopener" class="wpforms-btn wpforms-btn-md wpforms-btn-orange">
			<?php esc_html_e( 'Edit Your Theme', 'wpforms-lite' ); ?>
		</a>
		<?php
	}

	/**
	 * Which addon notice the panel opens on: the addon that owns the form's styling and does not bridge the editor yet.
	 *
	 * @since 2.0.5
	 *
	 * @param ThemesEditor|null $editor    Front-end editor, null when it is not registered.
	 * @param array             $form_data Form data.
	 *
	 * @return string `lead_forms`, `conversational_forms`, `form_pages`, or empty for the launcher.
	 */
	private function get_addon_notice( ?ThemesEditor $editor, array $form_data ): string {

		if ( $editor === null ) {
			return '';
		}

		$support = $editor->get_support();

		if ( $support->is_unstyled_lead_form( $form_data ) ) {
			return 'lead_forms';
		}

		if ( $editor->is_form_supported( $form_data ) ) {
			return '';
		}

		if ( $support->is_conversational_form( $form_data ) ) {
			return 'conversational_forms';
		}

		return $support->is_form_page_form( $form_data ) ? 'form_pages' : '';
	}

	/**
	 * Render the education notice shown when the front-end editor cannot run.
	 *
	 * Mirrors the legacy Themes panel content, minus the demo form preview.
	 *
	 * @since 2.0.5
	 */
	private function render_unsupported_notice(): void {

		$docs_url = wpforms_utm_link( 'https://wpforms.com/docs/styling-your-forms/', 'Builder Themes', 'Description Link' );

		$settings_url = add_query_arg(
			[
				'page' => 'wpforms-settings',
				'view' => 'general',
			],
			admin_url( 'admin.php' )
		);

		$editor     = $this->get_editor();
		$reason     = $editor ? $editor->get_support()->get_unsupported_reason() : '';
		$is_fixable = $reason !== '';

		if ( ! $is_fixable ) {
			$reason = __( 'The front-end theme editor is not available on this site.', 'wpforms-lite' );
		}

		?>
		<p class="wpforms-panel-content-section-themes-description">
			<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s - URL to the documentation. */
						__( 'Customize the look and feel of your form with premade themes or simple style settings that allow you to use your own colors to match your brand. <a href="%s" target="_blank">Learn more about styling your forms</a>.', 'wpforms-lite' ),
						$docs_url
					)
				);
			?>
		</p>

		<div class="wpforms-alert wpforms-alert-warning wpforms-builder-themes-style-notice">
			<h4><?php esc_html_e( 'Want to customize your form styles without editing CSS?', 'wpforms-lite' ); ?></h4>
			<p><?php echo esc_html( $reason ); ?></p>
			<?php if ( $is_fixable ) : // A reason Settings cannot fix would make the CTA mislead. ?>
				<a href="<?php echo esc_url( $settings_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Go to Settings', 'wpforms-lite' ); ?></a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the notice shown when Lead Form Mode owns the form styling.
	 *
	 * The link switches to the Lead Forms settings section in place, wired by
	 * the inline script {@see launcher_enqueues()} registers.
	 *
	 * @since 2.0.5
	 */
	private function render_lead_forms_notice(): void {

		?>
		<div class="wpforms-alert wpforms-alert-warning wpforms-builder-themes-lf-notice">
			<h4><?php esc_html_e( 'Form Styles are disabled because Lead Form Mode is turned on.', 'wpforms-lite' ); ?></h4>
			<p>
				<?php
				echo wp_kses(
					__( 'To change the styling for this form, edit the options in the <a href="#">Lead Forms settings</a>.', 'wpforms-lite' ),
					[
						'a' => [
							'href' => [],
						],
					]
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the notice shown when Conversational Forms owns the form styling.
	 *
	 * The link switches to the Conversational Forms settings section in place, wired by
	 * the inline script {@see launcher_enqueues()} registers.
	 *
	 * @since 2.0.5
	 */
	private function render_conversational_forms_notice(): void {

		?>
		<div class="wpforms-alert wpforms-alert-warning wpforms-builder-themes-cf-notice">
			<h4><?php esc_html_e( 'Form Styles are disabled because Conversational Forms addon is turned on.', 'wpforms-lite' ); ?></h4>
			<p>
				<?php
				echo wp_kses(
					__( 'To change the styling for this form, edit the options in the <a href="#">Conversational Forms settings</a>.', 'wpforms-lite' ),
					[
						'a' => [
							'href' => [],
						],
					]
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the notice shown when Form Pages owns the form styling.
	 *
	 * The link switches to the Form Pages settings section in place, wired by
	 * the inline script {@see launcher_enqueues()} registers.
	 *
	 * @since 2.0.5
	 */
	private function render_form_pages_notice(): void {

		?>
		<div class="wpforms-alert wpforms-alert-warning wpforms-builder-themes-fp-notice">
			<h4><?php esc_html_e( 'Form Styles are disabled because Form Pages addon is turned on.', 'wpforms-lite' ); ?></h4>
			<p>
				<?php
				echo wp_kses(
					__( 'To change the styling for this form, edit the options in the <a href="#">Form Pages settings</a>.', 'wpforms-lite' ),
					[
						'a' => [
							'href' => [],
						],
					]
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Intercept the Themes sidebar link to launch the front-end editor.
	 *
	 * With Lead Form Mode or Conversational Forms on, the Themes panel opens on
	 * its notice instead, whose link switches to that addon's settings section.
	 *
	 * @since 2.0.5
	 */
	public function launcher_enqueues(): void {

		$editor  = $this->get_editor();
		$form_id = absint( $_GET['form_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $form_id || $editor === null ) {
			return;
		}

		$cf_form_data = $this->get_form_data_with_cf_on( $form_id );
		$fp_form_data = $this->get_form_data_with_fp_on( $form_id );
		$lf_form_data = $this->get_form_data_with_lf_on( $form_id );

		// The Lead Form Mode and Conversational Forms toggles are read on click, so a change made since the builder loaded counts.
		$script = sprintf(
			"document.addEventListener( 'click', function( e ) {
				var editorUrl = %1\$s;
				var cfThemesSupported = %2\$s;
				var previewUrl = %3\$s;
				var fpThemesSupported = %4\$s;
				var lfThemesSupported = %5\$s;
				var noticeSections = {
					'.wpforms-builder-themes-lf-notice a': 'lead_forms',
					'.wpforms-builder-themes-cf-notice a': 'conversational_forms',
					'.wpforms-builder-themes-fp-notice a': 'form_pages'
				};

				if ( ! e.target.closest ) {
					return;
				}

				for ( var noticeLink in noticeSections ) {
					if ( e.target.closest( noticeLink ) ) {
						e.preventDefault();

						var section = document.querySelector( 'a.wpforms-panel-sidebar-section-' + noticeSections[ noticeLink ] );

						if ( section ) {
							section.click();
						}

						return;
					}
				}

				if ( ! e.target.closest( '.wpforms-panel-sidebar-section-themes' ) ) {
					return;
				}

				var leadFormsToggle = document.getElementById( 'wpforms-panel-field-lead_forms-enable' );
				var cfToggle = document.getElementById( 'wpforms-panel-field-settings-conversational_forms_enable' );
				var isLeadForm = Boolean( leadFormsToggle && leadFormsToggle.checked ) && ! lfThemesSupported;
				var isCfForm = ! isLeadForm && Boolean( cfToggle && cfToggle.checked ) && ! cfThemesSupported;
				var fpToggle = document.getElementById( 'wpforms-panel-field-settings-form_pages_enable' );
				var isFpForm = ! isLeadForm && ! Boolean( cfToggle && cfToggle.checked ) && Boolean( fpToggle && fpToggle.checked ) && ! fpThemesSupported;
				var leadFormsContent = document.querySelector( '.wpforms-builder-themes-lf-content' );
				var cfContent = document.querySelector( '.wpforms-builder-themes-cf-content' );
				var fpContent = document.querySelector( '.wpforms-builder-themes-fp-content' );
				var launcherContent = document.querySelector( '.wpforms-builder-themes-launcher-content' );

				if ( leadFormsContent && launcherContent ) {
					leadFormsContent.hidden = ! isLeadForm;
					launcherContent.hidden = isLeadForm || isCfForm || isFpForm;
				}

				if ( cfContent ) {
					cfContent.hidden = ! isCfForm;
				}

				if ( fpContent ) {
					fpContent.hidden = ! isFpForm;
				}

				if ( isLeadForm || isCfForm || isFpForm || ! editorUrl ) {
					return;
				}

				e.preventDefault();
				e.stopPropagation();

				// The live toggles decide the page, as for the Preview button, and openPreview() saves a dirty form first.
				var dropdown = window.WPForms && WPForms.Admin && WPForms.Admin.Builder && WPForms.Admin.Builder.PreviewDropdown;

				if ( dropdown && dropdown.getActiveAddonPreviewUrl && dropdown.openPreview ) {
					var addonUrl = dropdown.getActiveAddonPreviewUrl();

					dropdown.openPreview( addonUrl ? addonUrl + ( addonUrl.indexOf( '?' ) === -1 ? '?' : '&' ) + 'wpforms_themes_editor=1' : previewUrl );

					return;
				}

				window.open( editorUrl, '_blank' );
			}, true );",
			wp_json_encode( $editor->is_supported() ? $editor->get_editor_url( $form_id ) : '' ),
			wp_json_encode( $editor->is_supported() && $editor->is_form_supported( $cf_form_data ) ),
			wp_json_encode( add_query_arg( 'wpforms_themes_editor', '1', wpforms_get_form_preview_url( $form_id ) ) ),
			wp_json_encode( $editor->is_supported() && $editor->is_form_supported( $fp_form_data ) ),
			wp_json_encode( $editor->is_supported() && $editor->is_form_supported( $lf_form_data ) )
		);

		wp_add_inline_script( 'wpforms-builder', $script );
	}

	/**
	 * The stored form with Conversational Form Mode switched on.
	 *
	 * The launcher reads the live toggle, so it needs to know whether a CF form
	 * is supported before the user saves.
	 *
	 * @since 2.0.5
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array
	 */
	private function get_form_data_with_cf_on( int $form_id ): array {

		$form_handler = wpforms()->obj( 'form' );
		$form_data    = $form_handler ? (array) $form_handler->get( $form_id, [ 'content_only' => true ] ) : [];

		$form_data['settings']['conversational_forms_enable'] = '1';

		return $form_data;
	}

	/**
	 * The stored form with Form Pages switched on and Conversational Forms off, as the live toggles may stand.
	 *
	 * @since 2.0.5
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array
	 */
	private function get_form_data_with_fp_on( int $form_id ): array {

		$form_data = $this->get_form_data_with_cf_on( $form_id );

		$form_data['settings']['form_pages_enable']           = '1';
		$form_data['settings']['conversational_forms_enable'] = '';

		return $form_data;
	}

	/**
	 * The stored form with Lead Form Mode switched on, as the live toggle may stand.
	 *
	 * @since 2.0.5
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array
	 */
	private function get_form_data_with_lf_on( int $form_id ): array {

		$form_data = $this->get_form_data_with_cf_on( $form_id );

		$form_data['settings']['lead_forms']['enable']        = '1';
		$form_data['settings']['conversational_forms_enable'] = '';

		return $form_data;
	}
}
