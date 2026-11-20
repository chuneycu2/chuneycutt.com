<?php

namespace WPForms\Integrations\Gutenberg;

use WPForms\Frontend\CSSVars;
use WPForms\Frontend\Themes\SettingsSanitizer;
use WPForms\Helpers\File;

/**
 * Rest API for Gutenberg block.
 *
 * @since 1.8.8
 */
abstract class ThemesData {

	/**
	 * Custom themes JSON file path.
	 *
	 * Relative to `wp-content/uploads/wpforms` directory.
	 *
	 * @since 1.8.8
	 *
	 * @var string
	 */
	const THEMES_CUSTOM_JSON_PATH = 'themes/themes-custom.json';

	/**
	 * WPForms themes JSON file path for lite version.
	 *
	 * Relative to WPForms plugin directory.
	 *
	 * @since 1.8.8
	 *
	 * @var string
	 */
	const THEMES_WPFORMS_JSON_PATH_LITE = 'assets/lite/js/integrations/gutenberg/themes.json';

	/**
	 * Custom themes JSON file the Elementor widget kept until its style controls were removed.
	 *
	 * Relative to `wp-content/uploads/wpforms` directory. Read only, never deleted, so a downgrade keeps working.
	 *
	 * @since 2.0.5
	 */
	private const ELEMENTOR_CUSTOM_JSON_PATH = 'themes/elementor/themes-custom.json';

	/**
	 * Option listing the Elementor custom themes written into the custom themes file, with the slug each has there.
	 *
	 * For a listed theme the file is the only source, so a merged theme deleted or edited later stays that way.
	 *
	 * @since 2.0.5
	 */
	const ELEMENTOR_MERGED_OPTION = 'wpforms_themes_elementor_merged';

	/**
	 * Removed stock theme slugs and the near-duplicate stock theme each still resolves to.
	 *
	 * @since 2.0.5
	 */
	private const THEME_ALIASES = [
		'spring' => 'tranquil',
	];

	/**
	 * Custom themes file path.
	 *
	 * @since 1.8.8
	 *
	 * @var string
	 */
	private $custom_themes_file_path;

	/**
	 * WPForms themes data.
	 *
	 * @since 1.8.8
	 *
	 * @var array
	 */
	protected $wpforms_themes;

	/**
	 * Custom themes data.
	 *
	 * @since 1.8.8
	 *
	 * @var array
	 */
	private $custom_themes;

	/**
	 * Custom themes of the Elementor widget, sanitized.
	 *
	 * @since 2.0.5
	 *
	 * @var array|null
	 */
	private $elementor_themes;

	/**
	 * Slug of each Elementor custom theme among the custom themes, keyed by its slug in the Elementor file.
	 *
	 * @since 2.0.5
	 *
	 * @var array
	 */
	private $elementor_slugs = [];

	/**
	 * Return WPForms themes.
	 *
	 * @since 1.8.8
	 *
	 * @return array
	 */
	public function get_wpforms_themes(): array {

		if ( $this->wpforms_themes !== null ) {
			return $this->wpforms_themes;
		}

		$themes_json = File::get_contents( WPFORMS_PLUGIN_DIR . static::THEMES_WPFORMS_JSON_PATH ) ?? '{}';
		$themes      = json_decode( $themes_json, true );

		$this->wpforms_themes = ! empty( $themes ) ? $themes : [];

		return $this->wpforms_themes;
	}

	/**
	 * Return custom themes.
	 *
	 * @since 1.8.8
	 *
	 * @return array
	 */
	public function get_custom_themes(): array {

		if ( $this->custom_themes !== null ) {
			return $this->custom_themes;
		}

		$themes_json = File::get_contents( $this->get_custom_themes_file_path() ) ?? '{}';
		$themes      = json_decode( $themes_json, true );

		$this->custom_themes = $this->add_elementor_themes( ! empty( $themes ) ? $themes : [] );

		return $this->custom_themes;
	}

	/**
	 * Add the Elementor widget's custom themes not yet written into the custom themes file.
	 *
	 * Each joins as `{slug}-elementor`, never under its own slug, so a form naming a deleted slug
	 * cannot pick up an Elementor theme of that name. A slug merged before keeps the file's state.
	 *
	 * @since 2.0.5
	 *
	 * @param array $themes Custom themes from the custom themes file.
	 *
	 * @return array
	 */
	private function add_elementor_themes( array $themes ): array {

		$merged = get_option( self::ELEMENTOR_MERGED_OPTION );

		$this->elementor_slugs = is_array( $merged ) ? (array) ( $merged['slugs'] ?? [] ) : [];

		foreach ( $this->get_elementor_themes() as $slug => $theme ) {
			if ( isset( $this->elementor_slugs[ $slug ] ) ) {
				continue;
			}

			[ $target, $is_present ] = $this->locate_elementor_theme( $themes, (string) $slug, $theme );

			$this->elementor_slugs[ $slug ] = $target;

			if ( $is_present ) {
				continue;
			}

			if ( in_array( $theme['name'] ?? '', array_column( $themes, 'name' ), true ) ) {
				/* translators: %s - custom theme name. */
				$theme['name'] = sprintf( __( '%s – Elementor', 'wpforms-lite' ), $theme['name'] );
			}

			$themes[ $target ] = $theme;
		}

		return $themes;
	}

	/**
	 * Record the Elementor themes the custom themes file holds, with the slug each has there.
	 *
	 * The slugs are kept because a merged theme edited later no longer matches its Elementor original.
	 * Every pending theme is recorded, written or not: from now on the file is the only source, so a
	 * merged theme deleted in this save stays deleted.
	 *
	 * @since 2.0.5
	 */
	private function record_elementor_merge(): void {

		$this->get_custom_themes();

		$merged = get_option( self::ELEMENTOR_MERGED_OPTION );
		$slugs  = is_array( $merged ) ? (array) ( $merged['slugs'] ?? [] ) : [];
		$added  = array_diff_key( $this->elementor_slugs, $slugs );

		if ( empty( $added ) && $merged ) {
			return;
		}

		update_option(
			self::ELEMENTOR_MERGED_OPTION,
			[
				'time'  => time(),
				'slugs' => $slugs + $added,
			],
			false
		);
	}

	/**
	 * Find an Elementor theme among the custom themes: the slug holding it, or the slug it joins under.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $themes Custom themes.
	 * @param string $slug   Slug in the Elementor widget's custom themes file.
	 * @param array  $theme  Elementor theme.
	 *
	 * @return array The slug, and whether a theme with these settings is already there.
	 */
	private function locate_elementor_theme( array $themes, string $slug, array $theme ): array {

		if ( isset( $themes[ $slug ] ) && $this->is_same_theme( (array) $themes[ $slug ], $theme ) ) {
			return [ $slug, true ];
		}

		$target = "{$slug}-elementor";

		for ( $suffix = 2; isset( $themes[ $target ] ); $suffix++ ) {
			if ( $this->is_same_theme( (array) $themes[ $target ], $theme ) ) {
				return [ $target, true ];
			}

			$target = "{$slug}-elementor-{$suffix}";
		}

		return [ $target, false ];
	}

	/**
	 * Get the slug an Elementor widget's custom theme has among the custom themes.
	 *
	 * A widget saved with the style controls names the theme by its slug in the Elementor
	 * widget's own file, which the merge may have suffixed.
	 *
	 * @since 2.0.5
	 *
	 * @param string $slug Slug in the Elementor widget's custom themes file.
	 *
	 * @return string
	 */
	public function get_elementor_theme_slug( string $slug ): string {

		$this->get_custom_themes();

		return (string) ( $this->elementor_slugs[ $slug ] ?? $slug );
	}

	/**
	 * Get the Elementor widget's custom themes, sanitized as the custom themes file stores them.
	 *
	 * @since 2.0.5
	 *
	 * @return array
	 */
	private function get_elementor_themes(): array {

		if ( $this->elementor_themes !== null ) {
			return $this->elementor_themes;
		}

		$this->elementor_themes = [];

		$upload_dir = wpforms_upload_dir();
		$file_path  = ! empty( $upload_dir['path'] ) ? trailingslashit( wp_normalize_path( $upload_dir['path'] ) ) . self::ELEMENTOR_CUSTOM_JSON_PATH : '';
		$themes     = $file_path ? json_decode( File::get_contents( $file_path ) ?? '{}', true ) : [];

		if ( empty( $themes ) || ! is_array( $themes ) ) {
			return [];
		}

		// Called before add_elementor_themes() maps anything, so these are the recorded slugs only, which it skips.
		$themes = array_diff_key( $themes, $this->elementor_slugs );

		if ( empty( $themes ) ) {
			return [];
		}

		$stock_themes = $this->get_wpforms_themes();

		// The themes are their own stored values: keys the current user may not edit keep them.
		$this->elementor_themes = SettingsSanitizer::get_instance()->sanitize_custom_themes(
			$themes,
			$themes,
			$stock_themes['default']['name'] ?? '',
			array_keys( $stock_themes )
		);

		$this->elementor_themes = $this->resolve_themes_background_settings( $this->elementor_themes );

		return $this->elementor_themes;
	}

	/**
	 * Give each theme the background style its widget renders: an image style without an image is None or Solid.
	 *
	 * The panel compares a theme with what the widget renders.
	 *
	 * @since 2.0.5
	 *
	 * @param array $themes Custom themes.
	 *
	 * @return array
	 */
	private function resolve_themes_background_settings( array $themes ): array {

		$css_vars = wpforms()->obj( 'css_vars' );

		if ( ! $css_vars instanceof CSSVars ) {
			return $themes;
		}

		foreach ( $themes as $slug => $theme ) {
			$themes[ $slug ]['settings'] = $css_vars->resolve_background_settings( (array) ( $theme['settings'] ?? [] ) );
		}

		return $themes;
	}

	/**
	 * Whether a custom theme already holds every setting of an Elementor theme.
	 *
	 * Keys the Elementor theme lacks, such as the gradient ones added later, do not count.
	 *
	 * @since 2.0.5
	 *
	 * @param array $theme     Custom theme.
	 * @param array $elementor Elementor theme.
	 *
	 * @return bool
	 */
	private function is_same_theme( array $theme, array $elementor ): bool {

		$settings = (array) ( $elementor['settings'] ?? [] );
		$stored   = array_intersect_key( (array) ( $theme['settings'] ?? [] ), $settings );

		// Key order differs between the files.
		ksort( $settings );
		ksort( $stored );

		return $stored === $settings;
	}

	/**
	 * Return theme data.
	 *
	 * @since 1.8.8
	 *
	 * @param string $slug Theme slug.
	 *
	 * @return array|null
	 */
	public function get_theme( string $slug ) {

		$slug    = $this->resolve_slug( $slug );
		$wpforms = $this->get_wpforms_themes();

		if ( ! empty( $wpforms[ $slug ] ) ) {
			return $wpforms[ $slug ];
		}

		$custom = $this->get_custom_themes();

		if ( ! empty( $custom[ $slug ] ) ) {
			return $custom[ $slug ];
		}

		return null;
	}

	/**
	 * Get the slug a stored theme slug resolves to: a removed stock theme's slug gives the theme replacing it.
	 *
	 * @since 2.0.5
	 *
	 * @param string $slug Theme slug.
	 *
	 * @return string
	 */
	public function resolve_slug( string $slug ): string {

		return self::THEME_ALIASES[ $slug ] ?? $slug;
	}

	/**
	 * Get custom themes json file path.
	 *
	 * @since 1.8.8
	 *
	 * @return string|bool File path OR false in the case of permissions error.
	 */
	public function get_custom_themes_file_path() {

		// Caching the file path in the class property.
		if ( $this->custom_themes_file_path !== null ) {
			return $this->custom_themes_file_path;
		}

		// Determine custom themes file path.
		$upload_dir  = wpforms_upload_dir();
		$upload_path = ! empty( $upload_dir['path'] ) ? $upload_dir['path'] : WP_CONTENT_DIR . 'uploads/wpforms/';
		$upload_path = trailingslashit( wp_normalize_path( $upload_path ) );
		$file_path   = $upload_path . self::THEMES_CUSTOM_JSON_PATH;
		$dirname     = dirname( $file_path );

		// If the directory doesn't exist, create it. Also, check for permissions.
		if ( ! wp_mkdir_p( $dirname ) ) {
			$file_path = false;
		}

		$this->custom_themes_file_path = $file_path;

		return $file_path;
	}

	/**
	 * Sanitize custom themes data.
	 *
	 * Settings go through the theme schema sanitizer; keys the user may not edit keep their stored value.
	 *
	 * @since 1.8.8
	 *
	 * @param array $custom_themes Custom themes data.
	 *
	 * @return array
	 */
	private function sanitize_custom_themes_data( array $custom_themes ): array {

		$stock_themes = $this->get_wpforms_themes();

		return SettingsSanitizer::get_instance()->sanitize_custom_themes(
			$custom_themes,
			$this->get_custom_themes(),
			$stock_themes['default']['name'] ?? '',
			array_keys( $stock_themes )
		);
	}

	/**
	 * Update custom themes data.
	 *
	 * @since 1.8.8
	 *
	 * @param array $custom_themes Custom themes data.
	 *
	 * @return bool
	 */
	public function update_custom_themes_file( array $custom_themes ): bool {

		// Sanitize custom themes data to be saved.
		$sanitized_themes = $this->sanitize_custom_themes_data( $custom_themes );

		// Determine custom themes file path.
		$themes_file = $this->get_custom_themes_file_path();
		$json_data   = ! empty( $sanitized_themes ) ? wp_json_encode( $sanitized_themes ) : '{}';

		// Save custom themes data and return the result.
		if ( ! File::put_contents( $themes_file, $json_data ) ) {
			return false;
		}

		// Clients edit the list get_custom_themes() served, so the file holds the merged Elementor themes now.
		$this->record_elementor_merge();

		return true;
	}
}
