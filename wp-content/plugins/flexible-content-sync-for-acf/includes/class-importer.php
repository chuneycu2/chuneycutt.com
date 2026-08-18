<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FCSA_Importer {

	/**
	 * Create a new post from a decoded export payload.
	 *
	 * @param array<string, mixed> $data
	 * @return array{post_id: int, warnings: string[]}|array{error: string}
	 */
	public static function import( array $data ): array {
		$post_data = is_array( $data['post'] ?? null ) ? $data['post'] : null;

		if ( null === $post_data || empty( $post_data['post_type'] ) ) {
			return [ 'error' => __( 'This file does not look like a Flexible Content Sync for ACF export.', 'flexible-content-sync-for-acf' ) ];
		}

		// A missing 'format_version' means the file predates this check (the
		// original, flat-by-field-name "acf" shape) — treat that as v1 so it
		// is still rejected with a clear message instead of silently
		// importing no ACF data at all under the new field-group-keyed shape.
		$format_version = (int) ( $data['format_version'] ?? 1 );
		if ( FCSA_EXPORT_FORMAT_VERSION !== $format_version ) {
			return [ 'error' => __( 'This file was exported with an incompatible version of this plugin — make sure both sites run the same plugin version, then re-export and try again.', 'flexible-content-sync-for-acf' ) ];
		}

		$post_type = sanitize_key( $post_data['post_type'] );

		if ( ! post_type_exists( $post_type ) ) {
			/* translators: %s: post type slug */
			return [ 'error' => sprintf( __( 'This site has no "%s" post type registered — cannot import.', 'flexible-content-sync-for-acf' ), $post_type ) ];
		}

		if ( ! FCSA_Settings::is_enabled_for( $post_type ) ) {
			/* translators: %s: post type slug */
			return [ 'error' => sprintf( __( 'Importing into "%s" is not enabled in Flexible Content Sync for ACF settings.', 'flexible-content-sync-for-acf' ), $post_type ) ];
		}

		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->create_posts ) ) {
			return [ 'error' => __( 'You do not have permission to create this type of content.', 'flexible-content-sync-for-acf' ) ];
		}

		$post_id = wp_insert_post(
			[
				'post_type'    => $post_type,
				'post_title'   => sanitize_text_field( $post_data['post_title'] ?? '' ),
				'post_content' => wp_kses_post( $post_data['post_content'] ?? '' ),
				'post_excerpt' => sanitize_textarea_field( $post_data['post_excerpt'] ?? '' ),
				'post_status'  => 'draft',
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return [ 'error' => $post_id->get_error_message() ];
		}

		$acf_importer = new FCSA_ACF_Importer();

		if ( ! empty( $data['featured_image'] ) && is_array( $data['featured_image'] ) ) {
			self::import_featured_image( $post_id, $data['featured_image'], $acf_importer );
		}

		if ( ! empty( $data['taxonomies'] ) && is_array( $data['taxonomies'] ) ) {
			self::import_taxonomies( $post_id, $data['taxonomies'] );
		}

		if ( class_exists( 'ACF' ) && ! empty( $data['acf'] ) && is_array( $data['acf'] ) ) {
			$acf_importer->import_post_fields( $post_id, $data['acf'] );
		}

		return [
			'post_id'  => (int) $post_id,
			'warnings' => $acf_importer->get_warnings(),
		];
	}

	/**
	 * @param array<string, mixed> $image
	 */
	private static function import_featured_image( int $post_id, array $image, FCSA_ACF_Importer $acf_importer ): void {
		$attachment_id = $acf_importer->import_single_attachment( $image );

		if ( $attachment_id > 0 ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}

	/**
	 * @param array<string, string[]> $taxonomies
	 */
	private static function import_taxonomies( int $post_id, array $taxonomies ): void {
		foreach ( $taxonomies as $taxonomy => $term_names ) {
			$taxonomy = sanitize_key( $taxonomy );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$term_ids = [];
			foreach ( (array) $term_names as $name ) {
				$name = sanitize_text_field( $name );
				if ( '' === $name ) {
					continue;
				}

				$term = get_term_by( 'name', $name, $taxonomy );
				if ( $term instanceof WP_Term ) {
					$term_ids[] = $term->term_id;
					continue;
				}

				$inserted = wp_insert_term( $name, $taxonomy );
				if ( ! is_wp_error( $inserted ) ) {
					$term_ids[] = (int) $inserted['term_id'];
				}
			}

			if ( ! empty( $term_ids ) ) {
				wp_set_post_terms( $post_id, $term_ids, $taxonomy );
			}
		}
	}
}
