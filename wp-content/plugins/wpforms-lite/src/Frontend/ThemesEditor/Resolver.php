<?php

namespace WPForms\Frontend\ThemesEditor;

use WPForms\Frontend\EmbedContext;
use WPForms\Frontend\Themes\Schema;
use WPForms\Frontend\ThemesEditor;

/**
 * Resolve the theme settings an embed renders with: override, legacy, form default.
 *
 * @since 2.0.5
 */
class Resolver {

	/**
	 * Sources.
	 *
	 * @since 2.0.5
	 */
	const SOURCE_OVERRIDE = 'override';
	const SOURCE_LEGACY   = 'legacy';
	const SOURCE_FORM     = 'form';

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

		add_action( 'wpforms_frontend_output_form_before', [ $this, 'print_css' ] );

		// A confirmation after a page-reload submit renders without the form, so the override prints before it.
		add_action( 'wpforms_frontend_output_success', [ $this, 'print_css' ], 5 );
	}

	/**
	 * Resolve the settings of an embed.
	 *
	 * @since 2.0.5
	 *
	 * @param int   $form_id   Form ID.
	 * @param array $context   Embed context (`key`, `legacy`); empty on the form's own page.
	 * @param array $form_data Form data.
	 *
	 * @return array `settings`, `source`, `hasOverride`.
	 */
	public function resolve( int $form_id, array $context, array $form_data ): array {

		$data     = $this->get_editor()->get_data();
		$override = $this->get_override( (string) ( $context['key'] ?? '' ), $form_id );
		$legacy   = $this->get_legacy_settings( (array) ( $context['legacy'] ?? [] ), $this->get_editor()->get_context( $form_data ) );

		if ( $override ) {
			$form_data['settings']['themes'] = $override;
			$result                          = [
				'settings'    => $data->get_current_settings( $form_data ),
				'source'      => self::SOURCE_OVERRIDE,
				'hasOverride' => true,
			];
		} elseif ( $legacy ) {
			$form_data['settings']['themes'] = array_merge( (array) ( $form_data['settings']['themes'] ?? [] ), $legacy );
			// The integration resolved the legacy values the way the embed renders them; the slug must not override them again.
			$result = [
				'settings'    => $data->get_current_settings( $form_data, false ),
				'source'      => self::SOURCE_LEGACY,
				'hasOverride' => false,
			];
		} else {
			$result = [
				'settings'    => Data::add_page_break_color_fallback( $data->get_current_settings( $form_data ), $form_data ),
				'source'      => self::SOURCE_FORM,
				'hasOverride' => false,
			];
		}

		// The form's Custom CSS keeps printing from the form; an embed's own is added on top,
		// so an embed without an override starts with none.
		if ( ! $override && ! empty( $context['key'] ) ) {
			$result['settings']['customCss'] = '';
		}

		/**
		 * Filter the resolved theme settings of an embed.
		 *
		 * A surface addon adjusts the values every reader sees here.
		 *
		 * @since 2.0.5
		 *
		 * @param array $result    `settings`, `source`, `hasOverride`.
		 * @param int   $form_id   Form ID.
		 * @param array $context   Embed context.
		 * @param array $form_data Form data.
		 */
		return (array) apply_filters( 'wpforms_frontend_themes_editor_resolver_resolve', $result, $form_id, $context, $form_data );
	}

	/**
	 * Print the override of the embed being rendered, inside its container.
	 *
	 * Printed after every other printer, on a selector that beats theirs, with the
	 * full variable set, so an embed with an override shows the override only.
	 *
	 * @since 2.0.5
	 *
	 * @param array|mixed $form_data Form data.
	 *
	 * @return void
	 */
	public function print_css( $form_data ): void {

		$form_data     = (array) $form_data;
		$form_id       = (int) ( $form_data['id'] ?? 0 );
		$embed_context = wpforms()->obj( 'embed_context' );
		$context       = $embed_context instanceof EmbedContext ? $embed_context->current() : [];

		if ( ! $form_id || empty( $context['key'] ) || $form_id !== (int) ( $context['form_id'] ?? 0 ) ) {
			return;
		}

		// Most renders have no override: skip the resolve work for them.
		if ( ! $this->get_override( (string) $context['key'], $form_id ) ) {
			return;
		}

		$resolved = $this->resolve( $form_id, $context, $form_data );

		if ( empty( $resolved['hasOverride'] ) ) {
			return;
		}

		$css_vars = wpforms()->obj( 'css_vars' );
		$schema   = wpforms()->obj( 'themes_schema' );
		$settings = $schema instanceof Schema ? $schema->apply_pinned( $resolved['settings'], $this->get_editor()->get_context( $form_data ) ) : $resolved['settings'];
		$selector = "#wpforms-{$form_id}.{$context['class']}";
		$style_id = 'wpforms-embed-css-vars-' . substr( $context['class'], strlen( EmbedContext::CLASS_PREFIX ) );
		$vars     = $css_vars->add_css_vars_units( $css_vars->get_customized_css_vars( $settings, false ) );

		$overrides    = wpforms()->obj( 'themes_editor_overrides' );
		$is_block_css = $overrides instanceof Overrides && ! empty( $overrides->get( (string) $context['key'] )['blockCustomCss'] );

		$css_vars->output_selector_vars( $selector, $vars, $style_id, $form_id );
		$css_vars->output_custom_css( $resolved['settings'], $selector, $style_id, $is_block_css );
	}

	/**
	 * Keep the legacy values the schema knows; the block's `theme` is the form's `wpformsTheme`.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $legacy  Render-resolved block attributes or Elementor settings.
	 * @param string $context Surface context.
	 *
	 * @return array Empty when nothing is known; else `wpformsTheme` is the embed's own slug or ''.
	 */
	private function get_legacy_settings( array $legacy, string $context ): array {

		if ( isset( $legacy['theme'] ) && ! isset( $legacy['wpformsTheme'] ) ) {
			$legacy['wpformsTheme'] = $legacy['theme'];
		}

		$known = $this->get_editor()->get_data()->get_schema_controls( $context ) + [ 'wpformsTheme' => true ];
		$kept  = array_intersect_key( $legacy, $known );

		if ( ! $kept ) {
			return [];
		}

		// Only the embed's own slug: the stored form slug is the form default, not this embed's choice.
		$kept['wpformsTheme'] = (string) ( $kept['wpformsTheme'] ?? '' );

		return $kept;
	}

	/**
	 * Get the override settings of a key when it belongs to the form.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key     Embed key.
	 * @param int    $form_id Form ID.
	 *
	 * @return array
	 */
	private function get_override( string $key, int $form_id ): array {

		if ( $key === '' ) {
			return [];
		}

		$overrides = wpforms()->obj( 'themes_editor_overrides' );
		$item      = $overrides instanceof Overrides ? $overrides->get( $key ) : [];

		if ( $form_id !== (int) ( $item['formId'] ?? 0 ) || empty( $item['settings'] ) || ! is_array( $item['settings'] ) ) {
			return [];
		}

		return $item['settings'];
	}

	/**
	 * Get the editor.
	 *
	 * @since 2.0.5
	 *
	 * @return ThemesEditor
	 */
	private function get_editor(): ThemesEditor {

		return wpforms()->obj( 'themes_editor' );
	}
}
