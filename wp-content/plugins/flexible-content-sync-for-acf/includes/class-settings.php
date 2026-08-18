<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FCSA_Settings {

	private const OPTION_KEY = 'fcsa_settings';

	private const DEFAULTS = [
		'post_types' => [ 'post', 'page' ],
	];

	/**
	 * @return array{post_types: string[]}
	 */
	public static function get_all(): array {
		$saved = get_option( self::OPTION_KEY, [] );
		return array_merge( self::DEFAULTS, is_array( $saved ) ? $saved : [] );
	}

	/**
	 * @return string[]
	 */
	public static function get_enabled_post_types(): array {
		$all = self::get_all();
		return is_array( $all['post_types'] ) ? $all['post_types'] : self::DEFAULTS['post_types'];
	}

	public static function is_enabled_for( string $post_type ): bool {
		return in_array( $post_type, self::get_enabled_post_types(), true );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function save( array $data ): void {
		$raw_types  = (array) ( $data['post_types'] ?? [] );
		$post_types = array_values( array_intersect( array_map( 'sanitize_key', $raw_types ), self::available_post_types() ) );

		update_option( self::OPTION_KEY, [ 'post_types' => $post_types ], false );
	}

	/**
	 * Public, non-attachment post types eligible for export/import.
	 *
	 * @return string[]
	 */
	public static function available_post_types(): array {
		$types = get_post_types( [ 'public' => true ], 'names' );
		unset( $types['attachment'] );
		return array_values( $types );
	}
}
