<?php

namespace WPForms\Frontend;

use WPForms\Frontend\Themes\Schema;
use WPForms\Frontend\ThemesEditor\AdminBar;
use WPForms\Frontend\ThemesEditor\Data;
use WPForms\Frontend\ThemesEditor\Embeds;
use WPForms\Frontend\ThemesEditor\RestApi;
use WPForms\Frontend\ThemesEditor\Support;
use WPForms\Integrations\Gutenberg\ThemesData;

/**
 * Front-end Form Themes editor.
 *
 * Renders a floating "Form Themes" panel on pages containing forms, allowing
 * users with form edit access to preview theme changes live and save them.
 *
 * @since 2.0.5
 */
class ThemesEditor {

	/**
	 * Form setting recording that a theme was saved through this editor.
	 *
	 * Nearly every form carries a `themes` blob from builder saves, so the
	 * blob alone cannot tell a chosen theme from an untouched default.
	 * An integration with its own legacy styling keeps that styling until
	 * this setting is present.
	 *
	 * @since 2.0.5
	 */
	const APPLIED_KEY = 'themes_applied';

	/**
	 * Embeds collector.
	 *
	 * @since 2.0.5
	 *
	 * @var Embeds|null
	 */
	private $embeds;

	/**
	 * Whether the editor assets were enqueued.
	 *
	 * @since 2.0.5
	 *
	 * @var bool
	 */
	private $is_enqueued = false;

	/**
	 * Themes data of the running tier, set by the tier subclass.
	 *
	 * @since 2.0.5
	 *
	 * @var ThemesData
	 */
	protected $themes_data_obj;

	/**
	 * Data object builder.
	 *
	 * @since 2.0.5
	 *
	 * @var Data|null
	 */
	private $data;

	/**
	 * Whether `add_data()` attached the data object to the editor script this request.
	 *
	 * The panel markup reads that global; rendering it without the data attached
	 * would leave the panel with nothing to run on.
	 *
	 * @since 2.0.5
	 *
	 * @var bool
	 */
	private $data_attached = false;

	/**
	 * Admin bar helper.
	 *
	 * @since 2.0.5
	 *
	 * @var AdminBar|null
	 */
	private $admin_bar;

	/**
	 * Support checks helper.
	 *
	 * @since 2.0.5
	 *
	 * @var Support|null
	 */
	private $support;

	/**
	 * Initialize.
	 *
	 * @since 2.0.5
	 *
	 * @return void
	 */
	public function init(): void {

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 2.0.5
	 *
	 * @return void
	 */
	private function hooks(): void {

		add_action( 'rest_api_init', [ new RestApi( $this ), 'register_routes' ] );
		add_filter( 'wpforms_save_form_args', [ $this, 'preserve_theme_settings' ], 20, 3 );

		if ( is_admin() ) {
			return;
		}

		add_action( 'wpforms_frontend_output', [ $this->get_embeds(), 'collect_form' ], 4 );
		add_action( 'wpforms_wp_footer', [ $this, 'add_data' ] );
		add_action( 'wpforms_wp_footer_end', [ $this, 'render_editor' ] );

		// After the core WPForms admin bar menu (999), so the item lands to its right.
		add_action( 'admin_bar_menu', [ $this->get_admin_bar(), 'register_item' ], 1000 );
		add_action( 'wp_enqueue_scripts', [ $this->get_admin_bar(), 'hide_pending_item' ] );
	}

	/**
	 * Whether the current user can edit the form's theme.
	 *
	 * Maps form meta caps first, which admin-only access hooks leave off on the
	 * front end and in REST.
	 *
	 * @since 2.0.5
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return bool
	 */
	public function can_edit( int $form_id ): bool {

		if ( ! is_user_logged_in() ) {
			return false;
		}

		$access = wpforms()->obj( 'access' );

		if ( $access ) {
			$access->enable_meta_caps();
		}

		return wpforms_current_user_can( 'edit_form_single', $form_id );
	}

	/**
	 * Whether the site configuration supports the Themes engine.
	 *
	 * Themes require Modern Markup and full "Base and Form Theme Styling".
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	public function is_supported(): bool {

		$is_supported = $this->get_support()->is_modern_engine() && $this->get_support()->is_full_styling();

		/**
		 * Filter whether the front-end themes editor is supported.
		 *
		 * An integration rendering forms with the classic engine can opt in
		 * when it bridges the theme variables itself.
		 *
		 * @since 2.0.5
		 *
		 * @param bool $is_supported Whether the editor is supported.
		 */
		return (bool) apply_filters( 'wpforms_frontend_themes_editor_is_supported', $is_supported );
	}

	/**
	 * Whether the editor can style the given form.
	 *
	 * @since 2.0.5
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return bool
	 */
	public function is_form_supported( array $form_data ): bool {

		$checks       = $this->get_support();
		$is_supported = $this->is_supported()
			&& ! $checks->is_lead_form( $form_data )
			&& ! $checks->is_conversational_form( $form_data )
			&& ! $checks->is_form_page_form( $form_data );

		/**
		 * Filter whether the front-end themes editor can style the given form.
		 *
		 * Unlike `wpforms_frontend_themes_editor_is_supported`, carries the form
		 * context, so integrations owning the styling of particular forms can
		 * exclude them. Lead Forms, Conversational Forms and Form Pages are excluded by
		 * default and their addons lift the exclusion when they bridge the theme variables.
		 *
		 * @since 2.0.5
		 *
		 * @param bool  $is_supported Whether the editor can style the form.
		 * @param array $form_data    Form data and settings.
		 */
		return (bool) apply_filters( 'wpforms_frontend_themes_editor_is_form_supported', $is_supported, $form_data );
	}

	/**
	 * Get the support checks helper.
	 *
	 * @since 2.0.5
	 *
	 * @return Support
	 */
	public function get_support(): Support {

		if ( $this->support === null ) {
			$this->support = new Support( $this );
		}

		return $this->support;
	}

	/**
	 * Get the front-end editor URL for a form.
	 *
	 * The `wpforms_themes_editor` argument opens the panel: `1` on the page's first
	 * editable item, an embed key on that embed, which is what the "Edit Theme"
	 * buttons of the block editor, Elementor and Divi pass on the host page URL.
	 *
	 * @since 2.0.5
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return string
	 */
	public function get_editor_url( int $form_id ): string {

		/**
		 * Filter the page a form's theme is edited on.
		 *
		 * An integration rendering the form on its own page returns that page.
		 *
		 * @since 2.0.5
		 *
		 * @param string $url     Page URL, the form preview by default.
		 * @param int    $form_id Form ID.
		 */
		$url = (string) apply_filters( 'wpforms_frontend_themes_editor_get_editor_url', wpforms_get_form_preview_url( $form_id ), $form_id );

		return (string) add_query_arg( 'wpforms_themes_editor', '1', $url );
	}

	/**
	 * Keep the stored theme settings authoritative on Form Builder saves.
	 *
	 * An open builder tab still posts the theme fields it loaded, which would revert a front-end save.
	 *
	 * @since 2.0.5
	 *
	 * @param array|mixed $form      Post data to be saved.
	 * @param array       $form_data Form data.
	 * @param array       $args      Update arguments.
	 *
	 * @return array
	 */
	public function preserve_theme_settings( $form, array $form_data, array $args ): array {

		$form = (array) $form;

		if ( ( $args['context'] ?? '' ) !== 'save_form' || empty( $form['ID'] ) || empty( $form['post_content'] ) ) {
			return $form;
		}

		$form_handler = wpforms()->obj( 'form' );
		$stored       = $form_handler ? $form_handler->get( $form['ID'], [ 'content_only' => true ] ) : false;
		$content      = json_decode( wp_unslash( $form['post_content'] ), true );

		// A stored form the user cannot read has no theme to restore, so keep what the builder sent.
		if ( ! is_array( $stored ) || ! is_array( $content ) ) {
			return $form;
		}

		$surface_keys = array_filter( array_keys( (array) ( $stored['settings'] ?? [] ) + (array) ( $content['settings'] ?? [] ) ), [ Schema::class, 'is_surface_key' ] );

		foreach ( array_merge( [ 'themes', self::APPLIED_KEY ], $surface_keys ) as $key ) {
			if ( isset( $stored['settings'][ $key ] ) ) {
				$content['settings'][ $key ] = $stored['settings'][ $key ];
			} else {
				unset( $content['settings'][ $key ] );
			}
		}

		$form['post_content'] = wpforms_encode( $content );

		return $form;
	}

	/**
	 * Get the themes data of the running tier.
	 *
	 * @since 2.0.5
	 *
	 * @return ThemesData
	 */
	public function get_themes_data(): ThemesData {

		return $this->themes_data_obj;
	}

	/**
	 * Get the embeds collector.
	 *
	 * @since 2.0.5
	 *
	 * @return Embeds
	 */
	public function get_embeds(): Embeds {

		if ( ! $this->embeds ) {
			$this->embeds = new Embeds( $this );
		}

		return $this->embeds;
	}

	/**
	 * Get the rendered forms the current user can edit.
	 *
	 * @since 2.0.5
	 *
	 * @return array Form ID => form data.
	 */
	public function get_forms(): array {

		return $this->get_embeds()->get_forms();
	}

	/**
	 * Get the admin bar helper.
	 *
	 * @since 2.0.5
	 *
	 * @return AdminBar
	 */
	public function get_admin_bar(): AdminBar {

		if ( $this->admin_bar === null ) {
			$this->admin_bar = new AdminBar( $this );
		}

		return $this->admin_bar;
	}

	/**
	 * Enqueue the editor assets.
	 *
	 * Public so an integration rendering forms on its own page can enqueue them.
	 *
	 * @since 2.0.5
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {

		if ( $this->is_enqueued ) {
			return;
		}

		$this->is_enqueued = true;

		$min = wpforms_get_min_suffix();

		// The editor icons; the shared handle avoids a duplicate on pages
		// where an integration already loads it.
		wp_enqueue_style(
			'wpforms-font-awesome',
			WPFORMS_PLUGIN_URL . 'assets/lib/font-awesome/css/all.min.css',
			[],
			'7.0.1'
		);

		wp_enqueue_style(
			'minicolors',
			WPFORMS_PLUGIN_URL . 'assets/lib/jquery.minicolors/jquery.minicolors.min.css',
			[],
			'2.3.6'
		);

		wp_enqueue_script(
			'minicolors',
			WPFORMS_PLUGIN_URL . 'assets/lib/jquery.minicolors/jquery.minicolors.min.js',
			[ 'jquery' ],
			'2.3.6',
			true
		);

		// Registered for admin screens only, so the front end enqueues it here.
		wp_enqueue_style(
			'jquery-confirm',
			WPFORMS_PLUGIN_URL . 'assets/lib/jquery.confirm/jquery-confirm.min.css',
			[],
			'1.0.0'
		);

		wp_enqueue_script(
			'jquery-confirm',
			WPFORMS_PLUGIN_URL . 'assets/lib/jquery.confirm/jquery-confirm.min.js',
			[ 'jquery' ],
			'1.0.0',
			true
		);

		wp_enqueue_style(
			'wpforms-themes-editor',
			WPFORMS_PLUGIN_URL . "assets/css/frontend/themes-editor{$min}.css",
			[ 'minicolors' ],
			WPFORMS_VERSION
		);

		// A collected form reveals the admin bar item registered hidden in AdminBar;
		// below 783px core hides the bar items and the item stays hidden with them.
		wp_add_inline_style( 'wpforms-themes-editor', '@media screen and (min-width: 783px){#wpadminbar #wp-admin-bar-wpforms-themes-editor.' . AdminBar::PENDING_CLASS . '{display:block}}' );

		wp_enqueue_script(
			'wpforms-themes-common',
			WPFORMS_PLUGIN_URL . "assets/js/share/themes-common{$min}.js",
			[],
			WPFORMS_VERSION,
			true
		);

		// Core enqueues it only when its footer scripts load, after a block theme has printed the head.
		wp_enqueue_script(
			'wpforms-generic-utils',
			WPFORMS_PLUGIN_URL . "assets/js/share/utils{$min}.js",
			[ 'jquery' ],
			WPFORMS_VERSION,
			! wpforms_is_frontend_js_header_force_load()
		);

		wp_enqueue_script(
			'wpforms-themes-editor',
			WPFORMS_PLUGIN_URL . "assets/js/frontend/themes-editor{$min}.js",
			[ 'jquery', 'minicolors', 'jquery-confirm', 'wpforms-generic-utils', 'wpforms-themes-common' ],
			WPFORMS_VERSION,
			true
		);

		$education = $this->get_data()->get_education();

		if ( $education ) {
			$education->enqueue();
		}
	}

	/**
	 * Get the surface context of a form.
	 *
	 * @since 2.0.5
	 *
	 * @param array $form_data Form data.
	 *
	 * @return string
	 */
	public function get_context( array $form_data ): string {

		/**
		 * Filter the schema context a form is edited in.
		 *
		 * An integration owning a page returns its own context there, so its
		 * sections, controls and defaults apply and its keys survive the save.
		 * Any other page keeps `default`: the same form can also be a block or
		 * a shortcode elsewhere. The save takes the context the panel sends,
		 * checked against {@see get_contexts()}.
		 *
		 * @since 2.0.5
		 *
		 * @param string $context   Context, `default` in core.
		 * @param array  $form_data Form data.
		 */
		$context = sanitize_key( (string) apply_filters( 'wpforms_frontend_themes_editor_get_context', Schema::CONTEXT_DEFAULT, $form_data ) );

		return $context !== '' ? $context : Schema::CONTEXT_DEFAULT;
	}

	/**
	 * Get the surface contexts a form may be saved in.
	 *
	 * The page decides which context it edits in; the save has no page, so it
	 * takes the context the panel sends when this list names it.
	 *
	 * @since 2.0.5
	 *
	 * @param array $form_data Form data.
	 *
	 * @return array Context slugs, `default` always among them.
	 */
	public function get_contexts( array $form_data ): array {

		/**
		 * Filter the schema contexts a form may be saved in.
		 *
		 * An integration adds its context for the forms that have its mode on,
		 * from the form data alone: the save runs over REST, without the page.
		 *
		 * @since 2.0.5
		 *
		 * @param array $contexts  Contexts, `default` in core.
		 * @param array $form_data Form data.
		 */
		$contexts = (array) apply_filters( 'wpforms_frontend_themes_editor_get_contexts', [ Schema::CONTEXT_DEFAULT ], $form_data );
		$contexts = array_filter( array_map( 'sanitize_key', array_map( 'strval', $contexts ) ) );

		return array_values( array_unique( array_merge( [ Schema::CONTEXT_DEFAULT ], $contexts ) ) );
	}

	/**
	 * Attach the data object to the editor script.
	 *
	 * Runs on `wpforms_wp_footer`, before the footer scripts print.
	 *
	 * @since 2.0.5
	 *
	 * @return void
	 */
	public function add_data(): void {

		if ( empty( $this->get_forms() ) ) {
			return;
		}

		$json = wp_json_encode( $this->get_data()->get() );

		if ( $json === false ) {
			return;
		}

		wp_add_inline_script( 'wpforms-themes-editor', 'var wpforms_themes_editor = ' . $json . ';', 'before' );

		$this->data_attached = true;
	}

	/**
	 * Get the data object builder.
	 *
	 * @since 2.0.5
	 *
	 * @return Data
	 */
	public function get_data(): Data {

		if ( $this->data === null ) {
			$this->data = $this->create_data();
		}

		return $this->data;
	}

	/**
	 * Create the data object builder of the running tier.
	 *
	 * @since 2.0.5
	 *
	 * @return Data
	 */
	protected function create_data(): Data {

		return new Data( $this );
	}

	/**
	 * Render the editor markup.
	 *
	 * @since 2.0.5
	 *
	 * @return void
	 */
	public function render_editor(): void {

		$forms = $this->get_forms();

		if ( empty( $forms ) || ! $this->data_attached ) {
			return;
		}

		// The markup carries the controls of every context on the page; the panel hides them per item.
		$contexts = array_map( [ $this, 'get_context' ], array_values( $forms ) );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wpforms_render(
			'frontend/themes-editor',
			[
				'sections'       => $this->get_data()->get_sections( $contexts ),
				'multiple_forms' => count( $forms ) > 1 || count( $this->get_embeds()->all() ) > 1,
			],
			true
		);
	}
}
