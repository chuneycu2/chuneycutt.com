<?php

namespace WPForms\Integrations\Gutenberg;

use WPForms\Frontend\CSSVars;
use WPForms\Frontend\EmbedContext;
use WPForms\Frontend\ThemesEditor\Overrides;

/**
 * Print the theme CSS of a block: its own legacy styles, or the form default.
 *
 * @since 2.0.5
 */
class BlockStyles {

	/**
	 * Attributes that are not styles; `lock` and `metadata` are the ones WordPress adds to every block.
	 *
	 * @since 2.0.5
	 */
	private const STRUCTURAL = [ 'clientId', 'formId', 'displayTitle', 'displayDesc', 'className', 'preview', 'copyPasteJsonValue', 'lock', 'metadata' ];

	/**
	 * Themes data.
	 *
	 * @since 2.0.5
	 *
	 * @var ThemesData
	 */
	private $themes_data;

	/**
	 * CSS variables printer.
	 *
	 * @since 2.0.5
	 *
	 * @var CSSVars|null
	 */
	private $css_vars;

	/**
	 * Attribute defaults the block declares.
	 *
	 * @since 2.0.5
	 *
	 * @var array
	 */
	private $defaults;

	/**
	 * Attributes the block registers, including the ones addons declare.
	 *
	 * @since 2.0.5
	 *
	 * @var array
	 */
	private $declared = [];

	/**
	 * Constructor.
	 *
	 * @since 2.0.5
	 *
	 * @param ThemesData    $themes_data Themes data.
	 * @param CSSVars|mixed $css_vars    CSS variables printer.
	 * @param array         $defaults    Attribute defaults the block declares.
	 */
	public function __construct( ThemesData $themes_data, $css_vars, array $defaults ) {

		$this->themes_data = $themes_data;
		$this->css_vars    = $css_vars instanceof CSSVars ? $css_vars : null;
		$this->defaults    = $defaults;
	}

	/**
	 * Drop the style attributes that equal their default.
	 *
	 * The saved block comment never carries them, but the editor preview request does:
	 * `ServerSideRender` fills every declared attribute with its default. Dropping them
	 * gives both paths the saved shape, so the preview renders like the front end.
	 *
	 * @since 2.0.5
	 *
	 * @param array $attr Attributes passed by WPForms Gutenberg block.
	 *
	 * @return array
	 */
	public function strip_defaults( array $attr ): array {

		foreach ( $this->defaults as $key => $default ) {
			if ( ! in_array( $key, self::STRUCTURAL, true ) && array_key_exists( $key, $attr ) && $attr[ $key ] === $default ) {
				unset( $attr[ $key ] );
			}
		}

		return $attr;
	}

	/**
	 * Override block attributes with the settings of the block's theme.
	 *
	 * @since 2.0.5
	 *
	 * @param array $attr Attributes passed by WPForms Gutenberg block.
	 *
	 * @return array
	 */
	public function resolve_attributes( array $attr ): array {

		$theme_slug = (string) ( $attr['theme'] ?? '' );

		// Previously added blocks (FS 1.0) don't have the themeName attribute.
		// To preserve existing styling of such old blocks, we shouldn't override attributes.
		if ( ! isset( $attr['themeName'] ) || ( empty( $attr['themeName'] ) && $theme_slug === 'default' ) ) {
			return $attr;
		}

		if ( $theme_slug === '' ) {
			$theme_slug = $this->get_theme_slug( $attr );
		}

		$theme_data = $this->themes_data->get_theme( $theme_slug );

		// Theme doesn't exist, let's return.
		if ( ! $theme_data ) {
			return $attr;
		}

		// Override block attributes with the theme settings.
		return array_merge( $attr, $theme_data['settings'] );
	}

	/**
	 * Whether the block carries styles of its own: a theme, or any attribute beyond the structural ones.
	 *
	 * A block saved with the style panels has both; an FS 1.0 block has attributes only;
	 * a block inserted without them follows the form default.
	 *
	 * @since 2.0.5
	 *
	 * @param array $attr Attributes passed by WPForms Gutenberg block.
	 *
	 * @return bool
	 */
	private function has_own_styles( array $attr ): bool {

		if ( ! empty( $attr['theme'] ) || ! empty( $attr['themeName'] ) ) {
			return true;
		}

		// Only declared attributes count: other plugins register theirs on every block (Popup Maker's `openPopupId`).
		// Empty theme fields were just checked; the editor sends them as declared defaults.
		$own = array_intersect_key( $attr, $this->declared ? $this->declared : $this->defaults );

		return (bool) array_diff_key( $own, array_flip( array_merge( self::STRUCTURAL, [ 'theme', 'themeName' ] ) ) );
	}

	/**
	 * Remember the attributes the block registers, so foreign ones never count as styles.
	 *
	 * @since 2.0.5
	 *
	 * @param array $attributes Registered attributes, keyed by name.
	 */
	public function declare_attributes( array $attributes ): void {

		$this->declared = $attributes;
	}

	/**
	 * Print the block CSS before the form.
	 *
	 * @since 2.0.5
	 *
	 * @param int   $form_id Form ID.
	 * @param array $attr    Attributes passed by WPForms Gutenberg block.
	 */
	public function print( int $form_id, array $attr ): void {

		if ( ! $this->has_own_styles( $attr ) ) {
			// Nothing of its own: the block renders the form default exactly as the shortcode does.
			if ( $this->css_vars ) {
				$this->css_vars->output_css_vars_for_shortcode( [ 'id' => $form_id ] );
			}

			return;
		}

		$this->output_css_vars( $form_id, $attr );
		$this->output_form_custom_css( $form_id );
		$this->output_custom_css( $attr );
	}

	/**
	 * Get the theme slug.
	 *
	 * @since 2.0.5
	 *
	 * @param array $attr Attributes passed by WPForms Gutenberg block.
	 *
	 * @return string
	 */
	private function get_theme_slug( array $attr ): string {

		$form_handler = wpforms()->obj( 'form' );

		if ( ! $form_handler ) {
			return 'default';
		}

		$form_id   = (int) ( $attr['formId'] ?? 0 );
		$form_data = $form_handler->get( $form_id, [ 'content_only' => true ] );

		if ( empty( $form_data['settings']['themes']['wpformsTheme'] ) ) {
			return 'default';
		}

		return $form_data['settings']['themes']['wpformsTheme'];
	}

	/**
	 * Whether the modern markup with full styling is on, the precondition of every printer.
	 *
	 * An early exit only: `CSSVars` applies the same gate when it computes the variables.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	private function is_full_styling(): bool {

		return wpforms_get_render_engine() !== 'classic' && (int) wpforms_setting( 'disable-css', '1' ) === 1;
	}

	/**
	 * Output CSS variables for the particular form.
	 *
	 * @since 2.0.5
	 *
	 * @param int   $form_id Form ID.
	 * @param array $attr    Attributes passed by WPForms Gutenberg block.
	 */
	private function output_css_vars( int $form_id, array $attr ): void {

		if ( ! $this->css_vars || ! $this->is_full_styling() ) {
			return;
		}

		$vars = $this->css_vars->get_customized_css_vars( $attr );

		if ( empty( $vars ) ) {
			return;
		}

		$client_id = (string) ( $attr['clientId'] ?? '' );
		$style_id  = "#wpforms-css-vars-{$form_id}-block-{$client_id}";

		/**
		 * Filter the CSS selector for output CSS variables for styling the GB block form.
		 *
		 * @since 1.8.1
		 *
		 * @param string $selector The CSS selector for output CSS variables for styling the GB block form.
		 * @param array  $attr     Attributes passed by WPForms Gutenberg block.
		 * @param array  $css_vars CSS variables data.
		 */
		$vars_selector = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- Preserved from FormSelector for backward compatibility.
			'wpforms_integrations_gutenberg_form_selector_output_css_vars_selector',
			"#wpforms-{$form_id}.wpforms-block-{$client_id}",
			$attr,
			$vars
		);

		$style_id      = rtrim( $style_id, '-' );
		$vars_selector = rtrim( $vars_selector, '-' );

		$this->css_vars->output_selector_vars( $vars_selector, $vars, $style_id, $form_id );
	}

	/**
	 * Whether the embed's override replaced the block's own Custom CSS.
	 *
	 * The panel shows the block's Custom CSS in its field, and a save by someone who
	 * could edit it moves it into the override, where it can be changed or cleared.
	 *
	 * @since 2.0.5
	 *
	 * @param array $context Embed context the form render takes.
	 * @param int   $form_id Form ID.
	 *
	 * @return bool
	 */
	private function is_custom_css_taken_over( array $context, int $form_id ): bool {

		$overrides = wpforms()->obj( 'themes_editor_overrides' );

		if ( ! $overrides instanceof Overrides ) {
			return false;
		}

		return $overrides->owns_custom_css( (string) ( $context['key'] ?? '' ), $form_id );
	}

	/**
	 * Output custom CSS styles.
	 *
	 * @since 2.0.5
	 *
	 * @param array $attr Attributes passed by WPForms Gutenberg block.
	 */
	private function output_custom_css( array $attr ): void {

		if ( wpforms_get_render_engine() === 'classic' ) {
			return;
		}

		$custom_css    = trim( $attr['customCss'] ?? '' );
		$form_id       = (int) ( $attr['formId'] ?? 0 );
		$embed_context = wpforms()->obj( 'embed_context' );
		$context       = $embed_context instanceof EmbedContext ? $embed_context->peek( $form_id ) : [];

		if ( empty( $custom_css ) || $this->is_custom_css_taken_over( $context, $form_id ) ) {
			return;
		}

		$client_id = (string) ( $attr['clientId'] ?? '' );
		$style_id  = "#wpforms-custom-css-{$form_id}-block-{$client_id}";

		// The embed selector lets the themes editor preview replace this CSS with the field's.
		$selector = empty( $context['class'] ) ? '' : "#wpforms-{$form_id}.{$context['class']}";

		printf(
			'<style id="%1$s"%3$s>
				%2$s
			</style>',
			sanitize_key( $style_id ),
			wp_strip_all_tags( $custom_css ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$selector === '' ? '' : ' data-wpforms-custom-css="' . esc_attr( $selector ) . '"' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Output form-level custom CSS styles loaded from the form settings.
	 *
	 * Mirrors the shortcode render path so custom CSS configured in
	 * the Form Builder is also applied when the form is embedded via the Gutenberg block.
	 *
	 * @since 2.0.5
	 *
	 * @param int $form_id Form ID.
	 */
	private function output_form_custom_css( int $form_id ): void {

		static $outputted_forms = [];

		if ( wpforms_get_render_engine() === 'classic' || in_array( $form_id, $outputted_forms, true ) ) {
			return;
		}

		$form_handler = wpforms()->obj( 'form' );

		if ( ! $form_handler || ! $this->css_vars ) {
			return;
		}

		$form_data = $form_handler->get( $form_id, [ 'content_only' => true ] );

		if ( empty( $form_data ) ) {
			return;
		}

		$outputted_forms[] = $form_id;

		$this->css_vars->output_custom_css( (array) ( $form_data['settings']['themes'] ?? [] ), "#wpforms-{$form_id}", "wpforms-custom-css-{$form_id}-form" );
	}
}
