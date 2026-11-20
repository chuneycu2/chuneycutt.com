<?php

namespace WPForms\Frontend\Themes;

/**
 * Form theme schema: one description of every theme control.
 *
 * @since 2.0.5
 */
class Schema {

	/**
	 * Core surface context.
	 *
	 * @since 2.0.5
	 */
	const CONTEXT_DEFAULT = 'default';

	/**
	 * Form `settings[themes]` storage, numbers unitless.
	 *
	 * @since 2.0.5
	 */
	const STORAGE_FORM = 'form';

	/**
	 * Custom theme storage, numbers with their unit.
	 *
	 * @since 2.0.5
	 */
	const STORAGE_THEME = 'theme';

	/**
	 * Surface `settings[{context}_themes]` storage: keys of an integration's own page, numbers unitless.
	 *
	 * @since 2.0.5
	 */
	const STORAGE_SURFACE = 'surface';

	/**
	 * Suffix of the form setting a context's surface keys are stored in.
	 *
	 * @since 2.0.5
	 */
	const SURFACE_KEY_SUFFIX = '_themes';

	/**
	 * Optional control fields and their defaults.
	 *
	 * @since 2.0.5
	 */
	const CONTROL_DEFAULTS = [
		'section'          => '',
		'label'            => '',
		'description'      => '',
		'type'             => 'text',
		'default'          => '',
		'options'          => [],
		'accepts'          => [],
		'unit'             => '',
		'min'              => 0,
		'scope'            => 'theme',
		'tier'             => 'lite',
		'capability'       => '',
		'css_var'          => '',
		'hidden'           => false,
		'stock'            => true,
		'enabled_if'       => [],
		'visible_if'       => [],
		'sanitize'         => null,
		'solid_default'    => '',
		'none_keeps_color' => false,
		'follows'          => '',
		// A value the context fixes for its output; settings and saves keep the theme's own.
		'pinned'           => null,
	];

	/**
	 * Optional section fields and their defaults.
	 *
	 * @since 2.0.5
	 */
	const SECTION_DEFAULTS = [
		'label'  => '',
		'tier'   => 'lite',
		'hidden' => false,
		'rows'   => [],
	];

	/**
	 * `rgb()` / `rgba()` with any spacing and an alpha with or without a leading zero.
	 *
	 * @since 2.0.5
	 */
	const RGBA_PATTERN = '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*(\d*\.?\d+)\s*)?\)$/iD';

	/**
	 * Hex color with `#`: 3, 6 or 8 digits.
	 *
	 * @since 2.0.5
	 */
	const HEX_PATTERN = '/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/iD';

	/**
	 * Six hex digits without `#`, as the Lite stock `flynn` theme stores one.
	 *
	 * @since 2.0.5
	 */
	const BARE_HEX_PATTERN = '/^[0-9a-f]{6}$/iD';

	/**
	 * Default white of the field, menu and button text colors.
	 *
	 * @since 2.0.5
	 */
	private const COLOR_WHITE = '#ffffff';

	/**
	 * Default accent of the button and page break colors.
	 *
	 * @since 2.0.5
	 */
	private const COLOR_ACCENT = '#066aab';

	/**
	 * Controls per context.
	 *
	 * @since 2.0.5
	 *
	 * @var array
	 */
	private $controls = [];

	/**
	 * Sections per context.
	 *
	 * @since 2.0.5
	 *
	 * @var array
	 */
	private $sections = [];

	/**
	 * Get the controls of a surface context.
	 *
	 * @since 2.0.5
	 *
	 * @param string $context Surface context.
	 *
	 * @return array Key => definition.
	 */
	public function get_controls( string $context = self::CONTEXT_DEFAULT ): array {

		if ( isset( $this->controls[ $context ] ) ) {
			return $this->controls[ $context ];
		}

		/**
		 * Filter the form theme controls.
		 *
		 * @since 2.0.5
		 *
		 * @param array  $controls Controls, key => definition.
		 * @param string $context  Surface context, `default` in core.
		 */
		$filtered = (array) apply_filters( 'wpforms_frontend_themes_schema_get_controls', $this->get_core_controls(), $context );

		$this->controls[ $context ] = array_map(
			static function ( $control ) {

				return array_merge( self::CONTROL_DEFAULTS, $control );
			},
			array_filter( $filtered, 'is_array' )
		);

		return $this->controls[ $context ];
	}

	/**
	 * Get one control.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key     Control key.
	 * @param string $context Surface context.
	 *
	 * @return array Definition, or an empty array for an unknown key.
	 */
	public function get_control( string $key, string $context = self::CONTEXT_DEFAULT ): array {

		return $this->get_controls( $context )[ $key ] ?? [];
	}

	/**
	 * Whether the None background style keeps the background color in a context, as a page that paints the color behind no background does.
	 *
	 * @since 2.0.5
	 *
	 * @param string $context Surface context.
	 *
	 * @return bool
	 */
	public function none_keeps_color( string $context = self::CONTEXT_DEFAULT ): bool {

		return ! empty( $this->get_control( 'backgroundColor', $context )['none_keeps_color'] );
	}

	/**
	 * Get the sections of a surface context.
	 *
	 * @since 2.0.5
	 *
	 * @param string $context Surface context.
	 *
	 * @return array Id => section.
	 */
	public function get_sections( string $context = self::CONTEXT_DEFAULT ): array {

		if ( isset( $this->sections[ $context ] ) ) {
			return $this->sections[ $context ];
		}

		/**
		 * Filter the form theme sections.
		 *
		 * @since 2.0.5
		 *
		 * @param array  $sections Sections, id => section.
		 * @param string $context  Surface context, `default` in core.
		 */
		$filtered = (array) apply_filters( 'wpforms_frontend_themes_schema_get_sections', $this->get_core_sections(), $context );

		$this->sections[ $context ] = array_map(
			static function ( $section ) {

				return array_merge( self::SECTION_DEFAULTS, $section );
			},
			array_filter( $filtered, 'is_array' )
		);

		return $this->sections[ $context ];
	}

	/**
	 * Get the defaults a custom theme is filled with in this edition.
	 *
	 * Matches today's fill from the edition's stock `default` theme.
	 *
	 * @since 2.0.5
	 *
	 * @param string $context Surface context.
	 *
	 * @return array Key => default, theme format.
	 */
	public function get_fill_defaults( string $context = self::CONTEXT_DEFAULT ): array {

		$is_pro   = wpforms()->is_pro();
		$defaults = [];

		foreach ( $this->get_controls( $context ) as $key => $control ) {
			if ( $control['scope'] !== 'theme' || ! $control['stock'] ) {
				continue;
			}

			if ( $control['tier'] === 'pro' && ! $is_pro ) {
				continue;
			}

			$defaults[ $key ] = $control['default'];
		}

		return $defaults;
	}

	/**
	 * Get the defaults of the surface-scope controls of a context.
	 *
	 * @since 2.0.5
	 *
	 * @param string $context Surface context.
	 *
	 * @return array Key => default, empty for a context without surface controls.
	 */
	public function get_surface_defaults( string $context = self::CONTEXT_DEFAULT ): array {

		$defaults = [];

		foreach ( $this->get_controls( $context ) as $key => $control ) {
			if ( $control['scope'] === 'surface' ) {
				$defaults[ $key ] = $control['default'];
			}
		}

		return $defaults;
	}

	/**
	 * Whether the current user may change a key.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key     Control key.
	 * @param string $context Surface context.
	 *
	 * @return bool
	 */
	public function can_edit( string $key, string $context = self::CONTEXT_DEFAULT ): bool {

		if ( ! $this->can_store( $key, self::STORAGE_FORM, $context ) ) {
			return false;
		}

		return $this->get_control( $key, $context )['tier'] !== 'pro' || $this->is_pro_tier_available();
	}

	/**
	 * Whether the current user may save an incoming value of a key.
	 *
	 * As in the Form Builder, the Pro tier locks the controls, not the form: a selected or pasted
	 * theme saves its Pro values. Lite custom themes take no new Pro values, as its themes file did not.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key     Control key.
	 * @param string $storage Storage.
	 * @param string $context Surface context.
	 *
	 * @return bool
	 */
	public function can_store( string $key, string $storage = self::STORAGE_FORM, string $context = self::CONTEXT_DEFAULT ): bool {

		$control = $this->get_control( $key, $context );

		if ( ! $control ) {
			return false;
		}

		if ( $control['capability'] !== '' && ! current_user_can( $control['capability'] ) ) {
			return false;
		}

		return $storage !== self::STORAGE_THEME || $control['tier'] !== 'pro' || wpforms()->is_pro();
	}

	/**
	 * Get the schema for localized JS data.
	 *
	 * @since 2.0.5
	 *
	 * @param string $context Surface context.
	 *
	 * @return array
	 */
	public function to_js( string $context = self::CONTEXT_DEFAULT ): array {

		$js_controls = [];

		foreach ( $this->get_controls( $context ) as $key => $control ) {
			$js_controls[ $key ] = $this->get_js_control( (string) $key, $control, $context );
		}

		$js_sections = [];

		foreach ( $this->get_sections( $context ) as $id => $section ) {
			$js_sections[] = [
				'id'     => (string) $id,
				'tier'   => $section['tier'],
				'hidden' => (bool) $section['hidden'],
				'rows'   => $section['rows'],
			];
		}

		return [
			'controls' => $js_controls,
			'sections' => $js_sections,
		];
	}

	/**
	 * Convert a value to the format of a storage.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key     Control key.
	 * @param mixed  $value   Value.
	 * @param string $storage Storage, `form` (unitless) or `theme` (with unit).
	 * @param string $context Surface context.
	 *
	 * @return string
	 */
	public function to_storage( string $key, $value, string $storage, string $context = self::CONTEXT_DEFAULT ): string {

		$value = trim( (string) $value );
		$unit  = $this->get_control( $key, $context )['unit'] ?? '';

		if ( $unit === '' || $value === '' ) {
			return $value;
		}

		$number = (string) preg_replace( '/' . preg_quote( $unit, '/' ) . '$/i', '', $value );

		return $storage === self::STORAGE_THEME ? $number . $unit : $number;
	}

	/**
	 * Get the form setting holding the surface keys of a context.
	 *
	 * @since 2.0.5
	 *
	 * @param string $context Surface context.
	 *
	 * @return string
	 */
	public static function get_surface_key( string $context ): string {

		return $context . self::SURFACE_KEY_SUFFIX;
	}

	/**
	 * Whether a context keeps a theme of its own in its slot.
	 *
	 * @since 2.0.5
	 *
	 * @param string $context Surface context.
	 *
	 * @return bool
	 */
	public function is_theme_owner( string $context ): bool {

		/**
		 * Filter whether a context keeps a theme of its own in its slot.
		 *
		 * A context owning a page (a Form Page, a Conversational Form) keeps its
		 * theme apart from the form default. A context that only narrows the
		 * controls of a form mode returns `false`: it saves to the form default
		 * and to embed overrides, as `default` does.
		 *
		 * @since 2.0.5
		 *
		 * @param bool   $is_owner Whether the context owns a theme, `true` for every context but `default`.
		 * @param string $context  Surface context.
		 */
		return (bool) apply_filters( 'wpforms_frontend_themes_schema_is_theme_owner', $context !== self::CONTEXT_DEFAULT, $context );
	}

	/**
	 * Overlay the values a context pins on settings about to be printed.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $settings Theme settings.
	 * @param string $context  Surface context.
	 *
	 * @return array
	 */
	public function apply_pinned( array $settings, string $context = self::CONTEXT_DEFAULT ): array {

		foreach ( $this->get_controls( $context ) as $key => $control ) {
			if ( isset( $control['pinned'] ) ) {
				$settings[ $key ] = (string) $control['pinned'];
			}
		}

		return $settings;
	}

	/**
	 * Whether a form setting holds the surface keys of a context.
	 *
	 * @since 2.0.5
	 *
	 * @param string|int $key Form setting key.
	 *
	 * @return bool
	 */
	public static function is_surface_key( $key ): bool {

		$key    = (string) $key;
		$length = strlen( self::SURFACE_KEY_SUFFIX );

		return strlen( $key ) > $length && substr( $key, -$length ) === self::SURFACE_KEY_SUFFIX;
	}

	/**
	 * Bring a color to one canonical spelling, for comparisons.
	 *
	 * Not applied on write yet: builder, block and Elementor compare stock themes by string.
	 *
	 * @since 2.0.5
	 *
	 * @param string $value Color.
	 *
	 * @return string
	 */
	public function normalize_color( string $value ): string {

		$color = strtolower( trim( $value ) );

		if ( $color === 'transparent' ) {
			return 'rgba(0, 0, 0, 0)';
		}

		if ( preg_match( self::HEX_PATTERN, $color, $matches ) ) {
			return $this->normalize_hex( $matches[1] );
		}

		if ( preg_match( self::BARE_HEX_PATTERN, $color ) ) {
			return $this->normalize_hex( $color );
		}

		if ( preg_match( self::RGBA_PATTERN, $color, $matches ) ) {
			return $this->format_rgba( $matches );
		}

		return $color;
	}

	/**
	 * Expand hex3 and drop an opaque hex8 alpha.
	 *
	 * @since 2.0.5
	 *
	 * @param string $hex Lowercase hex digits without `#`.
	 *
	 * @return string
	 */
	private function normalize_hex( string $hex ): string {

		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( strlen( $hex ) === 8 && substr( $hex, 6 ) === 'ff' ) {
			$hex = substr( $hex, 0, 6 );
		}

		return '#' . $hex;
	}

	/**
	 * Print parsed `rgb()` / `rgba()` parts as `rgba(r, g, b, a)`.
	 *
	 * @since 2.0.5
	 *
	 * @param array $parts RGBA_PATTERN matches.
	 *
	 * @return string
	 */
	private function format_rgba( array $parts ): string {

		$alpha = isset( $parts[4] ) && $parts[4] !== '' ? (string) (float) $parts[4] : '1';

		return sprintf( 'rgba(%d, %d, %d, %s)', $parts[1], $parts[2], $parts[3], $alpha );
	}

	/**
	 * Get one control for localized JS data.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key     Control key.
	 * @param array  $control Definition.
	 * @param string $context Surface context.
	 *
	 * @return array
	 */
	private function get_js_control( string $key, array $control, string $context ): array {

		return [
			'section'        => $control['section'],
			'type'           => $control['type'],
			'default'        => $control['default'],
			'unit'           => $control['unit'],
			'scope'          => $control['scope'],
			'tier'           => $control['tier'],
			'hidden'         => (bool) $control['hidden'],
			'editable'       => $this->can_edit( $key, $context ),
			'cssVar'         => $control['css_var'],
			'values'         => array_map( 'strval', array_keys( $control['options'] ) ),
			'accepts'        => $control['accepts'],
			'stock'          => (bool) $control['stock'],
			'enabledIf'      => $control['enabled_if'],
			'visibleIf'      => $control['visible_if'],
			'solidDefault'   => $control['solid_default'],
			'noneKeepsColor' => (bool) $control['none_keeps_color'],
			'follows'        => $control['follows'],
			// The panel reads a pin as a string, the way apply_pinned() prints it.
			'pinned'         => $control['pinned'] === null ? null : (string) $control['pinned'],
		];
	}

	/**
	 * Whether Pro tier keys may be edited.
	 *
	 * Public so the panel locks and upsells by the same rule the sanitizer applies. The rule is the
	 * one the Form Builder Themes panel used, `WPForms_License::is_active()`: a license over its
	 * activation limit or flagged is not active either, which `wpforms_is_license_valid()` does not
	 * check. Read from the option, since the license object exists in the admin only.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	public function is_pro_tier_available(): bool {

		if ( ! wpforms()->is_pro() || ! wpforms_get_license_key() ) {
			return false;
		}

		$license = get_option( 'wpforms_license', [] );

		// A `WPFORMS_LICENSE_KEY` constant alone does not activate, as in WPForms_License::is_active();
		// removing the key stores an empty string.
		if ( empty( $license ) || ! is_array( $license ) ) {
			return false;
		}

		// The error flags of WPForms_License::get_errors().
		$errors = array_intersect_key( $license, array_flip( [ 'is_expired', 'is_disabled', 'is_invalid', 'is_limit_reached', 'is_flagged' ] ) );

		return empty( array_filter( $errors ) );
	}

	/**
	 * Get the core sections.
	 *
	 * @since 2.0.5
	 *
	 * @return array
	 */
	private function get_core_sections(): array {

		return [
			'meta'              => [],
			'field_styles'      => [
				'label' => __( 'Field Styles', 'wpforms-lite' ),
				'rows'  => [
					[ 'fieldSize', 'fieldBorderStyle' ],
					[ 'fieldBorderSize', 'fieldBorderRadius' ],
					[ 'fieldBackgroundColor', 'fieldBorderColor', 'fieldTextColor' ],
				],
			],
			'label_styles'      => [
				'label' => __( 'Label Styles', 'wpforms-lite' ),
				'rows'  => [
					[ 'labelSize' ],
					[ 'labelColor', 'labelSublabelColor', 'labelErrorColor' ],
				],
			],
			'button_styles'     => [
				'label' => __( 'Button Styles', 'wpforms-lite' ),
				'rows'  => [
					[ 'buttonSize', 'buttonBorderStyle' ],
					[ 'buttonBorderSize', 'buttonBorderRadius' ],
					[ 'buttonBackgroundColor', 'buttonBorderColor', 'buttonTextColor' ],
				],
			],
			'container_styles'  => [
				'label' => __( 'Container Styles', 'wpforms-lite' ),
				'tier'  => 'pro',
				'rows'  => [
					[ 'containerPadding', 'containerBorderStyle' ],
					[ 'containerBorderWidth', 'containerBorderRadius' ],
					[ 'containerBorderColor', 'containerShadowSize' ],
				],
			],
			'background_styles' => [
				'label' => __( 'Background Styles', 'wpforms-lite' ),
				'tier'  => 'pro',
				'rows'  => [
					[ 'backgroundImage' ],
					[ 'backgroundGradientDirection' ],
					[ 'backgroundGradientStart', 'backgroundGradientEnd' ],
					[ 'backgroundColor', 'backgroundSizeMode' ],
					[ 'backgroundWidth', 'backgroundHeight' ],
					[ 'backgroundPosition', 'backgroundRepeat' ],
				],
			],
			'other_styles'      => [],
			'advanced'          => [
				'label' => __( 'Advanced', 'wpforms-lite' ),
				'rows'  => [
					[ 'customCss' ],
					[ 'copyPasteJsonValue' ],
				],
			],
		];
	}

	/**
	 * Get the core controls.
	 *
	 * One long method on purpose, like `WPForms_Settings::get_registered_settings()`:
	 * the definitions read top to bottom in panel order.
	 *
	 * @since 2.0.5
	 *
	 * @return array
	 */
	private function get_core_controls(): array {

		$sizes   = [
			'small'  => __( 'Small', 'wpforms-lite' ),
			'medium' => __( 'Medium', 'wpforms-lite' ),
			'large'  => __( 'Large', 'wpforms-lite' ),
		];
		$borders = [
			'none'   => __( 'None', 'wpforms-lite' ),
			'solid'  => __( 'Solid', 'wpforms-lite' ),
			'dashed' => __( 'Dashed', 'wpforms-lite' ),
			'dotted' => __( 'Dotted', 'wpforms-lite' ),
		];
		$image   = [ [ 'backgroundImage', 'in', [ 'library', 'stock' ] ] ];

		return [
			'wpformsTheme'                => [
				'section' => 'meta',
				'type'    => 'slug',
				'default' => 'default',
				'scope'   => 'form',
				'hidden'  => true,
			],
			'isCustomTheme'               => [
				'section' => 'meta',
				'type'    => 'flag',
				'scope'   => 'form',
				'hidden'  => true,
			],
			'themeName'                   => [
				'section' => 'meta',
				'type'    => 'text',
				'scope'   => 'form',
				'hidden'  => true,
			],
			'fieldSize'                   => [
				'section' => 'field_styles',
				'label'   => __( 'Size', 'wpforms-lite' ),
				'type'    => 'select',
				'default' => 'medium',
				'options' => $sizes,
			],
			'fieldBorderStyle'            => [
				'section' => 'field_styles',
				'label'   => __( 'Border', 'wpforms-lite' ),
				'type'    => 'select',
				'default' => 'solid',
				'options' => $borders,
				'css_var' => 'field-border-style',
			],
			'fieldBorderSize'             => [
				'section'    => 'field_styles',
				'label'      => __( 'Border Size', 'wpforms-lite' ),
				'type'       => 'number',
				'default'    => '1px',
				'unit'       => 'px',
				'css_var'    => 'field-border-size',
				'enabled_if' => [ [ 'fieldBorderStyle', '!=', 'none' ] ],
			],
			'fieldBorderRadius'           => [
				'section' => 'field_styles',
				'label'   => __( 'Border Radius', 'wpforms-lite' ),
				'type'    => 'number',
				'default' => '3px',
				'unit'    => 'px',
				'css_var' => 'field-border-radius',
			],
			'fieldBackgroundColor'        => [
				'section' => 'field_styles',
				'label'   => __( 'Background', 'wpforms-lite' ),
				'type'    => 'color',
				'default' => self::COLOR_WHITE,
				'css_var' => 'field-background-color',
			],
			'fieldBorderColor'            => [
				'section'    => 'field_styles',
				'label'      => __( 'Border', 'wpforms-lite' ),
				'type'       => 'color',
				'default'    => 'rgba( 0, 0, 0, 0.25 )',
				'css_var'    => 'field-border-color',
				'enabled_if' => [ [ 'fieldBorderStyle', '!=', 'none' ] ],
			],
			'fieldTextColor'              => [
				'section' => 'field_styles',
				'label'   => __( 'Text', 'wpforms-lite' ),
				'type'    => 'color',
				'default' => 'rgba( 0, 0, 0, 0.7 )',
				'css_var' => 'field-text-color',
			],
			'fieldMenuColor'              => [
				'section' => 'other_styles',
				'type'    => 'color',
				'default' => self::COLOR_WHITE,
				'css_var' => 'field-menu-color',
				'hidden'  => true,
			],
			'labelSize'                   => [
				'section' => 'label_styles',
				'label'   => __( 'Size', 'wpforms-lite' ),
				'type'    => 'select',
				'default' => 'medium',
				'options' => $sizes,
			],
			'labelColor'                  => [
				'section' => 'label_styles',
				'label'   => __( 'Label', 'wpforms-lite' ),
				'type'    => 'color',
				'default' => 'rgba( 0, 0, 0, 0.85 )',
				'css_var' => 'label-color',
			],
			'labelSublabelColor'          => [
				'section' => 'label_styles',
				'label'   => __( 'Sublabel', 'wpforms-lite' ),
				'type'    => 'color',
				'default' => 'rgba( 0, 0, 0, 0.55 )',
				'css_var' => 'label-sublabel-color',
			],
			'labelErrorColor'             => [
				'section' => 'label_styles',
				'label'   => __( 'Error', 'wpforms-lite' ),
				'type'    => 'color',
				'default' => '#d63637',
				'css_var' => 'label-error-color',
			],
			'buttonSize'                  => [
				'section' => 'button_styles',
				'label'   => __( 'Size', 'wpforms-lite' ),
				'type'    => 'select',
				'default' => 'medium',
				'options' => $sizes,
			],
			'buttonBorderStyle'           => [
				'section' => 'button_styles',
				'label'   => __( 'Border', 'wpforms-lite' ),
				'type'    => 'select',
				'default' => 'none',
				'options' => $borders,
				'css_var' => 'button-border-style',
			],
			'buttonBorderSize'            => [
				'section'    => 'button_styles',
				'label'      => __( 'Border Size', 'wpforms-lite' ),
				'type'       => 'number',
				'default'    => '1px',
				'unit'       => 'px',
				'css_var'    => 'button-border-size',
				'enabled_if' => [ [ 'buttonBorderStyle', '!=', 'none' ] ],
			],
			'buttonBorderRadius'          => [
				'section' => 'button_styles',
				'label'   => __( 'Border Radius', 'wpforms-lite' ),
				'type'    => 'number',
				'default' => '3px',
				'unit'    => 'px',
				'css_var' => 'button-border-radius',
			],
			'buttonBackgroundColor'       => [
				'section' => 'button_styles',
				'label'   => __( 'Background', 'wpforms-lite' ),
				'type'    => 'color',
				'default' => self::COLOR_ACCENT,
				'css_var' => 'button-background-color',
			],
			'buttonBorderColor'           => [
				'section'    => 'button_styles',
				'label'      => __( 'Border', 'wpforms-lite' ),
				'type'       => 'color',
				'default'    => self::COLOR_ACCENT,
				'css_var'    => 'button-border-color',
				'enabled_if' => [ [ 'buttonBorderStyle', '!=', 'none' ] ],
			],
			'buttonTextColor'             => [
				'section' => 'button_styles',
				'label'   => __( 'Text', 'wpforms-lite' ),
				'type'    => 'color',
				'default' => self::COLOR_WHITE,
				'css_var' => 'button-text-color',
			],
			'pageBreakColor'              => [
				'section' => 'other_styles',
				'type'    => 'color',
				'default' => self::COLOR_ACCENT,
				'css_var' => 'page-break-color',
				'hidden'  => true,
			],
			'containerPadding'            => [
				'section' => 'container_styles',
				'label'   => __( 'Padding', 'wpforms-lite' ),
				'type'    => 'number',
				'default' => '0px',
				'unit'    => 'px',
				'tier'    => 'pro',
				'css_var' => 'container-padding',
			],
			'containerBorderStyle'        => [
				'section' => 'container_styles',
				'label'   => __( 'Border', 'wpforms-lite' ),
				'type'    => 'select',
				'default' => 'none',
				'options' => $borders,
				'accepts' => [ 'double' ],
				'tier'    => 'pro',
				'css_var' => 'container-border-style',
			],
			'containerBorderWidth'        => [
				'section'    => 'container_styles',
				'label'      => __( 'Border Size', 'wpforms-lite' ),
				'type'       => 'number',
				'default'    => '1px',
				'unit'       => 'px',
				'tier'       => 'pro',
				'css_var'    => 'container-border-width',
				'enabled_if' => [ [ 'containerBorderStyle', '!=', 'none' ] ],
			],
			'containerBorderRadius'       => [
				'section' => 'container_styles',
				'label'   => __( 'Border Radius', 'wpforms-lite' ),
				'type'    => 'number',
				'default' => '3px',
				'unit'    => 'px',
				'tier'    => 'pro',
				'css_var' => 'container-border-radius',
			],
			'containerBorderColor'        => [
				'section'    => 'container_styles',
				'label'      => __( 'Border', 'wpforms-lite' ),
				'type'       => 'color',
				'default'    => '#000000',
				'tier'       => 'pro',
				'css_var'    => 'container-border-color',
				'enabled_if' => [ [ 'containerBorderStyle', '!=', 'none' ] ],
			],
			'containerShadowSize'         => [
				'section' => 'container_styles',
				'label'   => __( 'Shadow', 'wpforms-lite' ),
				'type'    => 'select',
				'default' => 'none',
				'options' => [ 'none' => __( 'None', 'wpforms-lite' ) ] + $sizes,
				'tier'    => 'pro',
			],
			'backgroundImage'             => [
				'section' => 'background_styles',
				'label'   => __( 'Style', 'wpforms-lite' ),
				'type'    => 'select',
				'default' => 'none',
				'options' => [
					'none'     => __( 'None', 'wpforms-lite' ),
					'solid'    => __( 'Solid', 'wpforms-lite' ),
					'gradient' => __( 'Gradient', 'wpforms-lite' ),
					'library'  => __( 'Image', 'wpforms-lite' ),
					'stock'    => __( 'Stock Photo', 'wpforms-lite' ),
				],
				'tier'    => 'pro',
				'css_var' => 'background-image',
			],
			'backgroundPosition'          => [
				'section'    => 'background_styles',
				'label'      => __( 'Position', 'wpforms-lite' ),
				'type'       => 'select',
				'default'    => 'center center',
				'options'    => [
					'top left'      => __( 'Top Left', 'wpforms-lite' ),
					'top center'    => __( 'Top Center', 'wpforms-lite' ),
					'top right'     => __( 'Top Right', 'wpforms-lite' ),
					'center left'   => __( 'Center Left', 'wpforms-lite' ),
					'center center' => __( 'Center Center', 'wpforms-lite' ),
					'center right'  => __( 'Center Right', 'wpforms-lite' ),
					'bottom left'   => __( 'Bottom Left', 'wpforms-lite' ),
					'bottom center' => __( 'Bottom Center', 'wpforms-lite' ),
					'bottom right'  => __( 'Bottom Right', 'wpforms-lite' ),
				],
				'accepts'    => [ 'center' ],
				'tier'       => 'pro',
				'css_var'    => 'background-position',
				'visible_if' => $image,
			],
			'backgroundRepeat'            => [
				'section'    => 'background_styles',
				'label'      => __( 'Repeat', 'wpforms-lite' ),
				'type'       => 'select',
				'default'    => 'no-repeat',
				'options'    => [
					'no-repeat' => __( 'No Repeat', 'wpforms-lite' ),
					'repeat'    => __( 'Tile', 'wpforms-lite' ),
					'repeat-x'  => __( 'Repeat X', 'wpforms-lite' ),
					'repeat-y'  => __( 'Repeat Y', 'wpforms-lite' ),
				],
				'tier'       => 'pro',
				'css_var'    => 'background-repeat',
				'visible_if' => $image,
			],
			'backgroundSizeMode'          => [
				'section'    => 'background_styles',
				'label'      => __( 'Size', 'wpforms-lite' ),
				'type'       => 'select',
				'default'    => 'cover',
				'options'    => [
					'dimensions' => __( 'Dimensions', 'wpforms-lite' ),
					'cover'      => __( 'Cover', 'wpforms-lite' ),
				],
				'tier'       => 'pro',
				'visible_if' => $image,
			],
			'backgroundSize'              => [
				'section' => 'background_styles',
				'type'    => 'size',
				'default' => 'cover',
				'tier'    => 'pro',
				'css_var' => 'background-size',
				'hidden'  => true,
			],
			'backgroundWidth'             => [
				'section'    => 'background_styles',
				'label'      => __( 'Width', 'wpforms-lite' ),
				'type'       => 'number',
				'default'    => '100px',
				'unit'       => 'px',
				'tier'       => 'pro',
				'css_var'    => 'background-width',
				'visible_if' => array_merge( $image, [ [ 'backgroundSizeMode', '=', 'dimensions' ] ] ),
			],
			'backgroundHeight'            => [
				'section'    => 'background_styles',
				'label'      => __( 'Height', 'wpforms-lite' ),
				'type'       => 'number',
				'default'    => '100px',
				'unit'       => 'px',
				'tier'       => 'pro',
				'css_var'    => 'background-height',
				'visible_if' => array_merge( $image, [ [ 'backgroundSizeMode', '=', 'dimensions' ] ] ),
			],
			'backgroundColor'             => [
				'section'    => 'background_styles',
				'label'      => __( 'Color', 'wpforms-lite' ),
				'type'       => 'color',
				'default'    => 'rgba( 0, 0, 0, 0 )',
				'tier'       => 'pro',
				'css_var'    => 'background-color',
				'visible_if' => [ [ 'backgroundImage', 'in', [ 'solid', 'library', 'stock' ] ] ],
			],
			'backgroundUrl'               => [
				'section' => 'background_styles',
				'type'    => 'url',
				'default' => 'url()',
				'tier'    => 'pro',
				'css_var' => 'background-url',
				'hidden'  => true,
			],
			'backgroundGradientDirection' => [
				'section'    => 'background_styles',
				'label'      => __( 'Direction', 'wpforms-lite' ),
				'type'       => 'select',
				'default'    => 'radial',
				'options'    => [
					'vertical'   => __( 'Vertical', 'wpforms-lite' ),
					'horizontal' => __( 'Horizontal', 'wpforms-lite' ),
					'diagonal'   => __( 'Diagonal', 'wpforms-lite' ),
					'radial'     => __( 'Radial', 'wpforms-lite' ),
				],
				'tier'       => 'pro',
				'visible_if' => [ [ 'backgroundImage', '=', 'gradient' ] ],
			],
			'backgroundGradientStart'     => [
				'section'    => 'background_styles',
				'label'      => __( 'Color Start', 'wpforms-lite' ),
				'type'       => 'color',
				'default'    => self::COLOR_WHITE,
				'tier'       => 'pro',
				'visible_if' => [ [ 'backgroundImage', '=', 'gradient' ] ],
			],
			'backgroundGradientEnd'       => [
				'section'    => 'background_styles',
				'label'      => __( 'Color End', 'wpforms-lite' ),
				'type'       => 'color',
				'default'    => self::COLOR_ACCENT,
				'tier'       => 'pro',
				'visible_if' => [ [ 'backgroundImage', '=', 'gradient' ] ],
			],
			'customCss'                   => [
				'section'     => 'advanced',
				'label'       => __( 'Custom CSS', 'wpforms-lite' ),
				'description' => __( 'Further customize the look of this form without having to edit theme files.', 'wpforms-lite' ),
				'type'        => 'textarea',
				'scope'       => 'form',
				'capability'  => 'manage_options',
			],
			'copyPasteJsonValue'          => [
				'section'     => 'advanced',
				'label'       => __( 'Copy / Paste Style Settings', 'wpforms-lite' ),
				'description' => __( 'If you\'ve copied style settings from another form, you can paste them here to add the same styling to this form. Any current style settings will be overwritten.', 'wpforms-lite' ),
				'type'        => 'textarea',
				'scope'       => 'form',
				'capability'  => 'manage_options',
			],
		];
	}
}
