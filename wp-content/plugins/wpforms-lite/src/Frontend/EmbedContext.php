<?php

namespace WPForms\Frontend;

use WPForms\Forms\Preview;
use WPForms\Frontend\EmbedContext\Hosts;

/**
 * Embed context of a rendered form: which block, widget or module put it on the page.
 *
 * An integration sets the context before it renders the form; `Frontend::output()`
 * takes it on entry. The key is built and parsed here only.
 *
 * @since 2.0.5
 */
class EmbedContext {

	/**
	 * Embed types.
	 *
	 * @since 2.0.5
	 */
	const TYPE_BLOCK     = 'block';
	const TYPE_ELEMENTOR = 'elementor';
	const TYPE_SHORTCODE = 'shortcode';
	const TYPE_DIVI      = 'divi';
	const TYPE_WIDGET    = 'widget';

	/**
	 * Container class prefix.
	 *
	 * @since 2.0.5
	 */
	const CLASS_PREFIX = 'wpforms-embed-';

	/**
	 * Host resolver.
	 *
	 * @since 2.0.5
	 *
	 * @var Hosts
	 */
	private $hosts;

	/**
	 * Context set for the next render.
	 *
	 * @since 2.0.5
	 *
	 * @var array
	 */
	private $pending = [];

	/**
	 * Context of the render in progress.
	 *
	 * @since 2.0.5
	 *
	 * @var array
	 */
	private $current = [];

	/**
	 * Constructor.
	 *
	 * @since 2.0.5
	 *
	 * @param Hosts|null $hosts Host resolver.
	 */
	public function __construct( ?Hosts $hosts = null ) {

		$this->hosts = $hosts ?? new Hosts();
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

		$this->hosts->hooks();

		add_filter( 'wpforms_frontend_container_class', [ $this, 'add_container_class' ], 5, 2 );
		add_action( 'wpforms_frontend_output_after', [ $this, 'clear' ] );
	}

	/**
	 * Set the context for the next rendered form.
	 *
	 * @since 2.0.5
	 *
	 * @param array $context Context: `type`, `id` (clientId, element id), `form_id` (the form about to render), `legacy` (styles the embed renders with).
	 *
	 * @return void
	 */
	public function set( array $context ): void {

		$this->pending = $context;
	}

	/**
	 * Take the pending context for the form about to render.
	 *
	 * Pending is always cleared, so an early return in the render cannot leak
	 * a context into the next form.
	 *
	 * @since 2.0.5
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array Empty on the form's own page.
	 */
	public function take( int $form_id ): array {

		$entry         = $this->pending;
		$this->pending = [];
		$this->current = $this->build_for( $entry, $form_id );

		return $this->current;
	}

	/**
	 * Get the context the next render of a form will take, without taking it.
	 *
	 * For a printer that runs before the form render, such as the block styles.
	 *
	 * @since 2.0.5
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array Empty on the form's own page.
	 */
	public function peek( int $form_id ): array {

		return $this->build_for( $this->pending, $form_id );
	}

	/**
	 * Build a form's context from a pending entry: host, filter, key and class.
	 *
	 * @since 2.0.5
	 *
	 * @param array $pending Pending entry.
	 * @param int   $form_id Form ID.
	 *
	 * @return array
	 */
	private function build_for( array $pending, int $form_id ): array {

		if ( $this->is_form_page( $form_id ) ) {
			return [];
		}

		// A context set for another form, whose render never reached output(), is not this embed.
		if ( $form_id !== (int) ( $pending['form_id'] ?? 0 ) ) {
			$pending = [];
		}

		$type    = (string) ( $pending['type'] ?? self::TYPE_SHORTCODE );
		$context = [
			'type'   => $type,
			'id'     => (string) ( $pending['id'] ?? '' ),
			'host'   => $this->hosts->resolve( $type ),
			'legacy' => (array) ( $pending['legacy'] ?? [] ),
		];

		/**
		 * Filter the embed context of the form about to render.
		 *
		 * An integration with its own render path sets its type here or changes the host;
		 * the key and the class are built from the result.
		 *
		 * @since 2.0.5
		 *
		 * @param array $context Context: type, id, host, legacy.
		 * @param int   $form_id Form ID.
		 */
		$context = (array) apply_filters( 'wpforms_frontend_embed_context_take', $context, $form_id );

		return $this->build_context( $context, $form_id );
	}

	/**
	 * Get the context of the render in progress.
	 *
	 * @since 2.0.5
	 *
	 * @return array
	 */
	public function current(): array {

		return $this->current;
	}

	/**
	 * Clear the current context once the form rendered.
	 *
	 * @since 2.0.5
	 *
	 * @return void
	 */
	public function clear(): void {

		$this->current = [];
	}

	/**
	 * Add the embed class to the container of the form being rendered.
	 *
	 * @since 2.0.5
	 *
	 * @param array|mixed $classes   Container classes.
	 * @param array|mixed $form_data Form data.
	 *
	 * @return array
	 */
	public function add_container_class( $classes, $form_data ): array {

		$classes = (array) $classes;

		if ( empty( $this->current['class'] ) || (int) ( $form_data['id'] ?? 0 ) !== (int) $this->current['form_id'] ) {
			return $classes;
		}

		$classes[] = $this->current['class'];

		return $classes;
	}

	/**
	 * Whether the page is the form's own page, where the panel edits the form default.
	 *
	 * The preview page is that only for the previewed form: a form the theme prints
	 * around it (header, footer, widgets) keeps its embed.
	 *
	 * @since 2.0.5
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return bool
	 */
	private function is_form_page( int $form_id ): bool {

		$preview = wpforms()->obj( 'preview' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_form_page = $preview instanceof Preview && $preview->is_preview_page() && absint( $_GET['wpforms_form_preview'] ?? 0 ) === $form_id;

		/**
		 * Filter whether the current page is the form's own page.
		 *
		 * On the form's own page no embed context exists and the panel writes the
		 * form default. An addon that renders a form on a page of its own returns true there.
		 *
		 * @since 2.0.5
		 *
		 * @param bool $is_form_page Whether this is the form's own page.
		 * @param int  $form_id      Form ID.
		 */
		return (bool) apply_filters( 'wpforms_frontend_embed_context_is_form_page', $is_form_page, $form_id );
	}

	/**
	 * Parse an embed key.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key Embed key.
	 *
	 * @return array|null `type`, `host`, `id`; null for a malformed key.
	 */
	public function parse( string $key ): ?array {

		$parts = explode( ':', $key );
		$type  = $parts[0] ?? '';

		if ( $type === self::TYPE_WIDGET ) {
			return count( $parts ) === 2 && $this->is_host( $parts[1] ) ? [
				'type' => $type,
				'host' => $parts[1],
				'id'   => '',
			] : null;
		}

		if ( count( $parts ) !== 3 || ! $this->is_slug( $type ) || ! $this->is_host( $parts[1] ) || ! $this->is_slug( $parts[2] ) ) {
			return null;
		}

		return [
			'type' => $type,
			'host' => $parts[1],
			'id'   => $parts[2],
		];
	}

	/**
	 * Whether the current user may edit the host of an embed.
	 *
	 * @since 2.0.5
	 *
	 * @param array $embed Embed with `type` and `host` (parsed key or current context).
	 *
	 * @return bool
	 */
	public function can_edit( array $embed ): bool {

		$host     = (string) ( $embed['host'] ?? '0' );
		$can_edit = false;

		if ( $host !== '0' && $host !== '' ) {
			$can_edit = ( $embed['type'] ?? '' ) === self::TYPE_WIDGET || ! self::is_post_host( $host )
				? current_user_can( 'edit_theme_options' )
				: current_user_can( 'edit_post', (int) $host );
		}

		/**
		 * Filter whether the current user may edit the host of an embed.
		 *
		 * An addon whose host is neither a post nor theme-owned decides here.
		 *
		 * @since 2.0.5
		 *
		 * @param bool  $can_edit Whether the host may be edited.
		 * @param array $embed    Embed with `type` and `host`.
		 */
		return (bool) apply_filters( 'wpforms_frontend_embed_context_can_edit', $can_edit, $embed );
	}

	/**
	 * Whether a host is a post of any type, as opposed to a widget id or a file-based template part.
	 *
	 * The one rule the storage, the finder and the capability check share; `0` is no post.
	 *
	 * @since 2.0.5
	 *
	 * @param string $host Host.
	 *
	 * @return bool
	 */
	public static function is_post_host( string $host ): bool {

		return $host !== '' && $host !== '0' && ctype_digit( $host );
	}

	/**
	 * Get the container class of a key.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key Embed key.
	 *
	 * @return string
	 */
	private static function get_class( string $key ): string {

		return self::CLASS_PREFIX . substr( md5( $key ), 0, 8 );
	}

	/**
	 * Build the current context from the filtered one: validated type, id and host, then key and class.
	 *
	 * @since 2.0.5
	 *
	 * @param array $context Filtered context: type, id, host, legacy.
	 * @param int   $form_id Form ID.
	 *
	 * @return array
	 */
	private function build_context( array $context, int $form_id ): array {

		$type = sanitize_key( (string) ( $context['type'] ?? '' ) );
		$id   = sanitize_key( (string) ( $context['id'] ?? '' ) );
		$host = (string) ( $context['host'] ?? '0' );
		$type = $type !== '' ? $type : self::TYPE_SHORTCODE;

		// A host with a `:` would make the key unparseable.
		$host = $this->is_host( $host ) ? $host : '0';

		if ( $this->hosts->is_widget() ) {
			$type = self::TYPE_WIDGET;
			$id   = '';
		}

		$key = $this->build_key( $type, $host, $id, $form_id );

		return [
			'type'    => $type,
			'id'      => $id,
			'host'    => $host,
			'form_id' => $form_id,
			'key'     => $key,
			'class'   => self::get_class( $key ),
			'legacy'  => (array) ( $context['legacy'] ?? [] ),
		];
	}

	/**
	 * Build the key of a render.
	 *
	 * @since 2.0.5
	 *
	 * @param string $type    Type.
	 * @param string $host    Host.
	 * @param string $id      Block or element id.
	 * @param int    $form_id Form ID.
	 *
	 * @return string
	 */
	private function build_key( string $type, string $host, string $id, int $form_id ): string {

		if ( $type === self::TYPE_WIDGET ) {
			return "widget:{$host}";
		}

		if ( $id !== '' ) {
			return "{$type}:{$host}:{$id}";
		}

		return "shortcode:{$host}:{$form_id}";
	}

	/**
	 * Whether a string is a valid host: digits, a widget id, or `{theme}//{slug}`.
	 *
	 * @since 2.0.5
	 *
	 * @param string $host Host.
	 *
	 * @return bool
	 */
	private function is_host( string $host ): bool {

		// Case-insensitive: a third-party widget `id_base` or a theme directory may carry capitals.
		return $host !== '' && (bool) preg_match( '#^[a-z0-9_\-]+(//[a-z0-9_\-]+)?\z#i', $host );
	}

	/**
	 * Whether a key part is a non-empty `sanitize_key()` value, the shape `take()` gives types and ids.
	 *
	 * @since 2.0.5
	 *
	 * @param string $value Key part.
	 *
	 * @return bool
	 */
	private function is_slug( string $value ): bool {

		return $value !== '' && sanitize_key( $value ) === $value;
	}
}
