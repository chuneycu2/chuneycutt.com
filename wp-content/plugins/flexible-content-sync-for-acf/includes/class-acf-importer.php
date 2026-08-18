<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reverses FCSA_ACF_Resolver: takes the portable JSON representation of
 * an ACF field and writes it onto a (usually newly created) post on this site,
 * re-resolving each portable identifier against local data:
 *  - image/file/gallery: downloads the remote URL into the Media Library
 *  - relationship/post_object/page_link: finds a local post with a matching
 *    title + post type (skipped, with a warning, if none is found)
 *  - taxonomy field: finds or creates a matching term
 *  - user field: finds a local user by email (skipped if none is found)
 *
 * Collects human-readable warnings for anything it could not resolve so the
 * admin UI can show them after import.
 */
class FCSA_ACF_Importer {

	/** @var string[] */
	private array $warnings = [];

	/** @var array<string, int> URL => attachment ID, deduped within one import run. */
	private array $media_cache = [];

	/**
	 * @param array<string, array<string, mixed>> $acf_data field_group_key => [ field_name => portable value ]
	 */
	public function import_post_fields( int $post_id, array $acf_data ): void {
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return;
		}

		// Matches export_post_fields()'s [ group_key => [ field_name => value ] ]
		// shape, field group by field group, so two field groups that happen
		// to both define a field with the same name never collide. Field
		// definitions come from acf_get_field_groups()/acf_get_fields()
		// directly rather than get_field_objects() — the latter was found to
		// return no fields at all for a post with empty/no ACF meta yet,
		// even though these two lower-level functions correctly find the
		// applicable groups/fields on a brand-new post.
		foreach ( acf_get_field_groups( [ 'post_id' => $post_id ] ) as $field_group ) {
			$group_data = $acf_data[ $field_group['key'] ] ?? null;
			if ( ! is_array( $group_data ) ) {
				// This field group didn't exist (by key) on the source site,
				// or had no matching data — nothing to import for it.
				continue;
			}

			$fields = [];
			foreach ( (array) acf_get_fields( $field_group ) as $field ) {
				$fields[ $field['name'] ] = $field;
			}

			foreach ( $group_data as $name => $portable ) {
				if ( ! isset( $fields[ $name ] ) ) {
					// Field no longer exists in this field group — skip.
					continue;
				}

				$field = $fields[ $name ];
				$value = $this->resolve_portable( $field, $portable );
				update_field( $field['key'], $value, $post_id );
			}
		}
	}

	/**
	 * @return string[]
	 */
	public function get_warnings(): array {
		return $this->warnings;
	}

	/**
	 * Public entry point for downloading a single portable media reference
	 * (used for the featured image, which isn't an ACF field). Shares the
	 * same dedupe cache and warnings list as ACF image/file/gallery fields.
	 *
	 * @param array{url?: string, alt?: string, title?: string, caption?: string, description?: string} $media
	 */
	public function import_single_attachment( array $media ): int {
		return $this->portable_to_attachment( $media );
	}

	/**
	 * @param array<string, mixed> $field
	 */
	private function resolve_portable( array $field, $portable ) {
		switch ( $field['type'] ?? '' ) {
			case 'image':
			case 'file':
				return $this->portable_to_attachment( is_array( $portable ) ? $portable : null );

			case 'gallery':
				$ids = [];
				foreach ( (array) $portable as $item ) {
					$id = $this->portable_to_attachment( is_array( $item ) ? $item : null );
					if ( $id > 0 ) {
						$ids[] = $id;
					}
				}
				return $ids;

			case 'relationship':
				$ids = [];
				foreach ( (array) $portable as $item ) {
					$id = $this->portable_to_post_id( is_array( $item ) ? $item : null );
					if ( $id > 0 ) {
						$ids[] = $id;
					}
				}
				return $ids;

			case 'post_object':
			case 'page_link':
				if ( is_array( $portable ) && ! isset( $portable['title'] ) ) {
					$ids = [];
					foreach ( $portable as $item ) {
						$id = $this->portable_to_post_id( is_array( $item ) ? $item : null );
						if ( $id > 0 ) {
							$ids[] = $id;
						}
					}
					return $ids;
				}
				$id = $this->portable_to_post_id( is_array( $portable ) ? $portable : null );
				return $id > 0 ? $id : '';

			case 'link':
				return $this->portable_to_link( is_array( $portable ) ? $portable : null );

			case 'taxonomy':
				$ids = [];
				foreach ( (array) $portable as $item ) {
					$id = $this->portable_to_term_id( is_array( $item ) ? $item : null );
					if ( $id > 0 ) {
						$ids[] = $id;
					}
				}
				return $ids;

			case 'user':
				if ( is_array( $portable ) && ! isset( $portable['user_email'] ) ) {
					$ids = [];
					foreach ( $portable as $item ) {
						$id = $this->portable_to_user_id( is_array( $item ) ? $item : null );
						if ( $id > 0 ) {
							$ids[] = $id;
						}
					}
					return $ids;
				}
				$id = $this->portable_to_user_id( is_array( $portable ) ? $portable : null );
				return $id > 0 ? $id : '';

			case 'repeater':
				if ( empty( $portable ) || ! is_array( $portable ) ) {
					return [];
				}
				$rows = [];
				foreach ( $portable as $row ) {
					$rows[] = $this->resolve_row( $field['sub_fields'] ?? [], is_array( $row ) ? $row : [] );
				}
				return $rows;

			case 'flexible_content':
				if ( empty( $portable ) || ! is_array( $portable ) ) {
					return [];
				}
				$rows = [];
				foreach ( $portable as $block ) {
					$block       = is_array( $block ) ? $block : [];
					$layout_name = (string) ( $block['layout'] ?? '' );
					$layout      = $this->find_layout( $field['layouts'] ?? [], $layout_name );
					$row         = $this->resolve_row( $layout['sub_fields'] ?? [], (array) ( $block['fields'] ?? [] ) );
					$row['acf_fc_layout'] = $layout_name;
					$rows[] = $row;
				}
				return $rows;

			case 'group':
			case 'clone':
				return $this->resolve_container( $field['sub_fields'] ?? [], is_array( $portable ) ? $portable : [] );

			default:
				return $portable;
		}
	}

	/**
	 * Repeater/flexible_content row: ACF's own update_row() matches each sub
	 * field's incoming value by field *key*, else by plain *name* — so this
	 * value tree is (and must stay) keyed by name.
	 *
	 * @param array<int, array<string, mixed>> $sub_fields
	 * @param array<string, mixed>             $row
	 * @return array<string, mixed>
	 */
	private function resolve_row( array $sub_fields, array $row ): array {
		$out = [];
		foreach ( $sub_fields as $sub_field ) {
			$out[ $sub_field['name'] ] = $this->resolve_portable( $sub_field, $row[ $sub_field['name'] ] ?? null );
		}
		return $out;
	}

	/**
	 * Group/clone container: unlike a repeater/flexible_content row, ACF's
	 * Group/Clone update_value() matches each incoming sub field value by
	 * field *key*, else by *_name* — never by *name*. For a plain Group,
	 * name and _name are always identical (Group only renames "name" on
	 * demand inside its own load_value()/update_value(), never in the
	 * cached field definition), so this makes no difference there. But for
	 * a "group"-display Clone field with "Prefix Field Names" on,
	 * acf_clone_field() renames *name* to "{clone_name}_{sub_name}" while
	 * leaving *_name* as the sub field's original, unprefixed name — using
	 * "name" as the output key here would silently never match, dropping
	 * the value on import. The portable data itself is still read by
	 * "name" (how the resolver exports it).
	 *
	 * @param array<int, array<string, mixed>> $sub_fields
	 * @param array<string, mixed>             $portable
	 * @return array<string, mixed>
	 */
	private function resolve_container( array $sub_fields, array $portable ): array {
		$out = [];
		foreach ( $sub_fields as $sub_field ) {
			$out[ $sub_field['_name'] ] = $this->resolve_portable( $sub_field, $portable[ $sub_field['name'] ] ?? null );
		}
		return $out;
	}

	/**
	 * @param array<int, array<string, mixed>> $layouts
	 * @return array<string, mixed>
	 */
	private function find_layout( array $layouts, string $name ): array {
		foreach ( $layouts as $layout ) {
			if ( ( $layout['name'] ?? '' ) === $name ) {
				return $layout;
			}
		}
		return [];
	}

	/**
	 * Downloads a remote media URL into the Media Library and returns the new
	 * attachment ID (0 on failure). Deduped per URL within one import run.
	 *
	 * @param array{url?: string, alt?: string, title?: string, caption?: string, description?: string}|null $media
	 */
	private function portable_to_attachment( ?array $media ): int {
		if ( empty( $media['url'] ) ) {
			return 0;
		}

		if ( isset( $this->media_cache[ $media['url'] ] ) ) {
			return $this->media_cache[ $media['url'] ];
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp_file = download_url( $media['url'] );
		if ( is_wp_error( $tmp_file ) ) {
			/* translators: %s: media URL */
			$this->warnings[] = sprintf( __( 'Could not download media from %s — field left empty.', 'flexible-content-sync-for-acf' ), $media['url'] );
			return 0;
		}

		$file_array = [
			'name'     => sanitize_file_name( wp_basename( (string) wp_parse_url( $media['url'], PHP_URL_PATH ) ) ),
			'tmp_name' => $tmp_file,
		];

		$attachment_id = media_handle_sideload( $file_array, 0, $media['title'] ?? '' );

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp_file );
			/* translators: %s: media URL */
			$this->warnings[] = sprintf( __( 'Could not import media from %s — field left empty.', 'flexible-content-sync-for-acf' ), $media['url'] );
			return 0;
		}

		if ( ! empty( $media['alt'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $media['alt'] ) );
		}

		$updated_fields = [];
		if ( ! empty( $media['caption'] ) ) {
			$updated_fields['post_excerpt'] = $media['caption'];
		}
		if ( ! empty( $media['description'] ) ) {
			$updated_fields['post_content'] = $media['description'];
		}
		if ( ! empty( $updated_fields ) ) {
			wp_update_post( array_merge( [ 'ID' => $attachment_id ], $updated_fields ) );
		}

		$this->media_cache[ $media['url'] ] = (int) $attachment_id;
		return (int) $attachment_id;
	}

	/**
	 * @param array{title?: string, url?: string, target?: string, internal_post?: array{title?: string, post_type?: string}|null}|null $portable
	 * @return array{title: string, url: string, target: string}
	 */
	private function portable_to_link( ?array $portable ): array {
		if ( empty( $portable ) ) {
			return [
				'title'  => '',
				'url'    => '',
				'target' => '',
			];
		}

		$url = (string) ( $portable['url'] ?? '' );

		// If the source URL pointed at a post on the *source* site, rebuild a
		// same-site-relative link on this site instead — otherwise (no match
		// found, or a genuinely external URL) the original URL is kept as-is.
		if ( ! empty( $portable['internal_post'] ) && is_array( $portable['internal_post'] ) ) {
			$post_id = $this->portable_to_post_id( $portable['internal_post'] );
			if ( $post_id > 0 ) {
				$permalink = get_permalink( $post_id );
				if ( false !== $permalink ) {
					$url = $permalink;
				}
			}
		}

		return [
			'title'  => (string) ( $portable['title'] ?? '' ),
			'url'    => $url,
			'target' => (string) ( $portable['target'] ?? '' ),
		];
	}

	/**
	 * @param array{title?: string, post_type?: string}|null $ref
	 */
	private function portable_to_post_id( ?array $ref ): int {
		if ( empty( $ref['title'] ) ) {
			return 0;
		}

		$found = get_posts(
			[
				'post_type'      => ! empty( $ref['post_type'] ) ? $ref['post_type'] : 'any',
				'title'          => $ref['title'],
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]
		);

		if ( empty( $found ) ) {
			/* translators: %s: referenced post title */
			$this->warnings[] = sprintf( __( 'Could not find a matching post titled "%s" on this site — reference left empty.', 'flexible-content-sync-for-acf' ), $ref['title'] );
			return 0;
		}

		return (int) $found[0];
	}

	/**
	 * @param array{name?: string, taxonomy?: string}|null $ref
	 */
	private function portable_to_term_id( ?array $ref ): int {
		if ( empty( $ref['name'] ) || empty( $ref['taxonomy'] ) || ! taxonomy_exists( $ref['taxonomy'] ) ) {
			return 0;
		}

		$term = get_term_by( 'name', $ref['name'], $ref['taxonomy'] );
		if ( $term instanceof WP_Term ) {
			return (int) $term->term_id;
		}

		$taxonomy = get_taxonomy( $ref['taxonomy'] );
		if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->manage_terms ) ) {
			/* translators: 1: term name, 2: taxonomy name */
			$this->warnings[] = sprintf( __( 'Term "%1$s" does not exist in "%2$s" and you do not have permission to create it — reference left empty.', 'flexible-content-sync-for-acf' ), $ref['name'], $ref['taxonomy'] );
			return 0;
		}

		$inserted = wp_insert_term( $ref['name'], $ref['taxonomy'] );
		return is_wp_error( $inserted ) ? 0 : (int) $inserted['term_id'];
	}

	/**
	 * @param array{user_email?: string}|null $ref
	 */
	private function portable_to_user_id( ?array $ref ): int {
		if ( empty( $ref['user_email'] ) ) {
			return 0;
		}

		$user = get_user_by( 'email', $ref['user_email'] );
		if ( ! $user ) {
			/* translators: %s: user email */
			$this->warnings[] = sprintf( __( 'Could not find a user with email %s on this site — reference left empty.', 'flexible-content-sync-for-acf' ), $ref['user_email'] );
			return 0;
		}

		return (int) $user->ID;
	}
}
