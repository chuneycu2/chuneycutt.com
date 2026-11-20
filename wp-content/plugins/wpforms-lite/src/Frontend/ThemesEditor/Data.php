<?php

namespace WPForms\Frontend\ThemesEditor;

use WPForms\Forms\Fields\Pagebreak\Field as PagebreakField;
use WPForms\Frontend\CSSVars;
use WPForms\Frontend\EmbedContext;
use WPForms\Frontend\Themes\Schema;
use WPForms\Frontend\Themes\SettingsSanitizer;
use WPForms\Frontend\ThemesEditor;

/**
 * Data object of the front-end themes editor.
 *
 * @since 2.0.5
 */
class Data {

	/**
	 * Pro tier states: the Pro sections are editable, or locked behind the upgrade or the license modal.
	 *
	 * @since 2.0.5
	 */
	public const PRO_TIER_AVAILABLE = 'available';
	public const PRO_TIER_UPGRADE   = 'upgrade';
	public const PRO_TIER_LICENSE   = 'license';

	/**
	 * Editor.
	 *
	 * @since 2.0.5
	 *
	 * @var ThemesEditor
	 */
	protected $editor;

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
	 * Get the data object passed to the panel JS.
	 *
	 * @since 2.0.5
	 *
	 * @return array
	 */
	public function get(): array {

		/**
		 * Filter the front-end themes editor data object.
		 *
		 * Integrations add the data their page needs in the panel JS.
		 *
		 * @since 2.0.5
		 *
		 * @param array $data Data object.
		 */
		return (array) apply_filters( 'wpforms_frontend_themes_editor_data_get', $this->get_raw() );
	}

	/**
	 * Build the data object; the Pro subclass extends it.
	 *
	 * @since 2.0.5
	 *
	 * @return array
	 */
	protected function get_raw(): array {

		$forms            = [];
		$schema           = [];
		$none_keeps_color = false;

		// A theme stored as None plus a color must reach a Form Page panel as Split, not Solid.
		foreach ( $this->editor->get_forms() as $form_id => $form_data ) {
			$context = $this->editor->get_context( $form_data );

			$forms[ $form_id ] = [
				'title'    => $form_data['settings']['form_title'] ?? "#{$form_id}",
				'context'  => $context,
				'settings' => self::add_page_break_color_fallback( $this->get_current_settings( $form_data ), $form_data ),
				'isLegacy' => $this->is_legacy( $form_data ),
			];

			$schema[ $context ] = $schema[ $context ] ?? $this->get_schema()->to_js( $context );
			$none_keeps_color   = $none_keeps_color || $this->get_schema()->none_keeps_color( $context );
		}

		$embeds = $this->get_embeds();

		return [
			'rest'          => [
				'url'   => rest_url( 'wpforms/v1/' ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			],
			'schema'        => $schema,
			'forms'         => $forms,
			'embeds'        => $embeds,
			'formPages'     => $this->get_form_pages(),
			'themes'        => [
				'wpforms' => $this->with_resolved_defaults( $this->get_themes(), $none_keeps_color ),
				'custom'  => $this->with_resolved_defaults( $this->editor->get_themes_data()->get_custom_themes(), $none_keeps_color ),
			],
			'canSaveThemes' => $this->can_save_themes(),
			'proTier'       => $this->get_pro_tier(),
			'badge'         => [
				'clicked' => $this->editor->get_admin_bar()->is_clicked(),
			],
			'sizes'         => [
				'field-size'            => CSSVars::FIELD_SIZE,
				'label-size'            => CSSVars::LABEL_SIZE,
				'button-size'           => CSSVars::BUTTON_SIZE,
				'container-shadow-size' => CSSVars::CONTAINER_SHADOW_SIZE,
			],
			'strings'       => $this->get_strings(),
			'modules'       => $this->get_modules(),
		];
	}

	/**
	 * Get the editor JS modules the loader imports, in merge order.
	 *
	 * Each URL carries the plugin version itself: a dynamic import bypasses
	 * `wp_enqueue_script()`, and modules never import each other, since a
	 * nested import would load without the version and survive updates in caches.
	 *
	 * @since 2.0.5
	 *
	 * @return array
	 */
	private function get_modules(): array {

		$min      = wpforms_get_min_suffix();
		$manifest = [];
		$modules  = [
			'state'       => 'state',
			'panel'       => 'panel',
			'themes'      => 'themes',
			'controls'    => 'controls',
			'preview'     => 'preview',
			'colorpicker' => 'colorpicker',
			'save'        => 'save',
			'api'         => 'api',
			'stockPhotos' => 'stock-photos',
			'upsell'      => 'upsell',
		];

		foreach ( $modules as $name => $file ) {
			$manifest[] = [
				'name' => $name,
				'path' => add_query_arg( 'ver', WPFORMS_VERSION, WPFORMS_PLUGIN_URL . "assets/js/frontend/themes-editor/modules/{$file}{$min}.js" ),
			];
		}

		return $manifest;
	}

	/**
	 * Get the resolved current theme settings of a form.
	 *
	 * Mirrors CSSVars::maybe_override_attributes(): a known theme slug wins
	 * over individually saved keys, so the controls reflect the rendered state.
	 *
	 * @since 2.0.5
	 *
	 * @param array $form_data   Form data.
	 * @param bool  $apply_theme Whether the theme slug overrides the saved keys; off for values the embed already resolved.
	 *
	 * @return array
	 */
	public function get_current_settings( array $form_data, bool $apply_theme = true ): array {

		$context        = $this->editor->get_context( $form_data );
		$themes_data    = $this->editor->get_themes_data();
		$wpforms_themes = $themes_data->get_wpforms_themes();
		$css_vars       = wpforms()->obj( 'css_vars' );
		$saved          = $this->get_stored_theme( $form_data, $context );
		$slug           = $themes_data->resolve_slug( (string) ( $saved['wpformsTheme'] ?? 'default' ) );
		$theme          = $themes_data->get_theme( $slug );
		$settings       = $saved;

		// Mirrors CSSVars::maybe_override_attributes(): only an explicitly
		// chosen theme slug wins over individually saved keys. A form with
		// no theme chosen yet keeps whatever keys it has saved.
		if ( $apply_theme && ! empty( $saved['wpformsTheme'] ) ) {
			$settings = array_merge( $settings, $theme['settings'] ?? [] );
		}

		// Gradient defaults follow the form's own button color, so they go in before the default theme.
		$settings = array_merge(
			$wpforms_themes['default']['settings'] ?? [],
			$css_vars->resolve_background_settings( $css_vars->add_gradient_defaults( $settings ), $this->get_schema()->none_keeps_color( $context ) )
		);

		$settings['wpformsTheme']  = $slug;
		$settings['isCustomTheme'] = empty( $wpforms_themes[ $slug ] ) && $theme ? 'true' : '';
		// An unknown slug (e.g. an imported form) keeps its stored name, the save sends it back.
		$settings['themeName'] = $theme['name'] ?? (string) ( $saved['themeName'] ?? '' );

		$surface  = $this->get_schema()->get_surface_defaults( $context );
		$settings = array_merge( $settings, $surface, array_intersect_key( (array) ( $form_data['settings'][ Schema::get_surface_key( $context ) ] ?? [] ), $surface ) );
		$settings = SettingsSanitizer::get_instance()->fill_followed( $settings, $context );

		/**
		 * Filter the resolved theme settings the panel shows for a form.
		 *
		 * @since 2.0.5
		 *
		 * @param array $settings  Resolved theme settings.
		 * @param array $form_data Form data.
		 */
		return (array) apply_filters( 'wpforms_frontend_themes_editor_data_get_current_settings', $settings, $form_data );
	}

	/**
	 * Whether a non-default context stores a theme of its own, not only its surface keys.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $form_data Form data.
	 * @param string $context   Surface context.
	 *
	 * @return bool
	 */
	public function has_own_theme( array $form_data, string $context ): bool {

		return (bool) $this->get_own_theme( $form_data, $context );
	}

	/**
	 * Get the stored theme of a form in a context: the context slot's own theme, or the form default.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $form_data Form data.
	 * @param string $context   Surface context.
	 *
	 * @return array
	 */
	public function get_stored_theme( array $form_data, string $context ): array {

		$own = $this->get_own_theme( $form_data, $context );

		// A slot with surface keys only follows the form default.
		$theme = $own ? $own : (array) ( $form_data['settings']['themes'] ?? [] );

		/**
		 * Filter the stored theme a form renders, edits and saves over in a context.
		 *
		 * @since 2.0.5
		 *
		 * @param array  $theme     The context slot's own theme, or the form default.
		 * @param array  $form_data Form data.
		 * @param string $context   Surface context.
		 */
		return (array) apply_filters( 'wpforms_frontend_themes_editor_data_get_stored_theme', $theme, $form_data, $context );
	}

	/**
	 * Get the theme keys a context slot stores beyond its surface keys.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $form_data Form data.
	 * @param string $context   Surface context.
	 *
	 * @return array
	 */
	private function get_own_theme( array $form_data, string $context ): array {

		if ( ! $this->get_schema()->is_theme_owner( $context ) ) {
			return [];
		}

		$slot = (array) ( $form_data['settings'][ Schema::get_surface_key( $context ) ] ?? [] );

		return array_diff_key( $slot, $this->get_schema()->get_surface_defaults( $context ) );
	}

	/**
	 * Keep the page indicator's field color until a theme is saved through the editor.
	 *
	 * Builder saves store the default page break color on nearly every form, so only the
	 * applied flag tells a chosen theme apart; the indicator follows the theme variable.
	 *
	 * @since 2.0.5
	 *
	 * @param array $settings  Resolved theme settings of the form default.
	 * @param array $form_data Form data.
	 *
	 * @return array
	 */
	public static function add_page_break_color_fallback( array $settings, array $form_data ): array {

		if ( ! empty( $form_data['settings'][ ThemesEditor::APPLIED_KEY ] ) ) {
			return $settings;
		}

		$pagebreak = wpforms_get_pagebreak_details( $form_data );
		$top       = $pagebreak['top'] ?? [];

		if ( empty( $top['indicator'] ) || $top['indicator'] === 'none' ) {
			return $settings;
		}

		// The same color the indicator renders with.
		$color = wpforms_sanitize_hex_color( $top['indicator_color'] ?? PagebreakField::get_default_indicator_color() );

		if ( $color !== '' ) {
			$settings['pageBreakColor'] = $color;
		}

		return $settings;
	}

	/**
	 * Get the stock themes offered in the panel.
	 *
	 * @since 2.0.5
	 *
	 * @return array
	 */
	private function get_themes(): array {

		/**
		 * Filter the stock themes offered by the panel.
		 *
		 * An integration trims themes that do not suit its page. A form saved with
		 * a removed theme still renders with it; only the list changes.
		 *
		 * @since 2.0.5
		 *
		 * @param array $themes Stock themes.
		 */
		return (array) apply_filters( 'wpforms_frontend_themes_editor_data_get_themes', $this->editor->get_themes_data()->get_wpforms_themes() );
	}

	/**
	 * Fill every theme's gradient keys and resolve its background style the way the settings get them.
	 *
	 * Themes predate the gradient keys, while the settings always carry them with
	 * a derived end color; a theme saved before the editor can hold a background
	 * color under the None style, which the settings render as Solid. Without this
	 * an untouched theme compares as modified.
	 *
	 * @since 2.0.5
	 *
	 * @param array $themes           Themes keyed by slug.
	 * @param bool  $none_keeps_color Whether None keeps its color on this page.
	 *
	 * @return array
	 */
	private function with_resolved_defaults( array $themes, bool $none_keeps_color = false ): array {

		$css_vars = wpforms()->obj( 'css_vars' );

		foreach ( $themes as &$theme ) {
			if ( is_array( $theme['settings'] ?? null ) ) {
				$theme['settings'] = $css_vars->resolve_background_settings( $css_vars->add_gradient_defaults( $theme['settings'] ), $none_keeps_color );
			}
		}

		unset( $theme );

		return $themes;
	}

	/**
	 * Whether a form still renders with legacy styling.
	 *
	 * @since 2.0.5
	 *
	 * @param array $form_data Form data.
	 *
	 * @return bool
	 */
	private function is_legacy( array $form_data ): bool {

		/**
		 * Filter whether a form still renders with legacy styling.
		 *
		 * An integration with its own legacy styling returns true until a theme is
		 * saved from the panel; the panel then shows no theme as selected.
		 *
		 * @since 2.0.5
		 *
		 * @param bool  $is_legacy Whether legacy styling is in effect.
		 * @param array $form_data Form data.
		 */
		return (bool) apply_filters( 'wpforms_frontend_themes_editor_data_is_legacy', false, $form_data );
	}

	/**
	 * Get the IDs of the forms collected on their own page, whose form default the panel edits.
	 *
	 * @since 2.0.5
	 *
	 * @return int[]
	 */
	private function get_form_pages(): array {

		$form_ids = [];

		foreach ( $this->editor->get_embeds()->all() as $embed ) {
			if ( empty( $embed['context']['key'] ) ) {
				$form_ids[] = (int) ( $embed['form_data']['id'] ?? 0 );
			}
		}

		return $form_ids;
	}

	/**
	 * Get the embeds on the page, in render order.
	 *
	 * Without the previewed form, which the panel edits as the form default.
	 *
	 * @since 2.0.5
	 *
	 * @return array
	 */
	private function get_embeds(): array {

		$embeds = [];

		foreach ( $this->editor->get_embeds()->all() as $key => $embed ) {
			if ( empty( $embed['context']['key'] ) ) {
				continue;
			}

			$embeds[] = $this->get_embed_item( (string) $key, (array) $embed['form_data'], (array) $embed['context'] );
		}

		return $embeds;
	}

	/**
	 * Build one embed of the data object.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key       Embed key.
	 * @param array  $form_data Form data.
	 * @param array  $context   Embed context.
	 *
	 * @return array
	 */
	private function get_embed_item( string $key, array $form_data, array $context ): array {

		$form_id   = (int) ( $form_data['id'] ?? 0 );
		$type      = (string) ( $context['type'] ?? EmbedContext::TYPE_SHORTCODE );
		$resolved  = wpforms()->obj( 'themes_editor_resolver' )->resolve( $form_id, $context, $form_data );
		$overrides = wpforms()->obj( 'themes_editor_overrides' );
		$block_css = trim( (string) ( $context['legacy']['customCss'] ?? '' ) );
		$is_owned  = $overrides instanceof Overrides && $overrides->owns_custom_css( $key, $form_id );

		// A block's Custom CSS is complete rules, and stays so in the override that takes it over.
		$has_block_css = $is_owned
			? ! empty( $overrides->get( $key )['blockCustomCss'] )
			: $type === EmbedContext::TYPE_BLOCK && $block_css !== '' && $overrides instanceof Overrides;

		// A block's own Custom CSS, from before the editor, shows in the field until a save takes it over.
		if ( $has_block_css && ! $is_owned ) {
			$resolved['settings']['customCss'] = $block_css;
		}

		return [
			'key'         => $key,
			'formId'      => $form_id,
			'type'        => $type,
			'label'       => $this->get_embed_label( $form_data, $type ),
			'selector'    => empty( $context['class'] ) ? '' : '.' . $context['class'],
			'settings'    => $resolved['settings'],
			'hasOverride' => (bool) $resolved['hasOverride'],
			'isLegacy'    => $resolved['source'] === Resolver::SOURCE_LEGACY,
			'canEdit'     => $this->editor->get_embeds()->can_override( $key, $form_id ),
			'blockCss'    => $has_block_css,
		];
	}

	/**
	 * Get the label of an embed for the form selector: form title and type name.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $form_data Form data.
	 * @param string $type      Embed type.
	 *
	 * @return string
	 */
	private function get_embed_label( array $form_data, string $type ): string {

		return sprintf(
			/* translators: %1$s - form title, %2$s - embed type name. */
			esc_html__( '%1$s (%2$s)', 'wpforms-lite' ),
			$form_data['settings']['form_title'] ?? '#' . (int) ( $form_data['id'] ?? 0 ),
			$this->get_type_label( $type )
		);
	}

	/**
	 * Get the translated name of an embed type for the form selector.
	 *
	 * @since 2.0.5
	 *
	 * @param string $type Embed type.
	 *
	 * @return string
	 */
	private function get_type_label( string $type ): string {

		$labels = [
			EmbedContext::TYPE_BLOCK     => esc_html__( 'Block', 'wpforms-lite' ),
			EmbedContext::TYPE_ELEMENTOR => 'Elementor',
			EmbedContext::TYPE_DIVI      => 'Divi',
			EmbedContext::TYPE_WIDGET    => esc_html__( 'Widget', 'wpforms-lite' ),
		];

		return $labels[ $type ] ?? esc_html__( 'Shortcode', 'wpforms-lite' );
	}

	/**
	 * Get the panel strings.
	 *
	 * @since 2.0.5
	 *
	 * @return array
	 */
	private function get_strings(): array {

		return [
			'theme_copy'               => esc_html__( 'Copy', 'wpforms-lite' ),
			'theme_custom'             => esc_html__( 'Custom Theme', 'wpforms-lite' ),
			'saved'                    => esc_html__( 'Saved!', 'wpforms-lite' ),
			'save_error'               => esc_html__( 'Could not save the changes. Please try again.', 'wpforms-lite' ),
			'unsaved'                  => esc_html__( 'You have unsaved changes. Are you sure you want to leave?', 'wpforms-lite' ),
			'theme_delete_title'       => esc_html__( 'Delete Form Theme', 'wpforms-lite' ),
			/* translators: %1$s - theme name. */
			'theme_delete_confirm'     => esc_html__( 'Are you sure you want to delete the %1$s theme?', 'wpforms-lite' ),
			'theme_delete_cant_undone' => esc_html__( 'This cannot be undone.', 'wpforms-lite' ),
			'theme_delete_yes'         => esc_html__( 'Yes, Delete', 'wpforms-lite' ),
			'copy_paste_error'         => esc_html__( 'There was an error parsing your JSON code. Please check your code and try again.', 'wpforms-lite' ),
			'uhoh'                     => esc_html__( 'Uh oh!', 'wpforms-lite' ),
			'close'                    => esc_html__( 'Close', 'wpforms-lite' ),
			'cancel'                   => esc_html__( 'Cancel', 'wpforms-lite' ),
			'collapse'                 => esc_html__( 'Collapse', 'wpforms-lite' ),
			'expand'                   => esc_html__( 'Expand', 'wpforms-lite' ),
			/* translators: %1$s - form selector label, %2$d - number of the embed among those with the same label. */
			'form_selector_number'     => esc_html__( '%1$s #%2$d', 'wpforms-lite' ),
			'button_background'        => esc_html__( 'Button Background', 'wpforms-lite' ),
			'button_text'              => esc_html__( 'Button Text', 'wpforms-lite' ),
			'field_label'              => esc_html__( 'Field Label', 'wpforms-lite' ),
			'field_sublabel'           => esc_html__( 'Field Sublabel', 'wpforms-lite' ),
			'field_border'             => esc_html__( 'Field Border', 'wpforms-lite' ),
			// The feature names of the upsell modals, as the builder named them.
			'pro_sections'             => [
				'background' => esc_html__( 'Background Styles', 'wpforms-lite' ),
				'container'  => esc_html__( 'Container Styles', 'wpforms-lite' ),
				'themes'     => esc_html__( 'Themes', 'wpforms-lite' ),
			],
		];
	}

	/**
	 * Get the Pro tier state of the current site.
	 *
	 * @since 2.0.5
	 *
	 * @return string One of the `PRO_TIER_*` constants.
	 */
	private function get_pro_tier(): string {

		if ( $this->get_schema()->is_pro_tier_available() ) {
			return self::PRO_TIER_AVAILABLE;
		}

		return wpforms()->is_pro() ? self::PRO_TIER_LICENSE : self::PRO_TIER_UPGRADE;
	}

	/**
	 * Get the education modals of the upsell, or null when the Pro sections are editable or education is off.
	 *
	 * @since 2.0.5
	 *
	 * @return Education|null
	 */
	public function get_education() {

		// The site-wide education kill switch, documented in Loader::populate_education().
		if ( ! apply_filters( 'wpforms_admin_education', true ) ) { // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName, WPForms.Comments.PHPDocHooks.RequiredHookDocumentation
			return null;
		}

		return $this->get_pro_tier() === self::PRO_TIER_AVAILABLE ? null : $this->create_education();
	}

	/**
	 * Create the education modals of the edition.
	 *
	 * @since 2.0.5
	 *
	 * @return Education
	 */
	protected function create_education(): Education {

		return new Education();
	}

	/**
	 * Get the panel sections of the contexts on a page, prepared for the template.
	 *
	 * Hidden sections and controls, and controls the user lacks the capability for,
	 * are left out; tier-locked controls stay and render disabled.
	 * Users who cannot save themes get the Themes list only, so no sections.
	 * With several contexts a control stays when any of them shows it, so the panel
	 * can hide it per item; a section takes its rows from the first context showing it.
	 *
	 * @since 2.0.5
	 *
	 * @param string|array $contexts Surface context, or the contexts of the page.
	 *
	 * @return array Id => [ label, rows, tier, locked ].
	 */
	public function get_sections( $contexts ): array {

		if ( ! $this->can_save_themes() ) {
			return [];
		}

		$contexts  = array_values( array_unique( array_map( 'strval', (array) $contexts ) ) );
		$sections  = [];
		$is_locked = $this->get_pro_tier() !== self::PRO_TIER_AVAILABLE;

		foreach ( $contexts as $context ) {
			foreach ( $this->get_schema()->get_sections( $context ) as $id => $section ) {
				$rows = isset( $sections[ $id ] ) || $section['hidden'] ? [] : $this->get_section_rows( $section['rows'], $contexts );

				if ( ! $rows ) {
					continue;
				}

				$sections[ $id ] = [
					'label'  => $section['label'],
					'rows'   => $rows,
					'tier'   => $section['tier'],
					'locked' => $is_locked && $section['tier'] === 'pro',
				];
			}
		}

		return $sections;
	}

	/**
	 * Whether the current user can save themes.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	private function can_save_themes(): bool {

		return current_user_can( 'manage_options' );
	}

	/**
	 * Get the non-empty rows of a section shown to the current user.
	 *
	 * @since 2.0.5
	 *
	 * @param array $rows     Rows of control keys.
	 * @param array $contexts Surface contexts on the page.
	 *
	 * @return array
	 */
	private function get_section_rows( array $rows, array $contexts ): array {

		$result = [];

		foreach ( $rows as $keys ) {
			$controls = $this->get_row_controls( (array) $keys, $contexts );

			if ( $controls ) {
				$result[] = $controls;
			}
		}

		return $result;
	}

	/**
	 * Get the controls of one row shown to the current user.
	 *
	 * @since 2.0.5
	 *
	 * @param array $keys     Control keys.
	 * @param array $contexts Surface contexts on the page.
	 *
	 * @return array
	 */
	private function get_row_controls( array $keys, array $contexts ): array {

		$schema   = $this->get_schema();
		$controls = [];

		foreach ( $keys as $key ) {
			foreach ( $contexts as $context ) {
				$control = $schema->get_control( (string) $key, $context );

				// The first context showing the control describes it.
				if ( $control && ! $control['hidden'] && ( $control['capability'] === '' || current_user_can( $control['capability'] ) ) ) {
					break;
				}

				$control = [];
			}

			if ( empty( $control ) ) {
				continue;
			}

			$controls[] = [
				'key'         => (string) $key,
				'type'        => $control['type'],
				'label'       => $control['label'],
				'options'     => $control['options'],
				'description' => $control['description'],
				'unit'        => $control['unit'],
				'tier'        => $control['tier'],
				'editable'    => $schema->can_edit( (string) $key, $context ),
			];
		}

		return $controls;
	}

	/**
	 * Get the registered schema, or a fresh one when the Loader has not registered it.
	 *
	 * @since 2.0.5
	 *
	 * @return Schema
	 */
	protected function get_schema(): Schema {

		$schema = wpforms()->obj( 'themes_schema' );

		return $schema instanceof Schema ? $schema : new Schema();
	}

	/**
	 * Get the controls known to a surface context.
	 *
	 * @since 2.0.5
	 *
	 * @param string $context Surface context.
	 *
	 * @return array
	 */
	public function get_schema_controls( string $context ): array {

		return $this->get_schema()->get_controls( $context );
	}
}
