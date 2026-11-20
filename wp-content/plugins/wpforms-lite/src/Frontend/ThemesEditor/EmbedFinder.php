<?php

namespace WPForms\Frontend\ThemesEditor;

use WPForms\Frontend\EmbedContext;

/**
 * Find a form inside a host's content: the save check and the live keys of a host.
 *
 * @since 2.0.5
 */
class EmbedFinder {

	/**
	 * Block names.
	 *
	 * @since 2.0.5
	 */
	const BLOCK_FORM    = 'wpforms/form-selector';
	const BLOCK_DIVI    = 'wpforms/divi-form-selector';
	const BLOCK_PATTERN = 'core/block';

	/**
	 * Elementor widget name.
	 *
	 * @since 2.0.5
	 */
	const ELEMENTOR_WIDGET = 'wpforms';

	/**
	 * Live keys `has()` already read this request, by host.
	 *
	 * @since 2.0.5
	 *
	 * @var array
	 */
	private $cache = [];

	/**
	 * Whether the form is embedded at this key.
	 *
	 * @since 2.0.5
	 *
	 * @param array $embed   Parsed key: `type`, `host`, `id`.
	 * @param int   $form_id Form ID.
	 *
	 * @return bool
	 */
	public function has( array $embed, int $form_id ): bool {

		$type = (string) ( $embed['type'] ?? '' );
		$host = (string) ( $embed['host'] ?? '' );
		$id   = (string) ( $embed['id'] ?? '' );
		$key  = $type === EmbedContext::TYPE_WIDGET ? "widget:{$host}" : "{$type}:{$host}:{$id}";

		$found = null;

		// A Divi module embeds as a shortcode key, so a `divi:` key is never live.
		if ( in_array( $type, [ EmbedContext::TYPE_BLOCK, EmbedContext::TYPE_SHORTCODE, EmbedContext::TYPE_ELEMENTOR, EmbedContext::TYPE_WIDGET ], true ) ) {
			// The panel checks every embed of a page; one parse per host is enough for a request.
			$this->cache[ $host ] = $this->cache[ $host ] ?? $this->get_live_keys( $host );
			$found                = isset( $this->cache[ $host ][ $key ] ) && $this->cache[ $host ][ $key ] === $form_id;
		}

		/**
		 * Filter whether a form is embedded at a key of an addon's type.
		 *
		 * @since 2.0.5
		 *
		 * @param bool|null $found   True or false; null for a type core does not know.
		 * @param array     $embed   Parsed key.
		 * @param int       $form_id Form ID.
		 */
		return (bool) apply_filters( 'wpforms_frontend_themes_editor_embed_finder_has', $found, $embed, $form_id );
	}

	/**
	 * Get the keys a host's content yields.
	 *
	 * @since 2.0.5
	 *
	 * @param string $host Host.
	 *
	 * @return array Key => form ID, in content order.
	 */
	public function get_live_keys( string $host ): array {

		/**
		 * Filter the keys a host's content yields.
		 *
		 * An addon whose builder keeps its content outside `post_content` adds the keys it finds there.
		 *
		 * @since 2.0.5
		 *
		 * @param array  $keys Key => form ID.
		 * @param string $host Host.
		 */
		return (array) apply_filters( 'wpforms_frontend_themes_editor_embed_finder_get_live_keys', $this->get_core_live_keys( $host ), $host );
	}

	/**
	 * Get the keys core finds in a host's content.
	 *
	 * @since 2.0.5
	 *
	 * @param string $host Host.
	 *
	 * @return array Key => form ID, in content order.
	 */
	private function get_core_live_keys( string $host ): array {

		if ( ! EmbedContext::is_post_host( $host ) ) {
			return strpos( $host, '//' ) !== false ? $this->get_template_part_keys( $host ) : $this->get_widget_keys( $host );
		}

		$post = get_post( (int) $host );

		if ( ! $post ) {
			return [];
		}

		return $this->get_content_keys( (string) $post->post_content, $host ) + $this->get_elementor_keys( (int) $host );
	}

	/**
	 * Keys of block, shortcode and Divi embeds in post content.
	 *
	 * @since 2.0.5
	 *
	 * @param string $content Content.
	 * @param string $host    Host.
	 *
	 * @return array
	 */
	private function get_content_keys( string $content, string $host ): array {

		$keys = [];

		if ( trim( $content ) === '' ) {
			return $keys;
		}

		$this->collect_block_keys( parse_blocks( $content ), $host, $keys );

		return $keys + $this->get_shortcode_keys( $content, $host );
	}

	/**
	 * Keys of `[wpforms id="N"]` and the Divi 4 module `[wpforms_selector form_id="N"]`.
	 *
	 * The core parser, so quoting, attribute order and an escaped `[[wpforms]]` follow the render.
	 *
	 * @since 2.0.5
	 *
	 * @param string $content Content.
	 * @param string $host    Host.
	 *
	 * @return array
	 */
	private function get_shortcode_keys( string $content, string $host ): array {

		$keys = [];

		if ( ! preg_match_all( '/' . get_shortcode_regex( [ 'wpforms', 'wpforms_selector' ] ) . '/', $content, $matches, PREG_SET_ORDER ) ) {
			return $keys;
		}

		foreach ( $matches as $shortcode ) {
			// `[[wpforms id="N"]]` prints the shortcode as text.
			if ( $shortcode[1] === '[' && $shortcode[6] === ']' ) {
				continue;
			}

			$atts    = shortcode_parse_atts( $shortcode[3] );
			$atts    = is_array( $atts ) ? $atts : [];
			$form_id = absint( $atts['id'] ?? ( $atts['form_id'] ?? 0 ) );

			if ( $form_id ) {
				$keys[ "shortcode:{$host}:{$form_id}" ] = $form_id;
			}
		}

		return $keys;
	}

	/**
	 * Walk the block tree; a synced pattern is its own host and is not followed.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $blocks Parsed blocks.
	 * @param string $host   Host.
	 * @param array  $keys   Keys collected so far, by reference.
	 *
	 * @return void
	 */
	private function collect_block_keys( array $blocks, string $host, array &$keys ): void {

		foreach ( $blocks as $block ) {
			$keys += $this->get_block_key( $block, $host );

			$name = (string) ( $block['blockName'] ?? '' );

			if ( $name !== self::BLOCK_PATTERN && ! empty( $block['innerBlocks'] ) ) {
				$this->collect_block_keys( (array) $block['innerBlocks'], $host, $keys );
			}
		}
	}

	/**
	 * Key of a `wpforms/form-selector` or `wpforms/divi-form-selector` block.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $block Parsed block.
	 * @param string $host  Host.
	 *
	 * @return array Empty for any other block, or a zero form ID.
	 */
	private function get_block_key( array $block, string $host ): array {

		$name  = (string) ( $block['blockName'] ?? '' );
		$attrs = (array) ( $block['attrs'] ?? [] );

		if ( $name === self::BLOCK_FORM ) {
			return $this->get_form_block_key( $attrs, $host );
		}

		if ( $name === self::BLOCK_DIVI ) {
			return $this->get_divi_block_key( $attrs, $host );
		}

		return [];
	}

	/**
	 * Key of a `wpforms/form-selector` block: `clientId` when present, else its form ID as a shortcode key.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $host  Host.
	 *
	 * @return array Empty for a zero form ID.
	 */
	private function get_form_block_key( array $attrs, string $host ): array {

		$form_id   = absint( $attrs['formId'] ?? 0 );
		$client_id = sanitize_key( (string) ( $attrs['clientId'] ?? '' ) );
		$key       = $client_id !== '' ? "block:{$host}:{$client_id}" : "shortcode:{$host}:{$form_id}";

		return $form_id ? [ $key => $form_id ] : [];
	}

	/**
	 * Key of a `wpforms/divi-form-selector` block, by its `formId.desktop.value`.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $attrs Block attributes.
	 * @param string $host  Host.
	 *
	 * @return array Empty for a zero form ID.
	 */
	private function get_divi_block_key( array $attrs, string $host ): array {

		$value   = $attrs['formId']['desktop']['value'] ?? ( $attrs['formId'] ?? 0 );
		$form_id = is_scalar( $value ) ? absint( $value ) : 0;

		return $form_id ? [ "shortcode:{$host}:{$form_id}" => $form_id ] : [];
	}

	/**
	 * Keys of the WPForms widgets in an Elementor document.
	 *
	 * @since 2.0.5
	 *
	 * @param int $post_id Document post ID.
	 *
	 * @return array
	 */
	private function get_elementor_keys( int $post_id ): array {

		$raw = get_post_meta( $post_id, '_elementor_data', true );

		// Elementor may hand the meta back already decoded.
		$elements = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		$keys     = [];

		if ( is_array( $elements ) ) {
			$this->collect_elementor_keys( $elements, (string) $post_id, $keys );
		}

		return $keys;
	}

	/**
	 * Walk the Elementor element tree.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $elements Elements.
	 * @param string $host     Host.
	 * @param array  $keys     Keys collected so far, by reference.
	 *
	 * @return void
	 */
	private function collect_elementor_keys( array $elements, string $host, array &$keys ): void {

		foreach ( $elements as $element ) {
			if ( ( $element['widgetType'] ?? '' ) === self::ELEMENTOR_WIDGET ) {
				$form_id = absint( $element['settings']['form_id'] ?? 0 );
				$id      = sanitize_key( (string) ( $element['id'] ?? '' ) );

				if ( $form_id && $id !== '' ) {
					$keys[ "elementor:{$host}:{$id}" ] = $form_id;
				}
			}

			if ( ! empty( $element['elements'] ) ) {
				$this->collect_elementor_keys( (array) $element['elements'], $host, $keys );
			}
		}
	}

	/**
	 * Key of a widget that holds the form, when the widget is placed.
	 *
	 * @since 2.0.5
	 *
	 * @param string $widget_id Widget id, e.g. `wpforms-widget-3`.
	 *
	 * @return array
	 */
	private function get_widget_keys( string $widget_id ): array {

		if ( ! $this->is_widget_placed( $widget_id ) || ! preg_match( '/^(.+)-(\d+)$/', $widget_id, $parts ) ) {
			return [];
		}

		$instances = get_option( 'widget_' . $parts[1], [] );
		$instance  = is_array( $instances ) ? (array) ( $instances[ (int) $parts[2] ] ?? [] ) : [];
		$form_id   = absint( $instance['form_id'] ?? 0 );

		if ( ! $form_id ) {
			$content = (string) ( $instance['text'] ?? ( $instance['content'] ?? '' ) );
			$form_id = (int) ( array_values( $this->get_content_keys( $content, $widget_id ) )[0] ?? 0 );
		}

		return $form_id ? [ "widget:{$widget_id}" => $form_id ] : [];
	}

	/**
	 * Whether a widget id sits in a sidebar; inactive widgets count.
	 *
	 * @since 2.0.5
	 *
	 * @param string $widget_id Widget id.
	 *
	 * @return bool
	 */
	private function is_widget_placed( string $widget_id ): bool {

		$sidebars = get_option( 'sidebars_widgets', [] );

		foreach ( is_array( $sidebars ) ? $sidebars : [] as $widgets ) {
			if ( is_array( $widgets ) && in_array( $widget_id, $widgets, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Keys inside a file-based template part, `{theme}//{slug}`.
	 *
	 * @since 2.0.5
	 *
	 * @param string $host Host.
	 *
	 * @return array
	 */
	private function get_template_part_keys( string $host ): array {

		if ( ! function_exists( 'get_block_template' ) ) {
			return [];
		}

		$template = get_block_template( $host, 'wp_template_part' );

		return $template ? $this->get_content_keys( (string) $template->content, $host ) : [];
	}
}
