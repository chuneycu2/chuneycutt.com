<?php

namespace WPForms\Frontend\ThemesEditor;

use WP_Post;
use WPForms\Frontend\EmbedContext;

/**
 * Per-embed theme overrides: storage, cache purge and orphan cleanup.
 *
 * A post host keeps all its overrides in one meta array; widgets and
 * file-based template parts share one option with the same shape.
 *
 * @since 2.0.5
 */
class Overrides {

	/**
	 * Post meta key of a post host.
	 *
	 * @since 2.0.5
	 */
	const META_KEY = '_wpforms_embed_themes';

	/**
	 * Option key of the non-post hosts.
	 *
	 * @since 2.0.5
	 */
	const OPTION_KEY = 'wpforms_embed_themes';

	/**
	 * Option name prefix of a classic widget's instances.
	 *
	 * @since 2.0.5
	 */
	const WIDGET_OPTION_PREFIX = 'widget_';

	/**
	 * Embed context.
	 *
	 * @since 2.0.5
	 *
	 * @var EmbedContext
	 */
	private $embed_context;

	/**
	 * Constructor.
	 *
	 * @since 2.0.5
	 *
	 * @param EmbedContext|null $embed_context Embed context.
	 */
	public function __construct( ?EmbedContext $embed_context = null ) {

		$this->embed_context = $embed_context ?? wpforms()->obj( 'embed_context' );
	}

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

		add_action( 'save_post', [ $this, 'on_save_post' ], 20, 2 );
		add_action( 'elementor/document/after_save', [ $this, 'on_elementor_save' ] );
		add_action( 'update_option_sidebars_widgets', [ $this, 'on_sidebars_widgets_updated' ], 10, 2 );
		add_action( 'updated_option', [ $this, 'on_widget_option_updated' ] );
		add_action( 'before_delete_post', [ $this, 'on_before_delete_post' ], 10, 2 );
	}

	/**
	 * Prune a saved host that has overrides.
	 *
	 * @since 2.0.5
	 *
	 * @param int|mixed     $post_id Post ID.
	 * @param WP_Post|mixed $post    Post.
	 *
	 * @return void
	 */
	public function on_save_post( $post_id, $post ): void {

		$post_id = (int) $post_id;

		if ( ! $post instanceof WP_Post || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$this->prune_post_host( $post_id );
	}

	/**
	 * Prune an Elementor document that has overrides.
	 *
	 * @since 2.0.5
	 *
	 * @param object|mixed $document Elementor document.
	 *
	 * @return void
	 */
	public function on_elementor_save( $document ): void {

		if ( ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) ) {
			return;
		}

		$this->prune_post_host( (int) $document->get_main_id() );
	}

	/**
	 * Prune the widgets no sidebar holds any more; inactive widgets stay.
	 *
	 * @since 2.0.5
	 *
	 * @param array|mixed $old_value Previous sidebars.
	 * @param array|mixed $value     Current sidebars.
	 *
	 * @return void
	 */
	public function on_sidebars_widgets_updated( $old_value, $value ): void {

		$items = $this->get_for_host( '' );

		if ( ! $items ) {
			return;
		}

		$placed = [];

		foreach ( is_array( $value ) ? $value : [] as $widgets ) {
			$placed = array_merge( $placed, is_array( $widgets ) ? array_map( 'strval', $widgets ) : [] );
		}

		$live = [];

		foreach ( array_keys( $items ) as $key ) {
			$embed = $this->embed_context->parse( (string) $key );

			if ( ! $embed || $embed['type'] !== EmbedContext::TYPE_WIDGET || in_array( $embed['host'], $placed, true ) ) {
				$live[ (string) $key ] = null;
			}
		}

		$this->prune( '', $live );
	}

	/**
	 * Prune the widgets of a saved widget type whose form changed or went away.
	 *
	 * Switching a widget's form rewrites its `widget_{id_base}` option only, so
	 * `sidebars_widgets` never sees it.
	 *
	 * @since 2.0.5
	 *
	 * @param string|mixed $option Option name.
	 *
	 * @return void
	 */
	public function on_widget_option_updated( $option ): void {

		$option = (string) $option;

		if ( strpos( $option, self::WIDGET_OPTION_PREFIX ) !== 0 ) {
			return;
		}

		$items = $this->get_for_host( '' );

		if ( ! $items ) {
			return;
		}

		$id_base = substr( $option, strlen( self::WIDGET_OPTION_PREFIX ) );
		$finder  = wpforms()->obj( 'themes_editor_embed_finder' );
		$live    = [];

		foreach ( array_keys( $items ) as $key ) {
			$embed = $this->embed_context->parse( (string) $key );

			// Only this widget type is re-read; every other item is untouched.
			if ( ! $embed || $embed['type'] !== EmbedContext::TYPE_WIDGET || ! preg_match( '/^' . preg_quote( $id_base, '/' ) . '-\d+$/', $embed['host'] ) ) {
				$live[ (string) $key ] = null;

				continue;
			}

			$live += $finder->get_live_keys( $embed['host'] );
		}

		$this->prune( '', $live );
	}

	/**
	 * Clear a form's overrides while its meta still exists.
	 *
	 * `wpforms_delete_form` fires after `wp_delete_post()`, when the Locator meta is gone.
	 *
	 * @since 2.0.5
	 *
	 * @param int|mixed     $post_id Post ID.
	 * @param WP_Post|mixed $post    Post.
	 *
	 * @return void
	 */
	public function on_before_delete_post( $post_id, $post ): void {

		if ( $post instanceof WP_Post && $post->post_type === 'wpforms' ) {
			$this->delete_for_form( (int) $post_id );
		}
	}

	/**
	 * Prune a post host, gated on it having overrides and on non-empty content.
	 *
	 * @since 2.0.5
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	private function prune_post_host( int $post_id ): void {

		if ( ! $post_id || ! metadata_exists( 'post', $post_id, self::META_KEY ) ) {
			return;
		}

		$post = get_post( $post_id );

		// A blanked post or a parser failure must not delete what the page still shows.
		if ( ! $post || ( trim( (string) $post->post_content ) === '' && empty( get_post_meta( $post_id, '_elementor_data', true ) ) ) ) {
			return;
		}

		$live = wpforms()->obj( 'themes_editor_embed_finder' )->get_live_keys( (string) $post_id );

		if ( ! $live ) {
			return;
		}

		// Core cannot see an addon's embeds in the content, so it never prunes their keys.
		foreach ( array_keys( $this->get_for_host( (string) $post_id ) ) as $key ) {
			if ( ! $this->is_core_key( (string) $key ) ) {
				$live[ (string) $key ] = null;
			}
		}

		$this->prune( (string) $post_id, $live );
	}

	/**
	 * Whether a key is of a type core finds in content; a malformed key counts, so prune drops it.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key Embed key.
	 *
	 * @return bool
	 */
	private function is_core_key( string $key ): bool {

		$embed = $this->embed_context->parse( $key );
		$types = [
			EmbedContext::TYPE_BLOCK,
			EmbedContext::TYPE_SHORTCODE,
			EmbedContext::TYPE_ELEMENTOR,
			EmbedContext::TYPE_WIDGET,
			EmbedContext::TYPE_DIVI,
		];

		return ! $embed || in_array( $embed['type'], $types, true );
	}

	/**
	 * Get the override of an embed.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key Embed key.
	 *
	 * @return array `formId` and `settings`; empty when there is none.
	 */
	public function get( string $key ): array {

		$host_items = $this->get_host_items( $key );

		return isset( $host_items[1][ $key ] ) ? (array) $host_items[1][ $key ] : [];
	}

	/**
	 * Get every override of a host.
	 *
	 * @since 2.0.5
	 *
	 * @param string $host Host: post ID, widget id or `{theme}//{slug}`; '' is the whole option store.
	 *
	 * @return array Embed key => item.
	 */
	private function get_for_host( string $host ): array {

		if ( EmbedContext::is_post_host( $host ) ) {
			$items = get_post_meta( (int) $host, self::META_KEY, true );

			return is_array( $items ) ? $items : [];
		}

		$items = get_option( self::OPTION_KEY, [] );
		$items = is_array( $items ) ? $items : [];

		if ( $host === '' ) {
			return $items;
		}

		return array_filter(
			$items,
			function ( $key ) use ( $host ) {
				return ( $this->embed_context->parse( (string) $key )['host'] ?? null ) === $host;
			},
			ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * Store the override of an embed.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key             Embed key.
	 * @param int    $form_id         Form ID.
	 * @param array  $settings        Sanitized theme settings in the form storage shape.
	 * @param bool   $owns_custom_css Whether the save could edit the embed's Custom CSS, so it replaces a block's own.
	 * @param bool   $is_block_css    Whether the Custom CSS taken over is the block's own, complete rules.
	 *
	 * @return bool
	 */
	public function set( string $key, int $form_id, array $settings, bool $owns_custom_css = false, bool $is_block_css = false ): bool {

		$host_items = $this->get_host_items( $key );

		if ( ! $host_items ) {
			return false;
		}

		[ $host, $items ] = $host_items;

		$item = [
			'formId'   => $form_id,
			'settings' => $settings,
		];

		// Once taken over, a later save by someone who cannot edit Custom CSS must not bring the block's back.
		if ( $owns_custom_css || ! empty( $items[ $key ]['ownsCustomCss'] ) ) {
			$item['ownsCustomCss'] = true;
		}

		// The block printed its Custom CSS as is, so the override keeps printing it that way.
		if ( ( $owns_custom_css && $is_block_css ) || ! empty( $items[ $key ]['blockCustomCss'] ) ) {
			$item['blockCustomCss'] = true;
		}

		$items[ $key ] = $item;

		return $this->write( $host, $items );
	}

	/**
	 * Whether the override of an embed replaced the embed's own Custom CSS, a block attribute from before the editor.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key     Embed key.
	 * @param int    $form_id Form ID.
	 *
	 * @return bool
	 */
	public function owns_custom_css( string $key, int $form_id ): bool {

		$item = $key !== '' ? $this->get( $key ) : [];

		return ! empty( $item['ownsCustomCss'] ) && $form_id === (int) ( $item['formId'] ?? 0 );
	}

	/**
	 * Drop every override of a host that is not live any more.
	 *
	 * @since 2.0.5
	 *
	 * @param string $host Host.
	 * @param array  $live Live keys => form ID; null keeps the key whatever form it holds.
	 *
	 * @return void
	 */
	private function prune( string $host, array $live ): void {

		$kept = array_filter(
			$this->get_for_host( $host ),
			static function ( $item, $key ) use ( $live ) {

				if ( ! array_key_exists( $key, $live ) ) {
					return false;
				}

				// An embed switched to another form keeps its key; its old override must not wait for the switch back.
				return $live[ $key ] === null || (int) ( $item['formId'] ?? 0 ) === (int) $live[ $key ];
			},
			ARRAY_FILTER_USE_BOTH
		);

		$this->write( $host, $kept );
	}

	/**
	 * Delete every override of a form, in the option and on every post that stores one.
	 *
	 * @since 2.0.5
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return void
	 */
	private function delete_for_form( int $form_id ): void {

		global $wpdb;

		// An item serializes as `s:6:"formId";i:N;`, so one LIKE finds every host without a form-side index.
		$hosts = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value LIKE %s",
				self::META_KEY,
				'%' . $wpdb->esc_like( 's:6:"formId";i:' . $form_id . ';' ) . '%'
			)
		);

		foreach ( array_merge( [ '' ], array_map( 'strval', (array) $hosts ) ) as $host ) {
			$kept = array_filter(
				$this->get_for_host( $host ),
				static function ( $item ) use ( $form_id ) {

					return $form_id !== (int) ( $item['formId'] ?? 0 );
				}
			);

			$this->prune( $host, array_fill_keys( array_keys( $kept ), null ) );
		}
	}

	/**
	 * Parse a key and read the items of its host.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key Embed key.
	 *
	 * @return array|null `[ host, items ]`; null for a malformed key.
	 */
	private function get_host_items( string $key ): ?array {

		$embed = $this->embed_context->parse( $key );

		return $embed ? [ $embed['host'], $this->get_for_host( $embed['host'] ) ] : null;
	}

	/**
	 * Write a host's items, deleting the record when none is left.
	 *
	 * @since 2.0.5
	 *
	 * @param string $host  Host; '' is the whole option store.
	 * @param array  $items Items.
	 *
	 * @return bool
	 */
	private function write( string $host, array $items ): bool {

		// WordPress reports an unchanged value as false, not just a failed write.
		if ( $items === $this->get_for_host( $host ) ) {
			return true;
		}

		if ( ! EmbedContext::is_post_host( $host ) ) {
			return $this->write_option( $host, $items );
		}

		$post_id = (int) $host;

		// The meta API unslashes the value, which would strip a backslash from Custom CSS.
		$result = $items ? update_post_meta( $post_id, self::META_KEY, wp_slash( $items ) ) : delete_post_meta( $post_id, self::META_KEY );

		// Most page cache plugins purge the page on this.
		clean_post_cache( $post_id );

		return (bool) $result;
	}

	/**
	 * Write a non-post host's items into the shared option, keeping the other hosts' items.
	 *
	 * @since 2.0.5
	 *
	 * @param string $host  Host; '' replaces the whole option store.
	 * @param array  $items Items.
	 *
	 * @return bool
	 */
	private function write_option( string $host, array $items ): bool {

		if ( $host !== '' ) {
			$items = array_diff_key( $this->get_for_host( '' ), $this->get_for_host( $host ) ) + $items;
		}

		$result = $items ? update_option( self::OPTION_KEY, $items, 'no' ) : delete_option( self::OPTION_KEY );

		return (bool) $result;
	}
}
