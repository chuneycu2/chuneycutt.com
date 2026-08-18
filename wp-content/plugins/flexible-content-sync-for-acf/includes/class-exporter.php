<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FCSA_Exporter {

	/**
	 * Build the full portable representation of a single post: core fields,
	 * featured image, taxonomy terms, and every ACF field.
	 *
	 * @return array<string, mixed>
	 */
	public static function build( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return [];
		}

		$thumbnail_id = get_post_thumbnail_id( $post_id );

		return [
			'fcsa_version'     => FCSA_VERSION,
			'format_version'   => FCSA_EXPORT_FORMAT_VERSION,
			'exported_at'      => gmdate( 'c' ),
			'source_site'      => home_url(),
			'post'             => [
				'post_type'    => $post->post_type,
				'post_title'   => $post->post_title,
				'post_content' => $post->post_content,
				'post_excerpt' => $post->post_excerpt,
				'post_name'    => $post->post_name,
			],
			'featured_image'   => $thumbnail_id ? FCSA_ACF_Resolver::attachment_to_portable( $thumbnail_id ) : null,
			'taxonomies'       => self::export_taxonomies( $post_id, $post->post_type ),
			'acf'              => class_exists( 'ACF' ) ? FCSA_ACF_Resolver::export_post_fields( $post_id ) : [],
		];
	}

	/**
	 * Stream a post's export as a downloadable JSON file. Exits on completion.
	 */
	public static function stream_download( int $post_id ): void {
		$data = self::build( $post_id );

		if ( empty( $data ) ) {
			wp_die( esc_html__( 'Nothing to export — post not found.', 'flexible-content-sync-for-acf' ) );
		}

		$filename = sanitize_file_name( ( $data['post']['post_name'] ?: 'flexible-content-sync-for-acf' ) . '-' . gmdate( 'Y-m-d' ) . '.json' );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * @return array<string, string[]> taxonomy => term names
	 */
	private static function export_taxonomies( int $post_id, string $post_type ): array {
		$out = [];

		foreach ( get_object_taxonomies( $post_type, 'names' ) as $taxonomy ) {
			$terms = get_the_terms( $post_id, $taxonomy );
			if ( ! is_array( $terms ) || empty( $terms ) ) {
				continue;
			}
			$out[ $taxonomy ] = wp_list_pluck( $terms, 'name' );
		}

		return $out;
	}
}
