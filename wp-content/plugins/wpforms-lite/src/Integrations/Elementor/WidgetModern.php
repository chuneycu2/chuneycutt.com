<?php

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedMethodInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace WPForms\Integrations\Elementor;

use Elementor\Plugin;
use Elementor\Controls_Manager;
use Exception;
use WPForms\Frontend\CSSVars;
use WPForms\Frontend\ThemesEditor;
use WPForms\Frontend\ThemesEditor\Support;

/**
 * WPForms modern widget for Elementor page builder.
 *
 * @since 1.8.3
 */
class WidgetModern extends Widget {

	/**
	 * Setting that marks a widget inserted after the style controls were removed.
	 *
	 * @since 2.0.5
	 */
	private const MARKER = 'themesEditor';

	/**
	 * Style settings of the removed style controls, with the defaults those controls had.
	 *
	 * Elementor stores only values that differ from a control default, so a widget saved
	 * with the style controls carries some of these keys and inherits the rest.
	 *
	 * @since 2.0.5
	 */
	private const STYLE_DEFAULTS = [
		'wpformsTheme'          => 'default',
		'fieldSize'             => 'medium',
		'fieldBorderStyle'      => 'solid',
		'fieldBorderSize'       => '1',
		'fieldBorderRadius'     => '3',
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
		'buttonBorderRadius'    => '3',
		'buttonBackgroundColor' => CSSVars::ROOT_VARS['button-background-color'],
		'buttonBorderColor'     => CSSVars::ROOT_VARS['button-border-color'],
		'buttonTextColor'       => CSSVars::ROOT_VARS['button-text-color'],
		'containerPadding'      => CSSVars::ROOT_VARS['container-padding'],
		'containerBorderStyle'  => CSSVars::ROOT_VARS['container-border-style'],
		'containerBorderWidth'  => CSSVars::ROOT_VARS['container-border-width'],
		'containerBorderRadius' => '3',
		'containerShadowSize'   => CSSVars::CONTAINER_SHADOW_SIZE['none']['box-shadow'],
		'containerBorderColor'  => CSSVars::ROOT_VARS['container-border-color'],
		'backgroundImage'       => 'none',
		'backgroundPosition'    => CSSVars::ROOT_VARS['background-position'],
		'backgroundRepeat'      => CSSVars::ROOT_VARS['background-repeat'],
		'backgroundSize'        => CSSVars::ROOT_VARS['background-size'],
		'backgroundSizeMode'    => CSSVars::ROOT_VARS['background-size'],
		'backgroundWidth'       => '100',
		'backgroundHeight'      => '100',
		// The control stored a media array; the default stays a value apply_complex_settings() passes through.
		'backgroundUrl'         => CSSVars::ROOT_VARS['background-url'],
		'backgroundColor'       => CSSVars::ROOT_VARS['background-color'],
		'pageBreakColor'        => CSSVars::ROOT_VARS['page-break-color'],
	];

	/**
	 * Gradient settings, which the widget never had controls for.
	 *
	 * @since 2.0.5
	 */
	private const GRADIENT_KEYS = [ 'backgroundGradientDirection', 'backgroundGradientStart', 'backgroundGradientEnd' ];

	/**
	 * Classes of Elementor's warning alert, which the Form Theme notices use.
	 *
	 * @since 2.0.5
	 */
	private const WARNING_ALERT_CLASSES = 'elementor-panel-alert elementor-panel-alert-warning';

	/**
	 * Instance of CSSVars class.
	 *
	 * @since 1.8.3
	 *
	 * @var CSSVars
	 */
	protected $css_vars_obj;

	/**
	 * Widget constructor.
	 *
	 * @since 1.8.3
	 *
	 * @param array $data Widget data.
	 * @param array $args Widget arguments.
	 *
	 * @throws Exception If arguments are missing when initializing a full widget.
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function __construct( $data = [], $args = null ) {

		parent::__construct( $data, $args );

		$this->load();
	}

	/**
	 * Load widget.
	 *
	 * @since 1.8.3
	 */
	private function load(): void {

		$this->css_vars_obj = wpforms()->obj( 'css_vars' );
	}

	/**
	 * Register widget controls.
	 *
	 * Adds different input fields to allow the user to change and customize the widget settings.
	 *
	 * @since 1.8.3
	 */
	protected function register_controls() {

		$this->content_controls();
		$this->style_controls();
	}

	/**
	 * Register widget controls for the Style section.
	 *
	 * Adds different input fields into the "Style" section to allow the user to change and customize the widget style
	 * settings.
	 *
	 * @since 1.8.3
	 */
	private function style_controls(): void {

		$this->add_form_theme_controls();
		$this->add_advanced_style_controls();
	}

	/**
	 * Register widget controls for the "Form Theme" section.
	 *
	 * Themes are edited in the front-end themes editor; the section opens it.
	 *
	 * @since 2.0.5
	 */
	private function add_form_theme_controls(): void {

		$this->start_controls_section(
			'form_theme',
			[
				'label' => esc_html__( 'Form Theme', 'wpforms-lite' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			self::MARKER,
			[
				'type'    => Controls_Manager::HIDDEN,
				'default' => '',
			]
		);

		$editor  = wpforms()->obj( 'themes_editor' );
		$data    = $editor instanceof ThemesEditor ? $editor->get_support()->get_editor_launch_data( 'Elementor Widget Settings' ) : Support::get_unavailable_launch_data();
		$strings = (array) $data['strings'];

		if ( ! $data['supported'] ) {
			// Without the editor there is nothing to launch and no copy to show.
			if ( $strings ) {
				$this->add_control(
					'themes_editor_unsupported_notice',
					[
						'show_label'      => false,
						'type'            => Controls_Manager::RAW_HTML,
						// The reason names the setting to change; the generic text covers a site where a filter turned the editor off.
						'raw'             => $this->get_notice(
							$strings['unsupported_notice_head'],
							$data['reason'] !== '' ? $data['reason'] : $strings['unsupported_notice_text'],
							$strings['unsupported_notice_link'],
							$strings['learn_more']
						),
						'content_classes' => self::WARNING_ALERT_CLASSES,
					]
				);
			}

			$this->end_controls_section();

			return;
		}

		$notice_form_ids = $this->get_notice_form_ids();
		$hidden_for      = array_merge( [ '0' ], $notice_form_ids['lead'], $notice_form_ids['no_access'] );

		$this->add_control(
			'themes_editor_description',
			[
				'show_label' => false,
				'type'       => Controls_Manager::RAW_HTML,
				'raw'        => esc_html( $strings['description'] ),
				'condition'  => [
					'form_id!' => $hidden_for,
				],
			]
		);

		$this->add_control(
			'themes_editor_button',
			[
				'show_label'  => false,
				'label_block' => true,
				'type'        => Controls_Manager::BUTTON,
				'button_type' => 'default',
				'text'        => esc_html( $strings['button'] ),
				'event'       => 'wpformsElementorEditThemeClick',
				'classes'     => 'wpforms-elementor-edit-theme',
				'condition'   => [
					'form_id!' => $hidden_for,
				],
			]
		);

		$this->add_control(
			'themes_editor_admin_bar_hint',
			[
				'show_label' => false,
				'type'       => Controls_Manager::RAW_HTML,
				'raw'        => esc_html( $strings['admin_bar_hint'] ),
				'condition'  => [
					'form_id!' => $hidden_for,
				],
			]
		);

		$this->add_control(
			'lead_forms_notice',
			[
				'show_label'      => false,
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => $this->get_notice( $strings['lead_forms_notice_head'], $strings['lead_forms_notice_text'] ),
				'classes'         => 'wpforms-elementor-lead-forms-notice',
				'content_classes' => self::WARNING_ALERT_CLASSES,
				'condition'       => [
					'form_id' => $notice_form_ids['lead'],
				],
			]
		);

		$this->add_control(
			'no_access_notice',
			[
				'show_label'      => false,
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => $this->get_notice( $strings['no_access_notice_head'], $strings['no_access_notice_text'] ),
				'content_classes' => self::WARNING_ALERT_CLASSES,
				'condition'       => [
					'form_id' => $notice_form_ids['no_access'],
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Get the HTML of a Form Theme notice, as the block and the Divi modules show it.
	 *
	 * @since 2.0.5
	 *
	 * @param string $head      Bold first sentence.
	 * @param string $text      Body.
	 * @param string $link      Documentation URL, if any.
	 * @param string $link_text Documentation link text.
	 *
	 * @return string
	 */
	private function get_notice( string $head, string $text, string $link = '', string $link_text = '' ): string {

		$notice = sprintf( '<strong>%1$s</strong> %2$s', esc_html( $head ), esc_html( $text ) );

		if ( $link === '' ) {
			return $notice;
		}

		return sprintf( '%1$s <a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a>', $notice, esc_url( $link ), esc_html( $link_text ) );
	}

	/**
	 * Register widget controls for the "Advanced" section.
	 *
	 * @since 1.8.3
	 */
	private function add_advanced_style_controls(): void {

		$this->start_controls_section(
			'advanced',
			[
				'label' => esc_html__( 'Advanced', 'wpforms-lite' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'className',
			[
				'label'        => esc_html__( 'Additional Classes', 'wpforms-lite' ),
				'type'         => Controls_Manager::TEXT,
				'description'  => esc_html__( 'Separate multiple classes with spaces.', 'wpforms-lite' ),
				'ai'           => [
					'active' => false,
				],
				'prefix_class' => '', // Prevents re-rendering of the widget.
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Get the IDs of the forms whose Form Theme section shows a notice instead of the button.
	 *
	 * The addon styles a lead form, and the editor opens only a form the user can edit. Only the
	 * editor panel reads them, through control conditions built on the editor screen and its
	 * admin-ajax config request; a front-end render registers the controls too, and skips this.
	 *
	 * @since 2.0.5
	 *
	 * @return array `lead` and `no_access`, form IDs as the form selector stores them.
	 */
	private function get_notice_form_ids(): array {

		$ids = [
			'lead'      => [],
			'no_access' => [],
		];

		if ( ! is_admin() ) {
			return $ids;
		}

		$editor       = wpforms()->obj( 'themes_editor' );
		$form_handler = wpforms()->obj( 'form' );

		if ( ! $editor instanceof ThemesEditor || ! $form_handler || ! $this->current_user_can_view_forms() ) {
			return $ids;
		}

		foreach ( (array) $form_handler->get( '', wpforms_get_viewable_forms_args() ) as $form ) {
			if ( $editor->get_support()->is_unstyled_lead_form_post( $form ) ) {
				$ids['lead'][] = (string) $form->ID;

				continue;
			}

			if ( ! $editor->can_edit( (int) $form->ID ) ) {
				$ids['no_access'][] = (string) $form->ID;
			}
		}

		return $ids;
	}

	/**
	 * Render widget output on the frontend.
	 *
	 * @since 1.8.3
	 */
	protected function render_frontend() {

		if ( empty( $this->css_vars_obj ) ) {
			return;
		}

		$widget_id      = $this->get_id();
		$attr           = $this->get_settings_for_display();
		$custom_classes = ! empty( $attr['className'] ) ? trim( $attr['className'] ) : '';

		if ( $this->is_new_embed() ) {
			$this->output_form_default_css_vars( (string) $widget_id, (int) ( $attr['form_id'] ?? 0 ) );
		} else {
			$this->output_legacy_css_vars( (string) $widget_id, $attr );
		}

		// Add custom classes.
		if ( $custom_classes ) {
			$this->add_render_attribute(
				'_wrapper',
				[
					'class' => [
						$custom_classes,
					],
				]
			);
		}

		// Render selected form.
		$this->render_form();
	}

	/**
	 * Output the CSS variables of a widget saved with the style controls, on its wrapper.
	 *
	 * @since 2.0.5
	 *
	 * @param string $widget_id Widget ID.
	 * @param array  $attr      Widget settings for display.
	 */
	private function output_legacy_css_vars( string $widget_id, array $attr ): void {

		$css_vars = $this->css_vars_obj->get_customized_css_vars( $attr );

		if ( empty( $css_vars ) ) {
			return;
		}

		$style_id = 'wpforms-css-vars-elementor-widget-' . $widget_id;

		/**
		 * Filter the CSS selector for output CSS variables for styling the form in Elementor widget.
		 *
		 * @since 1.8.3
		 *
		 * @param string $selector The CSS selector for output CSS variables for styling the Elementor Widget.
		 * @param array  $attr     Attributes passed by Elementor Widget.
		 * @param array  $css_vars CSS variables data.
		 */
		$vars_selector = apply_filters(
			'wpforms_integrations_elementor_widget_modern_output_css_vars_selector',
			".elementor-widget-wpforms.elementor-element-{$widget_id}",
			$attr,
			$css_vars
		);

		$this->css_vars_obj->output_selector_vars( $vars_selector, $css_vars, $style_id );
	}

	/**
	 * Output the form default for a widget inserted after the style controls were removed.
	 *
	 * Scoped to the widget's own form: on `#wpforms-{id}` alone it would also beat the
	 * inherited variables of a legacy widget showing the same form on the page.
	 *
	 * @since 2.0.5
	 *
	 * @param string $widget_id Widget ID.
	 * @param int    $form_id   Form ID.
	 */
	private function output_form_default_css_vars( string $widget_id, int $form_id ): void {

		if ( ! $form_id ) {
			return;
		}

		$this->css_vars_obj->output_css_vars_for_shortcode(
			[ 'id' => $form_id ],
			".elementor-element-{$widget_id} #wpforms-{$form_id}",
			'wpforms-css-vars-elementor-widget-' . $widget_id
		);
	}

	/**
	 * Whether the widget was inserted after the style controls were removed and carries no styles of its own.
	 *
	 * A widget without the marker was saved before, even with no style changed, and keeps its
	 * legacy rendering; a marked copy of a styled widget keeps the styles it carries.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	private function is_new_embed(): bool {

		$settings = (array) $this->get_data( 'settings' );

		if ( ( $settings[ self::MARKER ] ?? '' ) !== '1' ) {
			return false;
		}

		$globals = array_filter( (array) ( $settings['__globals__'] ?? [] ) );

		return ! array_intersect_key( $settings, self::STYLE_DEFAULTS ) && ! array_intersect_key( $globals, self::STYLE_DEFAULTS );
	}

	/**
	 * Get the styles the embed renders with, for the themes editor.
	 *
	 * The defaults of the removed controls fill the keys the widget does not store, as they did
	 * while the controls existed, so the editor starts from what the widget renders rather than
	 * from the form default. A custom theme is named by the slug it has in the shared custom
	 * themes, which the Elementor widget's own themes joined under another slug.
	 *
	 * @since 2.0.5
	 *
	 * @return array
	 */
	protected function get_legacy_styles(): array {

		if ( $this->is_new_embed() ) {
			return [];
		}

		// Empty values are dropped first, as the render drops them, so they fall back to the default too.
		$styles = (array) $this->prepare_settings( array_merge( self::STYLE_DEFAULTS, array_filter( (array) parent::get_settings_for_display() ) ) );
		$editor = wpforms()->obj( 'themes_editor' );

		if ( ! $editor instanceof ThemesEditor ) {
			return $styles;
		}

		$themes_data = $editor->get_themes_data();

		if ( ( $styles['isCustomTheme'] ?? '' ) === 'true' ) {
			$styles['wpformsTheme'] = $themes_data->get_elementor_theme_slug( (string) $styles['wpformsTheme'] );
		}

		// The widget never had gradient controls. Its theme's gradient, or one derived from its own
		// colors, keeps the form default's gradient out and the theme comparison on the real values.
		$theme    = $themes_data->get_theme( (string) $styles['wpformsTheme'] );
		$styles   = array_merge( $styles, array_intersect_key( (array) ( $theme['settings'] ?? [] ), array_flip( self::GRADIENT_KEYS ) ) );
		$css_vars = $this->css_vars_obj;

		return $css_vars instanceof CSSVars ? $css_vars->add_gradient_defaults( $styles ) : $styles;
	}

	/**
	 * Get settings for display.
	 *
	 * @since 1.8.3
	 *
	 * @param string|null $setting_key Optional. The key of the requested setting. Default is null.
	 *
	 * @return mixed The settings.
	 */
	public function get_settings_for_display( $setting_key = null ) {

		$settings = parent::get_settings_for_display( $setting_key );

		if ( ! empty( $setting_key ) ) {
			return $settings;
		}

		return $this->prepare_settings( $settings );
	}

	/**
	 * Turn widget settings into the values the CSS variables are built from.
	 *
	 * @since 2.0.5
	 *
	 * @param mixed $settings Widget settings.
	 *
	 * @return mixed Updated settings.
	 */
	private function prepare_settings( $settings ) {

		$settings = $this->remove_empty_settings( $settings );
		$settings = $this->apply_dimension_settings( $settings );
		$settings = $this->apply_complex_settings( $settings );

		if ( isset( $settings['__globals__'] ) ) {
			$settings = $this->check_global_styles( $settings );
		}

		return $settings;
	}

	/**
	 * Remove empty settings.
	 *
	 * @since 1.9.6
	 *
	 * @param mixed $settings Widget settings.
	 *
	 * @return mixed Updated settings.
	 */
	private function remove_empty_settings( $settings ) {

		if ( ! is_array( $settings ) ) {
			return $settings;
		}

		return array_filter(
			$settings,
			static function ( $value ) {

				return ! empty( $value );
			}
		);
	}

	/**
	 * Apply complex settings values.
	 *
	 * @since 1.9.6
	 *
	 * @param mixed $settings Widget settings.
	 *
	 * @return mixed Updated settings.
	 */
	private function apply_complex_settings( $settings ) {

		if ( isset( $settings['backgroundUrl'] ) && is_array( $settings['backgroundUrl'] ) ) {
			$image_url                 = $settings['backgroundUrl']['url'] ?? '';
			$settings['backgroundUrl'] = 'url( ' . $image_url . ' )';
		}

		if ( isset( $settings['backgroundSize'] ) && $settings['backgroundSize'] === 'dimensions' ) {
			$bg_width  = $settings['backgroundWidth'] ?? CSSVars::ROOT_VARS['background-width'];
			$bg_height = $settings['backgroundHeight'] ?? CSSVars::ROOT_VARS['background-height'];

			$settings['backgroundSize'] = "{$bg_width} {$bg_height}";
		}

		return $settings;
	}

	/**
	 * Apply dimension settings with pixel units.
	 *
	 * @since 1.9.6
	 *
	 * @param mixed $settings Widget settings.
	 *
	 * @return mixed Updated settings with dimension values.
	 */
	private function apply_dimension_settings( $settings ) {

		$dimension_properties = [
			'fieldBorderRadius'     => 'field-border-radius',
			'fieldBorderSize'       => 'field-border-size',
			'buttonBorderRadius'    => 'button-border-radius',
			'buttonBorderSize'      => 'button-border-size',
			'containerPadding'      => 'container-padding',
			'containerBorderWidth'  => 'container-border-width',
			'containerBorderRadius' => 'container-border-radius',
			'backgroundWidth'       => 'background-width',
			'backgroundHeight'      => 'background-height',
		];

		foreach ( $dimension_properties as $property => $root_var ) {
			if ( ! isset( $settings[ $property ] ) ) {
				$settings[ $property ] = CSSVars::ROOT_VARS[ $root_var ];

				continue;
			}

			$value = (string) $settings[ $property ];

			if ( $value !== '' && substr( $value, -2 ) !== 'px' ) {
				$settings[ $property ] = $value . 'px';
			}
		}

		return $settings;
	}

	/**
	 * Check if global styles are used in colors controls and update its values with the real ones.
	 *
	 * @since 1.8.3
	 *
	 * @param mixed $settings Widget settings.
	 *
	 * @return mixed Updated settings.
	 */
	private function check_global_styles( $settings ) {

		$global_settings = $settings['__globals__'] ?? [];
		$kit             = Plugin::$instance->kits_manager->get_active_kit_for_frontend();
		$system_colors   = $kit->get_settings_for_display( 'system_colors' );
		$custom_colors   = $kit->get_settings_for_display( 'custom_colors' );
		$global_colors   = array_merge( $system_colors, $custom_colors );

		foreach ( $global_settings as $key => $value ) {
			if ( empty( $value ) ) {
				continue;
			}

			$color_id = str_replace( 'globals/colors?id=', '', $value );

			foreach ( $global_colors as $color ) {
				if ( $color['_id'] === $color_id ) {
					$settings[ $key ] = $color['color'];
				}
			}
		}

		return $settings;
	}
}
