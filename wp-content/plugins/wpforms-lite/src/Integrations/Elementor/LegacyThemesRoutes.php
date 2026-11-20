<?php

namespace WPForms\Integrations\Elementor;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response; // phpcs:ignore WPForms.PHP.UseStatement.UnusedUseStatement
use WPForms\Integrations\Gutenberg\ThemesData;

// phpcs:disable WPForms.Comments.DeprecatedTag.InvalidDeprecatedVersion -- The release script substitutes the placeholder.
/**
 * The Elementor widget's themes routes, kept for integrations that still call them.
 *
 * The widget's own themes store was merged into the shared custom themes, so both routes
 * read and write those: the same themes the widget now renders with.
 *
 * @since      2.0.5
 * @deprecated 2.0.5 Use the `/wpforms/v1/themes/` and `/wpforms/v1/themes/custom/` routes.
 */
class LegacyThemesRoutes {
	// phpcs:enable WPForms.Comments.DeprecatedTag.InvalidDeprecatedVersion

	/**
	 * Route namespace.
	 *
	 * @since 2.0.5
	 */
	private const ROUTE_NAMESPACE = '/wpforms/v1/';

	/**
	 * Shared themes data.
	 *
	 * @since 2.0.5
	 *
	 * @var ThemesData
	 */
	private $themes_data;

	/**
	 * Constructor.
	 *
	 * @since 2.0.5
	 *
	 * @param ThemesData $themes_data Shared themes data.
	 */
	public function __construct( ThemesData $themes_data ) {

		$this->themes_data = $themes_data;
	}

	/**
	 * Register the routes, with the paths, methods and permissions they had.
	 *
	 * @since 2.0.5
	 */
	public function register(): void {

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/elementor/themes/',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_themes' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			]
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/elementor/themes/custom/',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'save_themes' ],
				'permission_callback' => [ $this, 'admin_permissions_check' ],
			]
		);
	}

	/**
	 * Allow users who can edit posts.
	 *
	 * @since 2.0.5
	 *
	 * @return true|WP_Error
	 */
	public function permissions_check() {

		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'rest_forbidden', esc_html__( 'This route is private.', 'wpforms-lite' ), [ 'status' => 401 ] );
		}

		return true;
	}

	/**
	 * Allow administrators only.
	 *
	 * @since 2.0.5
	 *
	 * @return true|WP_Error
	 */
	public function admin_permissions_check() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', esc_html__( 'This route is accessible only to administrators.', 'wpforms-lite' ), [ 'status' => 401 ] );
		}

		return true;
	}

	/**
	 * Return the custom and the stock themes.
	 *
	 * @since 2.0.5
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_themes() {

		_deprecated_function( 'GET /wpforms/v1/elementor/themes/', '2.0.5 of the WPForms plugin', 'GET /wpforms/v1/themes/' );

		$custom_themes  = $this->themes_data->get_custom_themes();
		$wpforms_themes = $this->themes_data->get_wpforms_themes();

		return rest_ensure_response(
			[
				'custom'  => ! empty( $custom_themes ) ? $custom_themes : null,
				'wpforms' => ! empty( $wpforms_themes ) ? $wpforms_themes : null,
			]
		);
	}

	/**
	 * Save the posted custom themes.
	 *
	 * The route replaced the widget's own themes, while the shared themes hold the block's and
	 * the panel's too: a caller that only knows the widget's themes adds or updates, never deletes.
	 *
	 * @since 2.0.5
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function save_themes( WP_REST_Request $request ) {

		_deprecated_function( 'POST /wpforms/v1/elementor/themes/custom/', '2.0.5 of the WPForms plugin', 'POST /wpforms/v1/themes/custom/' );

		$posted = (array) ( $request->get_param( 'customThemes' ) ?? [] );
		$themes = array_merge( $this->themes_data->get_custom_themes(), $posted );

		if ( ! $this->themes_data->update_custom_themes_file( $themes ) ) {
			return rest_ensure_response(
				[
					'result' => false,
					'error'  => esc_html__( 'Can\'t save theme data.', 'wpforms-lite' ),
				]
			);
		}

		return rest_ensure_response( [ 'result' => true ] );
	}
}
