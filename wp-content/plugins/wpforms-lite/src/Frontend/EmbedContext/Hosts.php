<?php

namespace WPForms\Frontend\EmbedContext;

use Elementor\Plugin as ElementorPlugin;
use WPForms\Frontend\EmbedContext;

/**
 * Where a rendered form is placed: the host part of an embed key.
 *
 * @since 2.0.5
 */
class Hosts {

	/**
	 * The synced pattern block, hosting its blocks by the pattern post.
	 *
	 * @since 2.0.5
	 */
	private const PATTERN_BLOCK = 'core/block';

	/**
	 * Blocks that act as the host of the blocks inside them.
	 *
	 * @since 2.0.5
	 */
	const ENTITY_BLOCKS = [ self::PATTERN_BLOCK, 'core/template-part' ];

	/**
	 * Widget id being rendered, empty outside a sidebar.
	 *
	 * @since 2.0.5
	 *
	 * @var string
	 */
	private $widget_id = '';

	/**
	 * Stack of synced patterns and template parts being rendered: `name`, `attrs`, and `host` once resolved.
	 *
	 * @since 2.0.5
	 *
	 * @var array
	 */
	private $entities = [];

	/**
	 * Posts whose content is being rendered, innermost last.
	 *
	 * @since 2.0.5
	 *
	 * @var string[]
	 */
	private $content_owners = [];

	/**
	 * Block template hosts resolved this request, by template id.
	 *
	 * @since 2.0.5
	 *
	 * @var array
	 */
	private $template_hosts = [];

	/**
	 * Hooks.
	 *
	 * @since 2.0.5
	 *
	 * @return void
	 */
	public function hooks(): void {

		add_action( 'dynamic_sidebar', [ $this, 'track_widget' ] );
		add_action( 'dynamic_sidebar_after', [ $this, 'forget_widget' ] );
		add_filter( 'render_block_data', [ $this, 'push_entity' ] );
		add_filter( 'render_block', [ $this, 'pop_entity' ], 10, 2 );

		// Around the whole content pipeline, so blocks (9) and shortcodes (11) render inside.
		add_filter( 'the_content', [ $this, 'start_content' ], 0 );
		add_filter( 'the_content', [ $this, 'end_content' ], PHP_INT_MAX );
	}

	/**
	 * Remember the post whose content is about to render.
	 *
	 * A Query Loop in the content switches the global post per item; a form placed in the
	 * loop still belongs to this content.
	 *
	 * @since 2.0.5
	 *
	 * @param string|mixed $content Post content.
	 *
	 * @return string|mixed
	 */
	public function start_content( $content ) {

		$this->content_owners[] = (string) absint( get_the_ID() );

		return $content;
	}

	/**
	 * Forget the post once its content rendered.
	 *
	 * @since 2.0.5
	 *
	 * @param string|mixed $content Post content.
	 *
	 * @return string|mixed
	 */
	public function end_content( $content ) {

		array_pop( $this->content_owners );

		return $content;
	}

	/**
	 * Remember the widget being rendered.
	 *
	 * @since 2.0.5
	 *
	 * @param array|mixed $widget Widget data from `dynamic_sidebar()`.
	 *
	 * @return void
	 */
	public function track_widget( $widget ): void {

		$this->widget_id = (string) ( $widget['id'] ?? '' );
	}

	/**
	 * Forget the widget once the sidebar is done.
	 *
	 * @since 2.0.5
	 *
	 * @return void
	 */
	public function forget_widget(): void {

		$this->widget_id = '';
	}

	/**
	 * Whether a widget is being rendered.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	public function is_widget(): bool {

		return $this->widget_id !== '';
	}

	/**
	 * Push a synced pattern or template part before its blocks render.
	 *
	 * The host is resolved later, only when a form renders inside.
	 *
	 * @since 2.0.5
	 *
	 * @param array|mixed $parsed_block Parsed block.
	 *
	 * @return array|mixed
	 */
	public function push_entity( $parsed_block ) {

		$name = (string) ( $parsed_block['blockName'] ?? '' );

		if ( ! in_array( $name, self::ENTITY_BLOCKS, true ) ) {
			return $parsed_block;
		}

		$this->entities[] = [
			'name'  => $name,
			'attrs' => (array) ( $parsed_block['attrs'] ?? [] ),
		];

		return $parsed_block;
	}

	/**
	 * Pop the entity once its blocks rendered.
	 *
	 * @since 2.0.5
	 *
	 * @param string|mixed $content Rendered block content.
	 * @param array|mixed  $block   Parsed block.
	 *
	 * @return string|mixed
	 */
	public function pop_entity( $content, $block ) {

		$name = (string) ( $block['blockName'] ?? '' );
		$top  = end( $this->entities );

		// Another pattern or part of the same block type closing must not pop this one.
		if ( $top && $top['name'] === $name && $this->get_entity_id( $top['name'], $top['attrs'] ) === $this->get_entity_id( $name, (array) ( $block['attrs'] ?? [] ) ) ) {
			array_pop( $this->entities );
		}

		return $content;
	}

	/**
	 * Resolve the host for a render of the given embed type.
	 *
	 * Widget, then the innermost synced pattern or template part with a host, then the
	 * Elementor document, then the post whose content is rendering, then the block
	 * template, then the post in the loop.
	 *
	 * @since 2.0.5
	 *
	 * @param string $type Embed type.
	 *
	 * @return string Post ID, widget id, `{theme}//{slug}`, or `0`.
	 */
	public function resolve( string $type ): string {

		if ( $this->is_widget() ) {
			return $this->widget_id;
		}

		$entity_host = $this->get_entity_host();

		if ( $entity_host !== '0' ) {
			return $entity_host;
		}

		if ( $type === EmbedContext::TYPE_ELEMENTOR ) {
			$document = $this->get_elementor_document_id();

			if ( $document ) {
				return (string) $document;
			}
		}

		$owner = (string) end( $this->content_owners );

		if ( $owner !== '' && $owner !== '0' ) {
			return $owner;
		}

		$template_host = $this->get_template_host();

		return $template_host !== '0' ? $template_host : (string) absint( get_the_ID() );
	}

	/**
	 * Get the host of the block template being rendered: its post once it was edited, or `0`.
	 *
	 * Core renders a block template before `wp_head`, so a form outside any post content then
	 * sits in the template; a form a plugin prints later in the page does not.
	 *
	 * @since 2.0.5
	 *
	 * @return string
	 */
	private function get_template_host(): string {

		// Core sets the id of the block template it renders since WordPress 6.3.
		$template_id = (string) ( $GLOBALS['_wp_current_template_id'] ?? '' );

		if ( $template_id === '' || did_action( 'wp_head' ) || ! function_exists( 'get_block_template' ) ) {
			return '0';
		}

		if ( ! isset( $this->template_hosts[ $template_id ] ) ) {
			$template = get_block_template( $template_id, 'wp_template' );

			// A file-based template is not editable in place: a form in it stays at the post.
			$this->template_hosts[ $template_id ] = ! empty( $template->wp_id ) ? (string) $template->wp_id : '0';
		}

		return $this->template_hosts[ $template_id ];
	}

	/**
	 * Get the host of the innermost entity that has one, resolving each entry once.
	 *
	 * @since 2.0.5
	 *
	 * @return string Host, or `0` when no entity on the stack has one.
	 */
	private function get_entity_host(): string {

		for ( $i = count( $this->entities ) - 1; $i >= 0; $i-- ) {
			if ( ! isset( $this->entities[ $i ]['host'] ) ) {
				$this->entities[ $i ]['host'] = $this->resolve_entity_host( $this->entities[ $i ]['name'], $this->entities[ $i ]['attrs'] );
			}

			if ( $this->entities[ $i ]['host'] !== '0' ) {
				return $this->entities[ $i ]['host'];
			}
		}

		return '0';
	}

	/**
	 * Resolve the host of one entity.
	 *
	 * @since 2.0.5
	 *
	 * @param string $name  Block name.
	 * @param array  $attrs Block attributes.
	 *
	 * @return string
	 */
	private function resolve_entity_host( string $name, array $attrs ): string {

		return $name === self::PATTERN_BLOCK ? (string) absint( $attrs['ref'] ?? 0 ) : $this->get_template_part_host( $attrs );
	}

	/**
	 * Get the identity of an entity block, to match its closing render with its opening one.
	 *
	 * @since 2.0.5
	 *
	 * @param string $name  Block name.
	 * @param array  $attrs Block attributes.
	 *
	 * @return string
	 */
	private function get_entity_id( string $name, array $attrs ): string {

		if ( $name === self::PATTERN_BLOCK ) {
			return (string) ( $attrs['ref'] ?? '' );
		}

		return (string) ( $attrs['theme'] ?? '' ) . '//' . (string) ( $attrs['slug'] ?? '' );
	}

	/**
	 * Get the host of a template part: its post when it was edited, else theme and slug.
	 *
	 * @since 2.0.5
	 *
	 * @param array $attrs Block attributes.
	 *
	 * @return string
	 */
	private function get_template_part_host( array $attrs ): string {

		$raw_slug = (string) ( $attrs['slug'] ?? '' );
		$slug     = sanitize_key( $raw_slug );

		if ( $slug === '' ) {
			return '0';
		}

		// Core renders a part without a theme attribute from the active theme.
		$theme    = ! empty( $attrs['theme'] ) ? (string) $attrs['theme'] : get_stylesheet();
		$template = function_exists( 'get_block_template' ) ? get_block_template( "{$theme}//{$raw_slug}", 'wp_template_part' ) : null;

		return ! empty( $template->wp_id ) ? (string) $template->wp_id : "{$theme}//{$slug}";
	}

	/**
	 * Get the main Elementor document being rendered: the template for a header, footer or popup.
	 *
	 * @since 2.0.5
	 *
	 * @return int
	 */
	private function get_elementor_document_id(): int {

		if ( ! class_exists( ElementorPlugin::class ) || empty( ElementorPlugin::$instance->documents ) ) {
			return 0;
		}

		$document = ElementorPlugin::$instance->documents->get_current();

		return $document ? (int) $document->get_main_id() : 0;
	}
}
