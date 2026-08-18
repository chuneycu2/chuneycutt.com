<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts ACF field values into a portable, cross-site-safe representation
 * for export. IDs (attachment/post/term/user) are never exported directly
 * since they are meaningless on another site — each is replaced with a
 * stable identifier (URL, title, name, email) that FCSA_ACF_Importer can
 * look up or recreate on import.
 *
 * Repeater and flexible content fields are walked using ACF's own
 * have_rows()/the_row()/get_row_layout()/get_sub_field() traversal API
 * rather than hand-parsed from the raw postmeta shape — ACF assembles a
 * repeater/flexible-content row's nested values as part of that traversal,
 * not as part of a plain get_field(..., false) call, so parsing the "raw"
 * array directly leaves every nested sub-field value empty.
 *
 * A "seamless" clone field never reaches this class at all — ACF's own
 * acf_get_fields() transparently replaces it with its cloned fields
 * wherever sub_fields are loaded, at any nesting depth. A "group" display
 * clone field, however, still shows up as a real 'clone'-type field and is
 * resolved identically to a plain 'group' field (see resolve_container()).
 *
 * Two different field groups applying to the same post are allowed to
 * define a field with the same name — the export is keyed by field *group*
 * key first, then field name within it (see export_post_fields()), so
 * nothing collides. FCSA_ACF_Importer matches the same way on import,
 * assuming the destination site's field groups share the same keys (e.g.
 * via ACF Local JSON — see readme.txt).
 */
class FCSA_ACF_Resolver {

	/**
	 * Export every ACF field attached to a post as
	 * [ field_group_key => [ field_name => portable_value ] ].
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function export_post_fields( int $post_id ): array {
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return [];
		}

		// Field definitions come from acf_get_field_groups()/acf_get_fields()
		// directly rather than get_field_objects() — the latter was found to
		// return no fields at all for a post with empty/no ACF meta yet
		// (see FCSA_ACF_Importer::import_post_fields() for the same fix on
		// the import side). Values are re-fetched per field type below
		// (get_field()/get_sub_field() for plain fields, have_rows()/the_row()
		// for repeater and flexible content) rather than trusting any bundled
		// 'value'.
		$out = [];
		foreach ( acf_get_field_groups( [ 'post_id' => $post_id ] ) as $field_group ) {
			$fields = [];
			foreach ( (array) acf_get_fields( $field_group ) as $field ) {
				$fields[ $field['name'] ] = self::resolve_field( $field, $post_id );
			}
			$out[ $field_group['key'] ] = $fields;
		}

		return $out;
	}

	/**
	 * Resolve one field's value. $context is either a post ID (top-level
	 * field) or `false` (nested field — read from ACF's current row, which
	 * have_rows()/the_row()/get_sub_field() track implicitly).
	 *
	 * @param array<string, mixed> $field
	 * @return mixed
	 */
	private static function resolve_field( array $field, $context ) {
		$type = $field['type'] ?? '';
		$name = $field['name'];

		if ( 'repeater' === $type ) {
			$rows = [];
			while ( have_rows( $name, $context ) ) {
				the_row();
				$rows[] = self::resolve_row( $field['sub_fields'] ?? [] );
			}
			return $rows;
		}

		if ( 'flexible_content' === $type ) {
			$blocks = [];
			while ( have_rows( $name, $context ) ) {
				the_row();
				$layout_name = (string) get_row_layout();
				$layout      = self::find_layout( $field['layouts'] ?? [], $layout_name );
				$blocks[]    = [
					'layout' => $layout_name,
					'fields' => self::resolve_row( $layout['sub_fields'] ?? [] ),
				];
			}
			return $blocks;
		}

		if ( 'group' === $type || 'clone' === $type ) {
			// ACF's own recursive load_value() already resolves *leaf* sub
			// fields correctly in this one raw fetch (re-keyed by field key —
			// see resolve_container()). But a repeater/flexible_content sub
			// field needs have_rows() traversal instead, which requires the
			// exact prefixed name ACF renames it to before storage.
			$raw = ( false === $context )
				? get_sub_field( $name, false )
				: get_field( $name, $context, false );

			// Group always prefixes each direct sub field's storage name with
			// "{group_name}_" on demand (Group::prepare_field_for_db()), so
			// resolve_container() must compute that prefix itself. Clone's
			// equivalent renaming already happened once, permanently, when
			// its sub_fields were loaded (acf_clone_field()) — sub_field
			// ['name'] is already exactly the selector to use, prefixed or
			// not per its own "Prefix Field Names" setting — so no prefix
			// should be added again here.
			$prefix = ( 'group' === $type ) ? $name : null;

			return self::resolve_container( $field['sub_fields'] ?? [], $context, $prefix, $raw );
		}

		// Plain fields: raw value is either read from the given post
		// (top-level) or the current row (nested — inside a repeater/
		// flexible-content row).
		$raw = ( false === $context )
			? get_sub_field( $name, false )
			: get_field( $name, $context, false );

		return self::resolve_leaf( $field, $raw );
	}

	/**
	 * Resolve every sub field of a group/clone field whose own raw value
	 * ($raw) has already been fetched in one call. $raw correctly contains
	 * every *leaf* sub field's value (including sub fields of a nested
	 * group/clone), keyed by field *key* — ACF recurses through nested
	 * containers itself as part of that one fetch. A repeater or
	 * flexible_content sub field, however, is never fully assembled by a raw
	 * fetch (see the class docblock), so it's walked separately here via
	 * have_rows().
	 *
	 * @param array<int, array<string, mixed>> $sub_fields
	 * @param string|null                       $prefix Non-null for a group
	 *   parent: sub fields are addressed as "{$prefix}_{sub_field_name}"
	 *   (mirrors Group::prepare_field_for_db()). Null for a clone parent:
	 *   each sub_field['name'] is already the correct selector, because ACF
	 *   renamed it once, permanently, when the clone's sub_fields were
	 *   loaded — renaming it again here would double the prefix.
	 * @return array<string, mixed>
	 */
	private static function resolve_container( array $sub_fields, $context, ?string $prefix, $raw ): array {
		$out = [];

		foreach ( $sub_fields as $sub_field ) {
			$type = $sub_field['type'] ?? '';
			$name = ( null === $prefix ) ? $sub_field['name'] : $prefix . '_' . $sub_field['name'];

			if ( 'repeater' === $type ) {
				$rows = [];
				while ( have_rows( $name, $context ) ) {
					the_row();
					$rows[] = self::resolve_row( $sub_field['sub_fields'] ?? [] );
				}
				$out[ $sub_field['name'] ] = $rows;
				continue;
			}

			if ( 'flexible_content' === $type ) {
				$blocks = [];
				while ( have_rows( $name, $context ) ) {
					the_row();
					$layout_name = (string) get_row_layout();
					$layout      = self::find_layout( $sub_field['layouts'] ?? [], $layout_name );
					$blocks[]    = [
						'layout' => $layout_name,
						'fields' => self::resolve_row( $layout['sub_fields'] ?? [] ),
					];
				}
				$out[ $sub_field['name'] ] = $blocks;
				continue;
			}

			$sub_raw = ( (array) $raw )[ $sub_field['key'] ] ?? null;

			if ( 'group' === $type || 'clone' === $type ) {
				$child_prefix = ( 'group' === $type ) ? $name : null;
				$out[ $sub_field['name'] ] = self::resolve_container( $sub_field['sub_fields'] ?? [], $context, $child_prefix, $sub_raw );
				continue;
			}

			$out[ $sub_field['name'] ] = self::resolve_leaf( $sub_field, $sub_raw );
		}

		return $out;
	}

	/**
	 * Resolve every sub-field of the row ACF currently has active (set by a
	 * preceding the_row() call).
	 *
	 * @param array<int, array<string, mixed>> $sub_fields
	 * @return array<string, mixed>
	 */
	private static function resolve_row( array $sub_fields ): array {
		$out = [];
		foreach ( $sub_fields as $sub_field ) {
			$out[ $sub_field['name'] ] = self::resolve_field( $sub_field, false );
		}
		return $out;
	}

	/**
	 * Resolve a "leaf" field's already-fetched raw value (image, relationship,
	 * taxonomy, or a plain scalar field). Repeater/flexible_content/group are
	 * handled earlier in resolve_field() and never reach here.
	 *
	 * @param array<string, mixed> $field
	 */
	private static function resolve_leaf( array $field, $raw ) {
		switch ( $field['type'] ?? '' ) {
			case 'image':
			case 'file':
				return self::attachment_to_portable( $raw );

			case 'gallery':
				return array_values( array_filter( array_map( [ self::class, 'attachment_to_portable' ], (array) $raw ) ) );

			case 'relationship':
				return array_values( array_filter( array_map( [ self::class, 'post_to_portable' ], (array) $raw ) ) );

			case 'post_object':
			case 'page_link':
				if ( is_array( $raw ) && ! isset( $raw['ID'] ) ) {
					return array_values( array_filter( array_map( [ self::class, 'post_to_portable' ], $raw ) ) );
				}
				return self::post_to_portable( $raw );

			case 'link':
				return self::link_to_portable( $raw );

			case 'taxonomy':
				return array_values( array_filter( array_map( [ self::class, 'term_to_portable' ], (array) $raw ) ) );

			case 'user':
				if ( is_array( $raw ) && ! isset( $raw['ID'] ) ) {
					return array_values( array_filter( array_map( [ self::class, 'user_to_portable' ], $raw ) ) );
				}
				return self::user_to_portable( $raw );

			default:
				return $raw;
		}
	}

	/**
	 * @param array<int, array<string, mixed>> $layouts
	 * @return array<string, mixed>
	 */
	private static function find_layout( array $layouts, string $name ): array {
		foreach ( $layouts as $layout ) {
			if ( ( $layout['name'] ?? '' ) === $name ) {
				return $layout;
			}
		}
		return [];
	}

	/**
	 * @return array{url: string, alt: string, title: string, caption: string, description: string}|null
	 */
	public static function attachment_to_portable( $value ): ?array {
		$id = is_array( $value ) ? (int) ( $value['ID'] ?? 0 ) : (int) $value;
		if ( $id <= 0 ) {
			return null;
		}

		$post = get_post( $id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		$url = wp_get_attachment_url( $id );
		if ( ! $url ) {
			return null;
		}

		return [
			'url'         => $url,
			'alt'         => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'title'       => $post->post_title,
			'caption'     => $post->post_excerpt,
			'description' => $post->post_content,
		];
	}

	/**
	 * A Link field always stores an absolute URL, even when the editor picked
	 * an existing post via its "search content" UI — there is no post-ID
	 * reference to translate, only a URL string. If that URL points back at
	 * this (the *source*) site, we also resolve which post it is so the
	 * importer can rebuild a same-site-relative link on the destination site;
	 * otherwise (a genuinely external URL) it's left as plain data.
	 *
	 * @return array{title: string, url: string, target: string, internal_post: array{title: string, post_type: string}|null}|null
	 */
	private static function link_to_portable( $raw ): ?array {
		if ( empty( $raw ) || ! is_array( $raw ) || empty( $raw['url'] ) ) {
			return null;
		}

		$internal_post = null;
		if ( 0 === strpos( $raw['url'], home_url() ) ) {
			$post_id = url_to_postid( $raw['url'] );
			if ( $post_id > 0 ) {
				$internal_post = self::post_to_portable( $post_id );
			}
		}

		return [
			'title'         => (string) ( $raw['title'] ?? '' ),
			'url'           => (string) $raw['url'],
			'target'        => (string) ( $raw['target'] ?? '' ),
			'internal_post' => $internal_post,
		];
	}

	/**
	 * @return array{title: string, post_type: string}|null
	 */
	private static function post_to_portable( $value ): ?array {
		$id = is_array( $value ) ? (int) ( $value['ID'] ?? 0 ) : (int) $value;
		if ( $id <= 0 ) {
			return null;
		}

		$post = get_post( $id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		return [
			'title'     => $post->post_title,
			'post_type' => $post->post_type,
		];
	}

	/**
	 * @return array{name: string, taxonomy: string}|null
	 */
	private static function term_to_portable( $value ): ?array {
		$term = ( $value instanceof WP_Term ) ? $value : get_term( is_array( $value ) ? (int) ( $value['term_id'] ?? 0 ) : (int) $value );

		if ( ! $term instanceof WP_Term ) {
			return null;
		}

		return [
			'name'     => $term->name,
			'taxonomy' => $term->taxonomy,
		];
	}

	/**
	 * @return array{user_email: string}|null
	 */
	private static function user_to_portable( $value ): ?array {
		$id = is_array( $value ) ? (int) ( $value['ID'] ?? 0 ) : (int) $value;
		if ( $id <= 0 ) {
			return null;
		}

		$user = get_user_by( 'id', $id );
		if ( ! $user ) {
			return null;
		}

		return [ 'user_email' => $user->user_email ];
	}
}
