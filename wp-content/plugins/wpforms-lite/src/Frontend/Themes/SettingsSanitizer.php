<?php

namespace WPForms\Frontend\Themes;

use WPForms\Frontend\CSSVars;

/**
 * Validate form theme settings on write.
 *
 * Values reach `<style>` blocks raw (`CSSVars::get_vars_css()`), so every type that
 * prints as a CSS variable is checked by a strict pattern.
 *
 * @since 2.0.5
 */
class SettingsSanitizer {

	/**
	 * Number with an optional px unit.
	 *
	 * @since 2.0.5
	 */
	const NUMBER_PATTERN = '/^(\d+(?:\.\d+)?)(px)?$/iD';

	/**
	 * Background size built from width and height.
	 *
	 * @since 2.0.5
	 */
	const SIZE_PATTERN = '/^\d+(?:\.\d+)?(?:px)? \d+(?:\.\d+)?(?:px)?$/iD';

	/**
	 * Schema, or null to use the registered one.
	 *
	 * @since 2.0.5
	 *
	 * @var Schema|null
	 */
	private $schema;

	/**
	 * Constructor.
	 *
	 * @since 2.0.5
	 *
	 * @param Schema|null $schema Schema, or null to use the registered one.
	 */
	public function __construct( ?Schema $schema = null ) {

		$this->schema = $schema;
	}

	/**
	 * Sanitize theme settings for a storage.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $input   Incoming settings.
	 * @param array  $stored  Settings stored before this write.
	 * @param string $storage Storage, `form`, `theme` or `surface`.
	 * @param string $context Surface context.
	 *
	 * @return array Only schema keys.
	 */
	public function sanitize( array $input, array $stored, string $storage, string $context = Schema::CONTEXT_DEFAULT ): array {

		$themes_schema = $this->get_schema();
		$css_vars      = wpforms()->obj( 'css_vars' );
		$fill          = $storage === Schema::STORAGE_THEME ? array_diff_key( $themes_schema->get_fill_defaults( $context ), array_flip( CSSVars::GRADIENT_KEYS ) ) : [];
		$result        = [];

		foreach ( $themes_schema->get_controls( $context ) as $key => $control ) {
			if ( ! $this->is_handled( $control, $storage ) ) {
				continue;
			}

			$value = $this->resolve_value( (string) $key, $control, $input, $stored, $fill, $storage, $context );

			if ( $value !== null ) {
				$result[ $key ] = $value;
			}
		}

		return $css_vars instanceof CSSVars ? $this->drop_derived_gradient( $result, $css_vars ) : $result;
	}

	/**
	 * Drop the gradient keys equal to the defaults derived from the button color.
	 *
	 * A stored derived value would stop following the button and outlive a change of the derivation rule.
	 *
	 * @since 2.0.5
	 *
	 * @param array   $settings Sanitized settings.
	 * @param CSSVars $css_vars CSS vars, the owner of the derivation.
	 *
	 * @return array
	 */
	private function drop_derived_gradient( array $settings, CSSVars $css_vars ): array {

		$themes_schema = $this->get_schema();
		$defaults      = $css_vars->add_gradient_defaults( array_diff_key( $settings, array_flip( CSSVars::GRADIENT_KEYS ) ) );

		foreach ( CSSVars::GRADIENT_KEYS as $key ) {
			if ( isset( $settings[ $key ] ) && $themes_schema->normalize_color( (string) $settings[ $key ] ) === $themes_schema->normalize_color( (string) $defaults[ $key ] ) ) {
				unset( $settings[ $key ] );
			}
		}

		return $settings;
	}

	/**
	 * Sanitize a set of custom themes before the custom themes file is written.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $custom_themes Custom themes, slug => [ name, settings ].
	 * @param array  $existing      Custom themes stored before this write.
	 * @param string $default_name  Stock default theme name, for the fallback name.
	 * @param array  $stock_slugs   Stock theme slugs a custom theme can never take.
	 *
	 * @return array
	 */
	public function sanitize_custom_themes( array $custom_themes, array $existing, string $default_name, array $stock_slugs = [] ): array {

		$sanitized = [];

		foreach ( $custom_themes as $slug => $theme ) {
			$slug = sanitize_key( $slug );

			// An empty slug cannot be selected or rendered, and a stock slug
			// would shadow the stock theme in every editor reading the file.
			if ( $slug === '' || in_array( $slug, $stock_slugs, true ) ) {
				continue;
			}

			$theme    = (array) $theme;
			$settings = is_array( $theme['settings'] ?? null ) ? $theme['settings'] : [];
			$stored   = (array) ( $existing[ $slug ]['settings'] ?? [] );

			$sanitized[ $slug ]['name']     = sanitize_text_field( $theme['name'] ?? 'Copy of ' . $default_name );
			$sanitized[ $slug ]['settings'] = $this->sanitize( $settings, $stored, Schema::STORAGE_THEME );
		}

		return $sanitized;
	}

	/**
	 * Get the registered sanitizer, or a fresh one when the Loader has not registered it.
	 *
	 * @since 2.0.5
	 *
	 * @return SettingsSanitizer
	 */
	public static function get_instance(): SettingsSanitizer {

		$registered = wpforms()->obj( 'themes_settings_sanitizer' );

		return $registered instanceof self ? $registered : new self();
	}

	/**
	 * Fill the empty colors that follow the button with the color they follow, so the panel shows what the page paints.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $settings Resolved theme and surface settings.
	 * @param string $context  Surface context.
	 *
	 * @return array
	 */
	public function fill_followed( array $settings, string $context ): array {

		$followed = $this->get_followed_keys( $context );

		if ( ! $followed ) {
			return $settings;
		}

		$colors = $this->get_followed_colors( $settings );

		foreach ( $followed as $key => $follows ) {
			if ( (string) ( $settings[ $key ] ?? '' ) === '' && isset( $colors[ $follows ] ) ) {
				$settings[ $key ] = $colors[ $follows ];
			}
		}

		return $settings;
	}

	/**
	 * Drop the colors equal to the button color they follow.
	 *
	 * A stored followed value would stop following the button, as a stored derived gradient would.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $surface Sanitized surface settings.
	 * @param array  $theme   Sanitized theme settings the button colors come from.
	 * @param string $context Surface context.
	 *
	 * @return array
	 */
	public function drop_followed( array $surface, array $theme, string $context ): array {

		$followed = $this->get_followed_keys( $context );

		if ( ! $followed ) {
			return $surface;
		}

		$themes_schema = $this->get_schema();
		$colors        = $this->get_followed_colors( $theme );

		foreach ( $followed as $key => $follows ) {
			if ( isset( $surface[ $key ], $colors[ $follows ] ) && $themes_schema->normalize_color( (string) $surface[ $key ] ) === $themes_schema->normalize_color( $colors[ $follows ] ) ) {
				unset( $surface[ $key ] );
			}
		}

		return $surface;
	}

	/**
	 * Get the controls of a context that follow a button color, key => what they follow.
	 *
	 * @since 2.0.5
	 *
	 * @param string $context Surface context.
	 *
	 * @return array
	 */
	private function get_followed_keys( string $context ): array {

		$follows = array_map(
			static function ( array $control ): string {

				return (string) $control['follows'];
			},
			$this->get_schema()->get_controls( $context )
		);

		return array_filter( $follows );
	}

	/**
	 * Get the colors a control can follow: the color the button is painted with, and the text readable on it.
	 *
	 * The panel derives the same pair in `getButtonFollowColors()`.
	 *
	 * @since 2.0.5
	 *
	 * @param array $settings Theme settings.
	 *
	 * @return array `button`, `button_text`.
	 */
	private function get_followed_colors( array $settings ): array {

		$css_vars = wpforms()->obj( 'css_vars' );

		if ( ! $css_vars instanceof CSSVars ) {
			return [];
		}

		$vars = array_merge( $css_vars->get_vars(), $css_vars->get_customized_css_vars( $settings ) );

		return [
			'button'      => (string) ( $vars['button-background-color'] ?? '' ),
			'button_text' => (string) ( $vars['button-text-color-alt'] ?? $vars['button-text-color'] ?? '' ),
		];
	}

	/**
	 * Get the schema.
	 *
	 * @since 2.0.5
	 *
	 * @return Schema
	 */
	private function get_schema(): Schema {

		if ( $this->schema === null ) {
			$registered   = wpforms()->obj( 'themes_schema' );
			$this->schema = $registered instanceof Schema ? $registered : new Schema();
		}

		return $this->schema;
	}

	/**
	 * Whether a storage holds a control.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $control Definition.
	 * @param string $storage Storage.
	 *
	 * @return bool
	 */
	private function is_handled( array $control, string $storage ): bool {

		if ( $storage === Schema::STORAGE_SURFACE ) {
			return $control['scope'] === 'surface';
		}

		if ( $storage === Schema::STORAGE_THEME ) {
			return $control['scope'] === 'theme';
		}

		return in_array( $control['scope'], [ 'theme', 'form' ], true );
	}

	/**
	 * Pick the incoming, stored or default value of one key and validate it.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key     Control key.
	 * @param array  $control Definition.
	 * @param array  $input   Incoming settings.
	 * @param array  $stored  Stored settings.
	 * @param array  $fill    Default fill for missing keys.
	 * @param string $storage Storage.
	 * @param string $context Surface context.
	 *
	 * @return string|null Null when the key stays out of the output.
	 */
	private function resolve_value( string $key, array $control, array $input, array $stored, array $fill, string $storage, string $context ): ?string {

		$themes_schema = $this->get_schema();

		if ( array_key_exists( $key, $input ) && $themes_schema->can_store( $key, $storage, $context ) ) {
			return $this->sanitize_value( $key, $input[ $key ], $control, $storage, $context );
		}

		if ( array_key_exists( $key, $stored ) ) {
			return $this->sanitize_stored_value( $key, $stored[ $key ], $control, $storage, $context );
		}

		return isset( $fill[ $key ] ) ? $themes_schema->to_storage( $key, $fill[ $key ], $storage, $context ) : null;
	}

	/**
	 * Keep a stored value: free text as is, everything else validated again.
	 *
	 * Text sanitizers can be lossy (`sanitize_text_field()` collapses whitespace), and a stored
	 * value must survive a save by a user who may not edit it.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key     Control key.
	 * @param mixed  $value   Stored value.
	 * @param array  $control Definition.
	 * @param string $storage Storage.
	 * @param string $context Surface context.
	 *
	 * @return string
	 */
	private function sanitize_stored_value( string $key, $value, array $control, string $storage, string $context ): string {

		if ( is_callable( $control['sanitize'] ) || ! in_array( $control['type'], [ 'text', 'textarea' ], true ) ) {
			return $this->sanitize_value( $key, $value, $control, $storage, $context );
		}

		return is_string( $value ) ? $value : $this->get_schema()->to_storage( $key, $control['default'], $storage, $context );
	}

	/**
	 * Validate one value; an invalid one becomes the key's default.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key     Control key.
	 * @param mixed  $value   Value.
	 * @param array  $control Definition.
	 * @param string $storage Storage.
	 * @param string $context Surface context.
	 *
	 * @return string
	 */
	private function sanitize_value( string $key, $value, array $control, string $storage, string $context ): string {

		$themes_schema = $this->get_schema();
		$default       = $themes_schema->to_storage( $key, $control['default'], $storage, $context );

		// CSSVars skips non-string values, so numbers from JSON must become strings.
		if ( is_int( $value ) || is_float( $value ) ) {
			$value = (string) $value;
		}

		if ( ! is_string( $value ) ) {
			return $default;
		}

		$clean = is_callable( $control['sanitize'] )
			? call_user_func( $control['sanitize'], $value, $key, $control )
			: $this->sanitize_by_type( $value, $control );

		return is_string( $clean ) ? $themes_schema->to_storage( $key, $clean, $storage, $context ) : $default;
	}

	/**
	 * Validate a value by its type.
	 *
	 * @since 2.0.5
	 *
	 * @param string $value   Value.
	 * @param array  $control Definition.
	 *
	 * @return string|null Null when invalid.
	 */
	private function sanitize_by_type( string $value, array $control ): ?string {

		switch ( $control['type'] ) {
			case 'select':
			case 'tiles':
				$clean = $this->sanitize_select( $value, $control );
				break;

			case 'number':
				$clean = $this->sanitize_number( $value, $control );
				break;

			case 'color':
				$clean = $this->sanitize_color( $value );
				break;

			case 'size':
				$clean = $value === 'cover' || preg_match( self::SIZE_PATTERN, $value ) ? $value : null;
				break;

			case 'flag':
				$clean = in_array( $value, [ '', 'true' ], true ) ? $value : null;
				break;

			default:
				$clean = $this->sanitize_wp_type( $value, $control['type'] );
				break;
		}

		return $clean;
	}

	/**
	 * Validate a select value against its options and accepted legacy values.
	 *
	 * @since 2.0.5
	 *
	 * @param string $value   Value.
	 * @param array  $control Definition.
	 *
	 * @return string|null
	 */
	private function sanitize_select( string $value, array $control ): ?string {

		$valid = array_merge( array_map( 'strval', array_keys( $control['options'] ) ), $control['accepts'] );

		return in_array( $value, $valid, true ) ? $value : null;
	}

	/**
	 * Validate a number with an optional px unit.
	 *
	 * @since 2.0.5
	 *
	 * @param string $value   Value.
	 * @param array  $control Definition.
	 *
	 * @return string|null
	 */
	private function sanitize_number( string $value, array $control ): ?string {

		// An empty number falls back to the default, as an empty color does; CSS would read it as 0.
		if ( $value === '' ) {
			return null;
		}

		if ( ! preg_match( self::NUMBER_PATTERN, $value, $matches ) || (float) $matches[1] < (float) $control['min'] ) {
			return null;
		}

		return $value;
	}

	/**
	 * Validate a color; hex3 is expanded because CSSVars cannot parse it.
	 *
	 * @since 2.0.5
	 *
	 * @param string $value Value.
	 *
	 * @return string|null
	 */
	private function sanitize_color( string $value ): ?string {

		$color = trim( $value );

		if ( preg_match( '/^#[0-9a-f]{3}$/iD', $color ) ) {
			return $this->get_schema()->normalize_color( $color );
		}

		$is_valid = $color === 'transparent'
			|| preg_match( Schema::HEX_PATTERN, $color )
			|| preg_match( Schema::BARE_HEX_PATTERN, $color )
			|| preg_match( Schema::RGBA_PATTERN, $color );

		return $is_valid ? $color : null;
	}

	/**
	 * Validate the types that need WordPress sanitizers.
	 *
	 * @since 2.0.5
	 *
	 * @param string $value Value.
	 * @param string $type  Type.
	 *
	 * @return string|null
	 */
	private function sanitize_wp_type( string $value, string $type ): ?string {

		switch ( $type ) {
			case 'url':
				$clean = $this->sanitize_url( $value );
				break;

			case 'slug':
				$clean = sanitize_key( $value ) === $value ? $value : null;
				break;

			case 'textarea':
				// Keeps `%xx` octets (data-URL SVG in Custom CSS), as the builder save does.
				$clean = wp_strip_all_tags( wp_check_invalid_utf8( $value ) );
				break;

			default:
				$clean = sanitize_text_field( $value );
				break;
		}

		return $clean;
	}

	/**
	 * Validate a background URL and write it as `url( <url> )`.
	 *
	 * The inner part must survive esc_url_raw() unchanged and carry no CSS delimiters.
	 *
	 * @since 2.0.5
	 *
	 * @param string $value Value.
	 *
	 * @return string|null
	 */
	private function sanitize_url( string $value ): ?string {

		$inner = trim( $value );

		if ( preg_match( '/^url\(\s*(.*?)\s*\)$/isD', $inner, $matches ) ) {
			$inner = $matches[1];
		}

		if ( $inner === '' ) {
			return 'url()';
		}

		if ( preg_match( '/^[\w.-]+\.(jpe?g|png|webp|gif)$/iD', $inner ) ) {
			return "url( {$inner} )";
		}

		if ( preg_match( '/[)"\'\s;{}<>\\\\]/', $inner ) || esc_url_raw( $inner ) !== $inner ) {
			return null;
		}

		return "url( {$inner} )";
	}
}
