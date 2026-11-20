<?php

namespace WPForms\Integrations\Gutenberg;

use WP_Post;
use WPForms\Frontend\CSSVars;
use WPForms\Frontend\EmbedContext;
use WPForms\Frontend\ThemesEditor;
use WPForms\Frontend\ThemesEditor\Support;
use WPForms\Integrations\IntegrationInterface;

/**
 * Form Selector Gutenberg block with a live preview.
 *
 * @since 1.4.8
 */
abstract class FormSelector implements IntegrationInterface {

	/**
	 * Default attributes.
	 *
	 * @since 1.8.1
	 *
	 * @var array
	 */
	private const DEFAULT_ATTRIBUTES = [
		'formId'                => '',
		'displayTitle'          => false,
		'displayDesc'           => false,
		'theme'                 => '',
		'themeName'             => '',
		'fieldSize'             => 'medium',
		'backgroundImage'       => CSSVars::ROOT_VARS['background-image'],
		'backgroundPosition'    => CSSVars::ROOT_VARS['background-position'],
		'backgroundRepeat'      => CSSVars::ROOT_VARS['background-repeat'],
		'backgroundSizeMode'    => CSSVars::ROOT_VARS['background-size'],
		'backgroundSize'        => CSSVars::ROOT_VARS['background-size'],
		'backgroundWidth'       => CSSVars::ROOT_VARS['background-width'],
		'backgroundHeight'      => CSSVars::ROOT_VARS['background-height'],
		'backgroundUrl'         => CSSVars::ROOT_VARS['background-url'],
		'backgroundColor'       => CSSVars::ROOT_VARS['background-color'],
		'fieldBorderRadius'     => CSSVars::ROOT_VARS['field-border-radius'],
		'fieldBorderStyle'      => CSSVars::ROOT_VARS['field-border-style'],
		'fieldBorderSize'       => CSSVars::ROOT_VARS['field-border-size'],
		'fieldBackgroundColor'  => CSSVars::ROOT_VARS['field-background-color'],
		'fieldBorderColor'      => CSSVars::ROOT_VARS['field-border-color'],
		'fieldTextColor'        => CSSVars::ROOT_VARS['field-text-color'],
		'fieldMenuColor'        => CSSVars::ROOT_VARS['field-menu-color'],
		'labelSize'             => 'medium',
		'labelColor'            => CSSVars::ROOT_VARS['label-color'],
		'labelSublabelColor'    => CSSVars::ROOT_VARS['label-sublabel-color'],
		'labelErrorColor'       => CSSVars::ROOT_VARS['label-error-color'],
		'buttonSize'            => 'medium',
		'buttonBorderStyle'     => CSSVars::ROOT_VARS['button-border-style'],
		'buttonBorderSize'      => CSSVars::ROOT_VARS['button-border-size'],
		'buttonBorderRadius'    => CSSVars::ROOT_VARS['button-border-radius'],
		'buttonBackgroundColor' => CSSVars::ROOT_VARS['button-background-color'],
		'buttonTextColor'       => CSSVars::ROOT_VARS['button-text-color'],
		'buttonBorderColor'     => CSSVars::ROOT_VARS['button-border-color'],
		'pageBreakColor'        => CSSVars::ROOT_VARS['page-break-color'],
		'containerPadding'      => CSSVars::ROOT_VARS['container-padding'],
		'containerBorderStyle'  => CSSVars::ROOT_VARS['container-border-style'],
		'containerBorderWidth'  => CSSVars::ROOT_VARS['container-border-width'],
		'containerBorderColor'  => CSSVars::ROOT_VARS['container-border-color'],
		'containerBorderRadius' => CSSVars::ROOT_VARS['container-border-radius'],
		'containerShadowSize'   => CSSVars::CONTAINER_SHADOW_SIZE['none']['box-shadow'],
		'customCss'             => '',
		'copyPasteJsonValue'    => '',
	];

	/**
	 * Rest API class instance.
	 *
	 * @since 1.8.8
	 *
	 * @var RestApi
	 */
	protected $rest_api_obj;

	/**
	 * Rest API class instance.
	 *
	 * @since 1.8.8
	 *
	 * @var ThemesData
	 */
	protected $themes_data_obj;

	/**
	 * Render engine.
	 *
	 * @since 1.8.1
	 *
	 * @var string
	 */
	protected $render_engine;

	/**
	 * Disabled CSS setting.
	 *
	 * @since 1.8.1
	 *
	 * @var integer
	 */
	protected $disable_css_setting;

	/**
	 * Instance of CSSVars class.
	 *
	 * @since 1.8.1
	 *
	 * @var CSSVars
	 */
	private $css_vars_obj;

	/**
	 * Callbacks registered for wpforms_frontend_container_class filter.
	 *
	 * @since 1.7.5
	 *
	 * @var array
	 */
	private $callbacks = [];

	/**
	 * Block CSS printer.
	 *
	 * @since 2.0.5
	 *
	 * @var BlockStyles
	 */
	private $block_styles;

	/**
	 * Indicate if the current integration is allowed to load.
	 *
	 * @since 1.4.8
	 *
	 * @return bool
	 */
	public function allow_load(): bool {

		return function_exists( 'register_block_type' );
	}

	/**
	 * Load an integration.
	 *
	 * @since 1.4.8
	 */
	public function load() {

		$this->render_engine       = wpforms_get_render_engine();
		$this->disable_css_setting = (int) wpforms_setting( 'disable-css', '1' );
		$this->css_vars_obj        = wpforms()->obj( 'css_vars' );
		$this->block_styles        = new BlockStyles( $this->themes_data_obj, $this->css_vars_obj, self::DEFAULT_ATTRIBUTES );

		wpforms()->register_instance( 'formselector_themes_data', $this->themes_data_obj );

		$this->hooks();
	}

	/**
	 * Integration hooks.
	 *
	 * @since 1.4.8
	 */
	protected function hooks() {

		add_action( 'init', [ $this, 'register_block' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );
		add_action( 'wpforms_frontend_output_container_after', [ $this, 'replace_wpforms_frontend_container_class_filter' ] );
		add_filter( 'wpforms_frontend_form_action', [ $this, 'form_action_filter' ], 10, 2 );
		add_filter( 'wpforms_forms_anti_spam_v3_is_honeypot_enabled', [ $this, 'filter_is_honeypot_enabled' ] );
		add_filter( 'wpforms_field_richtext_display_editor_is_media_enabled', [ $this, 'disable_richtext_media' ], 10, 2 );
	}

	/**
	 * Disable honeypot in Gutenberg/Block editor.
	 *
	 * @since 1.9.0
	 *
	 * @param bool|mixed $is_enabled True if the honeypot is enabled, false otherwise.
	 *
	 * @return bool Whether to disable the honeypot.
	 */
	public function filter_is_honeypot_enabled( $is_enabled ): bool {

		if ( wpforms_is_wpforms_rest() ) {
			return false;
		}

		return (bool) $is_enabled;
	}

	/**
	 * Replace the filter registered for wpforms_frontend_container_class.
	 *
	 * @since 1.7.5
	 *
	 * @param array $form_data Form data.
	 *
	 * @return void
	 */
	public function replace_wpforms_frontend_container_class_filter( array $form_data ): void { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		if ( empty( $this->callbacks[ $form_data['id'] ] ) ) {
			return;
		}

		$callback = array_shift( $this->callbacks[ $form_data['id'] ] );

		remove_filter( 'wpforms_frontend_container_class', $callback );

		if ( ! empty( $this->callbacks[ $form_data['id'] ] ) ) {
			add_filter( 'wpforms_frontend_container_class', reset( $this->callbacks[ $form_data['id'] ] ), 10, 2 );
		}
	}

	/**
	 * Register WPForms Gutenberg block on the backend.
	 *
	 * @since 1.4.8
	 */
	public function register_block(): void {

		$type_string  = [ 'type' => 'string' ];
		$type_boolean = [ 'type' => 'boolean' ];

		$attributes = [
			'clientId'              => $type_string,
			'formId'                => $type_string,
			'displayTitle'          => $type_boolean,
			'displayDesc'           => $type_boolean,
			'className'             => $type_string,
			'theme'                 => $type_string,
			'themeName'             => $type_string,
			'fieldSize'             => $type_string,
			'fieldBorderRadius'     => $type_string,
			'fieldBorderStyle'      => $type_string,
			'fieldBorderSize'       => $type_string,
			'fieldBackgroundColor'  => $type_string,
			'fieldBorderColor'      => $type_string,
			'fieldTextColor'        => $type_string,
			'fieldMenuColor'        => $type_string,
			'labelSize'             => $type_string,
			'labelColor'            => $type_string,
			'labelSublabelColor'    => $type_string,
			'labelErrorColor'       => $type_string,
			'buttonSize'            => $type_string,
			'buttonBorderStyle'     => $type_string,
			'buttonBorderSize'      => $type_string,
			'buttonBorderRadius'    => $type_string,
			'buttonBackgroundColor' => $type_string,
			'buttonBorderColor'     => $type_string,
			'buttonTextColor'       => $type_string,
			'pageBreakColor'        => $type_string,
			'backgroundImage'       => $type_string,
			'backgroundPosition'    => $type_string,
			'backgroundRepeat'      => $type_string,
			'backgroundSizeMode'    => $type_string,
			'backgroundSize'        => $type_string,
			'backgroundWidth'       => $type_string,
			'backgroundHeight'      => $type_string,
			'backgroundUrl'         => $type_string,
			'backgroundColor'       => $type_string,
			'containerPadding'      => $type_string,
			'containerBorderStyle'  => $type_string,
			'containerBorderWidth'  => $type_string,
			'containerBorderColor'  => $type_string,
			'containerBorderRadius' => $type_string,
			'containerShadowSize'   => $type_string,
			'customCss'             => $type_string,
			'copyPasteJsonValue'    => $type_string,
		];

		$this->register_styles();

		/**
		 * Modify WPForms block attributes.
		 *
		 * @since 1.5.8.2
		 *
		 * @param array $attributes Attributes.
		 */
		$attributes = apply_filters( 'wpforms_gutenberg_form_selector_attributes', $attributes ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		$this->block_styles->declare_attributes( $attributes );

		register_block_type(
			'wpforms/form-selector',
			[
				'api_version'     => $this->get_block_api_version(),
				'attributes'      => $attributes,
				'style'           => 'wpforms-gutenberg-form-selector',
				'editor_style'    => 'wpforms-integrations',
				'render_callback' => [ $this, 'get_form_html' ],
			]
		);
	}

	/**
	 * Register WPForms Gutenberg block styles.
	 *
	 * @since 1.7.4.2
	 */
	protected function register_styles() {

		if ( ! is_admin() ) {
			return;
		}

		$min = wpforms_get_min_suffix();

		wp_register_style(
			'wpforms-integrations',
			WPFORMS_PLUGIN_URL . "assets/css/admin-integrations{$min}.css",
			[ 'dashicons' ],
			WPFORMS_VERSION
		);

		if ( $this->disable_css_setting === 3 ) {
			return;
		}

		$css_file = $this->disable_css_setting === 2 ? 'base' : 'full';
		$handle   = 'wpforms-gutenberg-form-selector';

		wp_register_style(
			$handle,
			WPFORMS_PLUGIN_URL . "assets/css/frontend/{$this->render_engine}/wpforms-{$css_file}{$min}.css",
			[ 'wp-edit-blocks', 'wpforms-integrations' ],
			WPFORMS_VERSION
		);

		// Add root CSS variables for the Modern Markup mode for full styles.
		if ( empty( $this->css_vars_obj ) || $this->render_engine !== 'modern' || $css_file !== 'full' ) {
			return;
		}

		wp_add_inline_style( $handle, $this->css_vars_obj->get_root_vars_css() );
	}

	/**
	 * Load WPForms Gutenberg block scripts.
	 *
	 * @since 1.4.8
	 */
	public function enqueue_block_editor_assets() {

		$min = wpforms_get_min_suffix();

		wp_enqueue_style( 'wpforms-integrations' );

		wp_set_script_translations( 'wpforms-gutenberg-form-selector', 'wpforms-lite' );

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

		// Support for the legacy form selector.
		// It is located in the common namespace.
		if ( $this->is_legacy_block() ) {
			wp_enqueue_script(
				'wpforms-gutenberg-form-selector',
				WPFORMS_PLUGIN_URL . "assets/js/integrations/gutenberg/formselector-legacy.es5{$min}.js",
				[ 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-data', 'wp-url', 'jquery' ],
				WPFORMS_VERSION,
				true
			);

			return;
		}

		if ( $this->render_engine === 'modern' ) {
			wp_enqueue_script(
				'wpforms-modern',
				WPFORMS_PLUGIN_URL . "assets/js/frontend/wpforms-modern{$min}.js",
				[ 'wpforms-gutenberg-form-selector' ],
				WPFORMS_VERSION,
				true
			);
		}
	}

	/**
	 * Whether the block is legacy.
	 *
	 * @since 1.8.8
	 */
	protected function is_legacy_block() {

		return ! wpforms_is_wp_version_at_least( '6.0' );
	}

	/**
	 * Get localize data.
	 *
	 * @since 1.8.1
	 *
	 * @return array
	 */
	public function get_localize_data(): array {

		$strings = [
			'title'                      => esc_html__( 'WPForms', 'wpforms-lite' ),
			'description'                => esc_html__( 'Select and display one of your forms.', 'wpforms-lite' ),
			'form_keywords'              => [
				esc_html__( 'form', 'wpforms-lite' ),
				esc_html__( 'contact', 'wpforms-lite' ),
				esc_html__( 'survey', 'wpforms-lite' ),
			],
			'form_select'                => esc_html__( 'Select a Form', 'wpforms-lite' ),
			'form_settings'              => esc_html__( 'Form Settings', 'wpforms-lite' ),
			'form_edit'                  => esc_html__( 'Edit Form', 'wpforms-lite' ),
			'form_entries'               => esc_html__( 'View Entries', 'wpforms-lite' ),
			'form_selected'              => esc_html__( 'Form', 'wpforms-lite' ),
			'show_title'                 => esc_html__( 'Show Title', 'wpforms-lite' ),
			'show_description'           => esc_html__( 'Show Description', 'wpforms-lite' ),
			'panel_notice_head'          => esc_html__( 'Heads up!', 'wpforms-lite' ),
			'panel_notice_text'          => esc_html__( 'Do not forget to test your form.', 'wpforms-lite' ),
			'panel_notice_link'          => esc_url( wpforms_utm_link( 'https://wpforms.com/docs/how-to-properly-test-your-wordpress-forms-before-launching-checklist/', 'gutenberg' ) ),
			'panel_notice_link_text'     => esc_html__( 'Check out our complete guide!', 'wpforms-lite' ),
			'learn_more'                 => esc_html__( 'Learn more', 'wpforms-lite' ),
			// Translators: %1$s: Opening strong tag, %2$s: Closing strong tag.
			'wpforms_empty_info'         => sprintf( esc_html__( 'You can use %1$sWPForms%2$s to build contact forms, surveys, payment forms, and more with just a few clicks.', 'wpforms-lite' ), '<strong>','</strong>' ),
			// Translators: %1$s: Opening anchor tag, %2$s: Closing anchor tag.
			'wpforms_empty_help'         => sprintf( esc_html__( 'Need some help? Check out our %1$scomprehensive guide.%2$s', 'wpforms-lite' ), '<a target="_blank" href="' . esc_url( wpforms_utm_link( 'https://wpforms.com/docs/creating-first-form/', 'gutenberg', 'Create Your First Form Documentation' ) ) . '">','</a>' ),
			'form_not_available_message' => esc_html__( 'It looks like the form you had selected is in the Trash or has been permanently deleted.', 'wpforms-lite' ),
		];

		$editor = wpforms()->obj( 'themes_editor' );

		return [
			'logo_url'          => WPFORMS_PLUGIN_URL . 'assets/images/wpforms-logo.svg',
			'block_preview_url' => WPFORMS_PLUGIN_URL . 'assets/images/integrations/gutenberg/block-preview.png',
			'block_empty_url'   => WPFORMS_PLUGIN_URL . 'assets/images/empty-states/no-forms.svg',
			'urls'              => [
				'form_url'    => admin_url( 'admin.php?page=wpforms-builder&view=fields&form_id={ID}' ),
				'entries_url' => admin_url( 'admin.php?view=list&page=wpforms-entries&form_id={ID}' ),
			],
			'forms'             => $this->get_form_list(),
			'strings'           => $strings,
			'canViewForms'      => $this->current_user_can_view_forms(),
			'canCreateForms'    => wpforms_current_user_can( 'create_forms' ),
			'isPro'             => wpforms()->is_pro(),
			// The attribute defaults the block declares; they must not change, or saved blocks would lose values.
			'defaults'          => self::DEFAULT_ATTRIBUTES,
			'is_full_styling'   => $this->render_engine === 'modern' && $this->disable_css_setting === 1,
			'wpforms_guide'     => esc_url( wpforms_utm_link( 'https://wpforms.com/docs/creating-first-form/', 'gutenberg', 'Create Your First Form Documentation' ) ),
			'get_started_url'   => esc_url( admin_url( 'admin.php?page=wpforms-builder' ) ),
			'themesEditor'      => $editor instanceof ThemesEditor ? $editor->get_support()->get_editor_launch_data() : Support::get_unavailable_launch_data(),
		];
	}

	/**
	 * Get the form list.
	 *
	 * @since 1.8.8
	 *
	 * @return array
	 * @noinspection NullPointerExceptionInspection
	 */
	public function get_form_list(): array {

		// Restrict the form list to users entitled to view forms.
		if ( ! $this->current_user_can_view_forms() ) {
			return [];
		}

		$forms = wpforms()->obj( 'form' )->get( '', [ 'order' => 'DESC' ] );

		if ( empty( $forms ) ) {
			return [];
		}

		return array_map( [ $this, 'get_form_list_item' ], $forms );
	}

	/**
	 * Get the form list item data exposed to the block editor.
	 *
	 * Only the data the block editor consumes is included. The form definition
	 * (fields, notifications, confirmations, integrations) must never reach the page source.
	 *
	 * @since 2.0.1
	 *
	 * @param object $form Form post object.
	 *
	 * @return array
	 */
	private function get_form_list_item( $form ): array {

		$max_length = 47;
		$title      = htmlspecialchars_decode( $form->post_title, ENT_QUOTES );
		$title      = trim( mb_substr( trim( $title ), 0, $max_length ) );
		$title      = mb_strlen( $title ) === $max_length ? $title . '…' : $title;
		$editor     = wpforms()->obj( 'themes_editor' );

		return [
			'ID'         => $form->ID,
			'post_title' => $title,
			'isLeadForm' => $editor instanceof ThemesEditor && $form instanceof WP_Post && $editor->get_support()->is_unstyled_lead_form_post( $form ),
			'canEdit'    => $editor instanceof ThemesEditor && $editor->can_edit( (int) $form->ID ),
		];
	}

	/**
	 * Determine whether the current user is entitled to view forms.
	 *
	 * See wpforms_current_user_can_view_forms() for the Lite and Pro difference.
	 *
	 * @since 2.0.1
	 *
	 * @return bool
	 */
	private function current_user_can_view_forms(): bool {

		return wpforms_current_user_can_view_forms();
	}

	/**
	 * Filter form action.
	 *
	 * @since 1.8.8
	 *
	 * @param string|mixed $action    Form action.
	 * @param array|mixed  $form_data Form data.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function form_action_filter( $action, $form_data ): string {

		if ( $this->is_gb_editor() ) {

			// Remove inappropriate form action URL that contains all the block attributes.
			$action = '';
		}

		return (string) $action;
	}

	/**
	 * Get form HTML to display in a WPForms Gutenberg block.
	 *
	 * @since 1.4.8
	 *
	 * @param array|mixed $attr Attributes passed by WPForms Gutenberg block.
	 *
	 * @return string
	 */
	public function get_form_html( $attr ): string {

		$attr = (array) $attr;

		$id = ! empty( $attr['formId'] ) ? absint( $attr['formId'] ) : 0;

		if ( empty( $id ) ) {
			return '';
		}

		// Core's block-renderer REST endpoint is open to anyone who can edit posts,
		// so the render must enforce the same gate as get_form_list().
		if ( $this->is_block_renderer_request() && ! $this->current_user_can_view_forms() ) {
			return '';
		}

		// The editor preview sends every declared attribute with its default; keep the saved shape only.
		$attr = $this->block_styles->strip_defaults( $attr );

		if ( $this->is_gb_editor() ) {
			$this->disable_fields_in_gb_editor();
		}

		$title = ! empty( $attr['displayTitle'] );
		$desc  = ! empty( $attr['displayDesc'] );

		$this->add_class_callback( $id, $attr );

		// Maybe override block attributes with the theme settings.
		$attr = $this->block_styles->resolve_attributes( $attr );

		$embed_context = wpforms()->obj( 'embed_context' );

		// The resolved attributes are the styles the block prints; the panel reads them, the render does not.
		if ( $embed_context instanceof EmbedContext ) {
			$embed_context->set(
				[
					'type'    => EmbedContext::TYPE_BLOCK,
					'id'      => (string) ( $attr['clientId'] ?? '' ),
					'form_id' => $id,
					'legacy'  => $attr,
				]
			);
		}

		// Get block content.
		$content = $this->get_content( $id, $title, $desc, $attr );

		// phpcs:disable WPForms.PHP.ValidateHooks.InvalidHookName

		/**
		 * Filter Gutenberg block content.
		 *
		 * @since 1.5.8.2
		 *
		 * @param string $content Block content.
		 * @param int    $id      Form id.
		 */
		return (string) apply_filters( 'wpforms_gutenberg_block_form_content', $content, $id );

		// phpcs:enable WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Add class callback.
	 *
	 * @since 1.8.1
	 *
	 * @param int   $id   Form id.
	 * @param array $attr Form attributes.
	 *
	 * @return void
	 */
	private function add_class_callback( int $id, array $attr ): void { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		$class_callback = static function ( $classes, $form_data ) use ( $id, $attr ) {

			if ( (int) $form_data['id'] !== $id ) {
				return $classes;
			}

			$cls = [];

			// Add custom class to form container.
			if ( ! empty( $attr['className'] ) ) {
				$cls = array_map( 'esc_attr', explode( ' ', $attr['className'] ) );
			}

			// Add classes to identify that the form displays inside the block.
			$cls[] = 'wpforms-block';

			if ( ! empty( $attr['clientId'] ) ) {
				$cls[] = 'wpforms-block-' . $attr['clientId'];
			}

			return array_unique( array_merge( $classes, $cls ) );
		};

		if ( empty( $this->callbacks[ $id ] ) ) {
			add_filter( 'wpforms_frontend_container_class', $class_callback, 10, 2 );
		}

		$this->callbacks[ $id ][] = $class_callback;
	}

	/**
	 * Get content.
	 *
	 * @since 1.8.1
	 *
	 * @param int   $id    Form id.
	 * @param bool  $title Form title is not empty.
	 * @param bool  $desc  Form desc is not empty.
	 * @param array $attr  Form attributes.
	 *
	 * @return string
	 * @noinspection JSUnresolvedReference
	 */
	private function get_content( int $id, bool $title, bool $desc, array $attr ): string {

		/**
		 * Filter allow render block content flag.
		 *
		 * @since 1.8.8
		 *
		 * @param bool $allow_render Allow render flag. Defaults to `true`.
		 */
		$allow_render = (bool) apply_filters( 'wpforms_integrations_gutenberg_form_selector_allow_render', true );

		if ( ! $allow_render ) {
			return '';
		}

		ob_start();

		// phpcs:disable WPForms.PHP.ValidateHooks.InvalidHookName

		/**
		 * Fires before Gutenberg block output.
		 *
		 * @since 1.5.8.2
		 */
		do_action( 'wpforms_gutenberg_block_before' );

		/**
		 * Filter block title display flag.
		 *
		 * @since 1.5.8.2
		 *
		 * @param bool $title Title display flag.
		 * @param int  $id    Form id.
		 */
		$title = (bool) apply_filters( 'wpforms_gutenberg_block_form_title', $title, $id );

		/**
		 * Filter block description display flag.
		 *
		 * @since 1.5.8.2
		 *
		 * @param bool $desc Description display flag.
		 * @param int  $id   Form id.
		 */
		$desc = (bool) apply_filters( 'wpforms_gutenberg_block_form_desc', $desc, $id );

		$this->block_styles->print( $id, $attr );

		wpforms_display( $id, $title, $desc );

		/**
		 * Fires after Gutenberg block output.
		 *
		 * @since 1.5.8.2
		 */
		do_action( 'wpforms_gutenberg_block_after' );

		// phpcs:enable WPForms.PHP.ValidateHooks.InvalidHookName

		$content = (string) ob_get_clean();

		if ( ! $this->is_gb_editor() ) {
			return $content;
		}

		if ( empty( $content ) ) {
			return '<div class="components-placeholder"><div class="components-placeholder__label"></div>' .
						'<div class="components-placeholder__fieldset">' .
						esc_html__( 'The form cannot be displayed.', 'wpforms-lite' ) .
						'</div></div>';
		}

		/**
		 * Unfortunately, the inline 'script' tag cannot be executed in the GB editor.
		 * This is the hacky way to trigger custom event on form loaded in the Block Editor / GB / FSE.
		 */

		// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export
		$content .= sprintf(
			// language=JavaScript
			'<img src="data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==" onLoad="
				window.top.dispatchEvent(
					new CustomEvent(
						\'wpformsFormSelectorFormLoaded\',
						{
							detail: {
								formId: %1$s,
								title: %2$s,
								desc: %3$s,
								block: this.closest( \'.wp-block\' )
							}
						}
					)
				);
			" class="wpforms-pix-trigger" alt="">',
			absint( $id ),
			var_export( $title, true ),
			var_export( $desc, true )
		);

		// phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_var_export

		return $content;
	}

	/**
	 * Checking if is Gutenberg REST API call.
	 *
	 * @since 1.5.7
	 *
	 * @return bool True if is Gutenberg REST API call.
	 */
	public function is_gb_editor(): bool {

		// TODO: Find a better way to check if is GB editor API call.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return defined( 'REST_REQUEST' ) && REST_REQUEST && ! empty( $_REQUEST['context'] ) && $_REQUEST['context'] === 'edit';
	}

	/**
	 * Determine whether the current request is a REST block-renderer request.
	 *
	 * Core exposes dynamic blocks via the /wp/v2/block-renderer/ REST endpoint.
	 * The context query argument is not checked on purpose: it defaults to `view`
	 * and can be set freely by the requester, unlike the route itself.
	 *
	 * @since 2.0.1
	 *
	 * @return bool
	 */
	private function is_block_renderer_request(): bool {

		if ( ! wpforms_is_rest() ) {
			return false;
		}

		$rest_route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';

		return strpos( (string) $rest_route, '/block-renderer/' ) !== false;
	}

	/**
	 * Disable form fields if called from the Gutenberg editor.
	 *
	 * @since 1.7.5
	 *
	 * @return void
	 */
	private function disable_fields_in_gb_editor(): void { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		add_filter(
			'wpforms_frontend_container_class',
			static function ( $classes ) {

				$classes[] = 'wpforms-gutenberg-form-selector';

				return $classes;
			}
		);
		add_action(
			'wpforms_frontend_output',
			static function () {

				echo '<fieldset disabled>';
			},
			3
		);
		add_action(
			'wpforms_frontend_output',
			static function () {

				echo '</fieldset>';
			},
			30
		);
	}

	/**
	 * Disable loading media for the richtext editor for edit action to prevent script conflicts.
	 *
	 * @since 1.9.1
	 *
	 * @param bool|mixed $media_enabled Whether to enable media.
	 * @param array      $field         Field data.
	 *
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function disable_richtext_media( $media_enabled, array $field ): bool {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_REQUEST['action'] ) && $_REQUEST['action'] === 'edit' && is_admin() ) {
			return false;
		}

		return (bool) $media_enabled;
	}

	/**
	 * Get block API version based on WP core version.
	 *
	 * @since 1.9.3
	 *
	 * @return int Block API version.
	 */
	private function get_block_api_version(): int {

		if ( $this->is_legacy_block() ) {
			return 1;
		}

		return wpforms_is_wp_version_at_least( '6.3' ) ? 3 : 2;
	}
}
