<?php

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace WPForms\Integrations\Elementor;

use Elementor\Plugin as ElementorPlugin;
use WPForms\Frontend\ThemesEditor;
use WPForms\Frontend\ThemesEditor\Support;
use WPForms\Integrations\IntegrationInterface;

/**
 * Improve Elementor Compatibility.
 *
 * @since 1.6.0
 */
class Elementor implements IntegrationInterface {

	/**
	 * Indicates if the current integration is allowed to load.
	 *
	 * @since 1.6.0
	 *
	 * @return bool
	 */
	public function allow_load() {

		return (bool) did_action( 'elementor/loaded' );
	}

	/**
	 * Load an integration.
	 *
	 * @since 1.6.0
	 */
	public function load() {

		$this->hooks();
	}

	/**
	 * Integration hooks.
	 *
	 * @since 1.6.0
	 *
	 * @noinspection PhpUndefinedConstantInspection
	 */
	protected function hooks() {

		// Skip if Elementor is not available.
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}

		add_action( 'elementor/preview/init', [ $this, 'init' ] );
		add_action( 'elementor/frontend/after_enqueue_scripts', [ $this, 'preview_assets' ] );
		add_action( 'elementor/frontend/after_enqueue_scripts', [ $this, 'frontend_assets' ] );
		add_action( 'wp_print_footer_scripts', [ $this, 'maybe_dequeue_frontend_script' ], 9 );
		add_action( 'elementor/editor/after_enqueue_styles', [ $this, 'editor_assets' ] );

		version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' )
			? add_action( 'elementor/widgets/register', [ $this, 'register_widget' ] )
			: add_action( 'elementor/widgets/widgets_registered', [ $this, 'register_widget' ] );

		add_action( 'wp_ajax_wpforms_admin_get_form_selector_options', [ $this, 'ajax_get_form_selector_options' ] );
		add_filter( 'wpforms_integrations_gutenberg_form_selector_allow_render', [ $this, 'disable_gutenberg_block_render' ] );
		add_filter( 'wpforms_forms_anti_spam_v3_is_honeypot_enabled', [ $this, 'filter_is_honeypot_enabled' ] );
		add_action( 'rest_api_init', [ $this, 'register_legacy_themes_routes' ] );
	}

	/**
	 * Register the widget's former themes routes, which now serve the shared themes.
	 *
	 * @since 2.0.5
	 */
	public function register_legacy_themes_routes(): void {

		$editor = wpforms()->obj( 'themes_editor' );

		if ( ! $editor instanceof ThemesEditor || ! wpforms_is_wpforms_rest() ) {
			return;
		}

		( new LegacyThemesRoutes( $editor->get_themes_data() ) )->register();
	}

	/**
	 * Init the main logic.
	 *
	 * @since 1.6.0
	 */
	public function init(): void { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		/**
		 * Allow developers to determine whether the compatibility layer should be applied.
		 * We do this check here because we want this filter to be available for theme developers too.
		 *
		 * @since 1.6.0
		 *
		 * @param bool $use_compat Use compatibility.
		 */
		$use_compat = (bool) apply_filters( 'wpforms_apply_elementor_preview_compat', true ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		if ( $use_compat !== true ) {
			return;
		}

		// Load WPForms assets globally on the Elementor Preview panel only.
		add_filter( 'wpforms_global_assets', '__return_true' );

		// Hide CAPTCHA badge on Elementor Preview panel.
		add_filter( 'wpforms_frontend_recaptcha_disable', '__return_true' );
	}

	/**
	 * Load assets in the preview panel.
	 *
	 * @since 1.6.2
	 */
	public function preview_assets() {

		if ( ! ElementorPlugin::$instance->preview->is_preview_mode() ) {
			return;
		}

		$min = wpforms_get_min_suffix();

		// jQuery.Confirm Reloaded.
		wp_enqueue_style(
			'jquery-confirm',
			WPFORMS_PLUGIN_URL . 'assets/lib/jquery.confirm/jquery-confirm.min.css',
			null,
			'1.0.0'
		);

		wp_enqueue_script(
			'jquery-confirm',
			WPFORMS_PLUGIN_URL . 'assets/lib/jquery.confirm/jquery-confirm.min.js',
			[ 'jquery' ],
			'1.0.0',
			false
		);

		wp_enqueue_style(
			'wpforms-font-awesome',
			WPFORMS_PLUGIN_URL . 'assets/lib/font-awesome/css/all.min.css',
			null,
			'7.0.1'
		);

		// FontAwesome v4 compatibility shims.
		wp_enqueue_style(
			'wpforms-font-awesome-v4-shim',
			WPFORMS_PLUGIN_URL . 'assets/lib/font-awesome/css/v4-shims.min.css',
			null,
			'4.7.0'
		);

		wp_enqueue_style(
			'wpforms-integrations',
			WPFORMS_PLUGIN_URL . "assets/css/admin-integrations{$min}.css",
			null,
			WPFORMS_VERSION
		);

		wp_enqueue_script(
			'wpforms-elementor',
			WPFORMS_PLUGIN_URL . "assets/js/integrations/elementor/editor{$min}.js",
			[ 'elementor-frontend', 'jquery', 'wp-util', 'wpforms', 'jquery-confirm' ],
			WPFORMS_VERSION,
			true
		);

		if ( $this->is_modern_widget() ) {

			wp_enqueue_script(
				'wpforms-elementor-modern',
				WPFORMS_PLUGIN_URL . "assets/js/integrations/elementor/editor-modern{$min}.js",
				[ 'wpforms-elementor' ],
				WPFORMS_VERSION,
				true
			);
		}

		/**
		 * Filter the strings passed to the Elementor editor script.
		 *
		 * The strings served the widget style controls, which moved to the front-end themes editor.
		 *
		 * @since 1.9.6
		 * @deprecated 2.0.5
		 *
		 * @param array $strings Array of strings to be filtered.
		 */
		apply_filters_deprecated( // phpcs:ignore WPForms.Comments.ParamTagHooks.InvalidParamTagsQuantity
			'wpforms_integrations_elementor_editor_strings',
			[ [] ],
			'2.0.5 of the WPForms plugin'
		);

		/**
		 * Filter the variables passed to an Elementor editor script.
		 *
		 * @since 1.9.6
		 *
		 * @param array $vars Array of variables to be filtered.
		 */
		$vars = apply_filters(
			'wpforms_integrations_elementor_editor_vars',
			[
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'wpforms-elementor-integration' ),
				'edit_form_url' => admin_url( 'admin.php?page=wpforms-builder&view=fields&form_id=' ),
				'add_form_url'  => admin_url( 'admin.php?page=wpforms-builder&view=setup' ),
				'css_url'       => WPFORMS_PLUGIN_URL . "assets/css/admin-integrations{$min}.css",
				'debug'         => wpforms_debug(),
			]
		);

		wp_localize_script(
			'wpforms-elementor',
			'wpformsElementorVars',
			$vars
		);
	}

	/**
	 * Load an integration assets on the frontend.
	 *
	 * @since 1.6.2
	 */
	public function frontend_assets(): void {

		if ( ElementorPlugin::$instance->preview->is_preview_mode() ) {
			return;
		}

		$min = wpforms_get_min_suffix();

		wp_register_script(
			'wpforms-elementor',
			WPFORMS_PLUGIN_URL . "assets/js/integrations/elementor/frontend{$min}.js",
			[ 'elementor-frontend', 'jquery', 'wp-util', 'wpforms' ],
			WPFORMS_VERSION,
			true
		);

		wp_localize_script(
			'wpforms-elementor',
			'wpformsElementorVars',
			[
				'captcha_provider' => wpforms_setting( 'captcha-provider', 'recaptcha' ),
				'recaptcha_type'   => wpforms_setting( 'recaptcha-type', 'v2' ),
			]
		);
	}

	/**
	 * Dequeue the frontend script when no form loaded the core `wpforms` script it depends on.
	 *
	 * Elementor enqueues the widget script even when the widget renders no form, while `wpforms`
	 * loads only when a form renders, so WordPress would drop the script and flag the missing dependency.
	 *
	 * @since 2.0.4
	 */
	public function maybe_dequeue_frontend_script(): void {

		if ( ! wp_script_is( 'wpforms', 'registered' ) ) {
			wp_dequeue_script( 'wpforms-elementor' );
		}
	}

	/**
	 * Load assets in the elementor document.
	 *
	 * @since 1.6.2
	 */
	public function editor_assets() {

		if ( empty( $_GET['action'] ) || $_GET['action'] !== 'elementor' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$min = wpforms_get_min_suffix();

		wp_enqueue_style(
			'wpforms-integrations',
			WPFORMS_PLUGIN_URL . "assets/css/admin-integrations{$min}.css",
			null,
			WPFORMS_VERSION
		);

		// Choices.js.
		wp_enqueue_script(
			'choicesjs',
			WPFORMS_PLUGIN_URL . 'assets/lib/choices.min.js',
			[],
			'10.2.0',
			false
		);

		// Loaded with any markup: a widget inserted while the classic markup is on still gets the marker.
		wp_enqueue_script(
			'wpforms-elementor-editor-context',
			WPFORMS_PLUGIN_URL . "assets/js/integrations/elementor/editor-context{$min}.js",
			[ 'jquery' ],
			WPFORMS_VERSION,
			true
		);

		$editor = wpforms()->obj( 'themes_editor' );
		$data   = $editor instanceof ThemesEditor ? $editor->get_support()->get_editor_launch_data( 'Elementor Widget Settings' ) : Support::get_unavailable_launch_data();

		wp_localize_script(
			'wpforms-elementor-editor-context',
			'wpformsElementorEditorContext',
			[
				'strings' => array_intersect_key( (array) $data['strings'], array_flip( [ 'confirm_title', 'confirm_text', 'confirm_save', 'cancel' ] ) ),
			]
		);
	}

	/**
	 * Register WPForms Widget.
	 *
	 * @since 1.6.2
	 * @since 1.7.6 Added support for the new registration method since 3.5.0.
	 * @since 1.8.3 Added a condition for selecting the required widget instance.
	 *
	 * @noinspection PhpUndefinedConstantInspection
	 */
	public function register_widget(): void {

		$widget_instance = $this->is_modern_widget() ? new WidgetModern() : new Widget();

		version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' )
			? ElementorPlugin::instance()->widgets_manager->register( $widget_instance )
			: ElementorPlugin::instance()->widgets_manager->register_widget_type( $widget_instance );
	}

	/**
	 * Get form selector options.
	 *
	 * @since 1.6.2
	 */
	public function ajax_get_form_selector_options(): void {

		check_ajax_referer( 'wpforms-elementor-integration', 'nonce' );

		$widget = new Widget();

		// In Pro the form list is not public data, so callers without a WPForms capability are denied.
		// A nonce is CSRF protection, not authorization, it is minted for any user who can open the
		// Elementor editor.
		if ( ! $widget->current_user_can_view_forms() ) {
			wp_send_json_error( esc_html__( 'You are not allowed to perform this action.', 'wpforms-lite' ), 403 );
		}

		wp_send_json_success( $widget->get_form_selector_options() );
	}

	/**
	 * Detect modern Widget.
	 *
	 * @since 1.8.3
	 */
	protected function is_modern_widget(): bool {

		return wpforms_get_render_engine() === 'modern' && (int) wpforms_setting( 'disable-css', '1' ) === 1;
	}

	/**
	 * Disable the block render for pages built in Elementor.
	 *
	 * @since 1.8.8
	 *
	 * @param bool|mixed $allow_render Whether to allow the block render.
	 *
	 * @return bool Whether to disable the block render.
	 */
	public function disable_gutenberg_block_render( $allow_render ): bool {

		$allow_render = (bool) $allow_render;

		$document = ElementorPlugin::$instance->documents->get( get_the_ID() );

		if ( $document && $document->is_built_with_elementor() ) {
			return false;
		}

		return $allow_render;
	}

	/**
	 * Disable honeypot on the preview panel.
	 *
	 * @since 1.9.0
	 *
	 * @param bool|mixed $is_enabled True if the honeypot is enabled, false otherwise.
	 *
	 * @return bool Whether to disable the honeypot.
	 */
	public function filter_is_honeypot_enabled( $is_enabled ): bool {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = sanitize_key( $_REQUEST['action'] ?? '' );

		if (
			in_array( $action, [ 'elementor', 'elementor_ajax' ], true ) ||
			ElementorPlugin::$instance->preview->is_preview_mode()
		) {
			return false;
		}

		return (bool) $is_enabled;
	}
}
