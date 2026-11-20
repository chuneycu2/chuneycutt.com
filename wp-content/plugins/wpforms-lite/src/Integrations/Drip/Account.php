<?php

namespace WPForms\Integrations\Drip;

use RuntimeException;
use WPForms\Integrations\Drip\Api\Client;

/**
 * Connects a Drip account: validates the API token against Drip and stores it.
 *
 * Both the Settings > Integrations page and the builder's Add New Account modal go through here.
 *
 * @since 2.0.5
 */
class Account {

	/**
	 * The fields that collect a new account: a name and the API token.
	 *
	 * @since 2.0.5
	 *
	 * @return string
	 *
	 * @noinspection HtmlUnknownTarget
	 */
	public static function get_fields_html(): string {

		$name = Core::get_instance()->name;

		$html = '<p>' . sprintf(
			wp_kses( /* translators: %1$s - documentation URL, %2$s - provider name. */
				__( 'If you need help connecting WPForms to %2$s, <a href="%1$s" rel="noopener noreferrer" target="_blank">read our documentation</a>.', 'wpforms-lite' ),
				[
					'a' => [
						'href'   => [],
						'rel'    => [],
						'target' => [],
					],
				]
			),
			esc_url( wpforms_utm_link( 'https://wpforms.com/docs/how-to-install-and-use-the-drip-addon-in-wpforms/#initial-setup', 'Settings - Integration', 'Drip Documentation' ) ),
			esc_html( $name )
		) . '</p>';

		$html .= sprintf(
			'<input type="text" name="account_name" placeholder="%s">',
			/* translators: %s - provider name. */
			esc_attr( sprintf( __( '%s Account Name', 'wpforms-lite' ), $name ) )
		);

		$html .= sprintf(
			'<input type="text" name="api_token" placeholder="%s" class="wpforms-required">',
			/* translators: %s - provider name. */
			esc_attr( sprintf( __( '%s API Token *', 'wpforms-lite' ), $name ) )
		);

		$html .= sprintf( '<p class="error hidden">%s</p>', esc_html__( 'Drip API Token is required.', 'wpforms-lite' ) );

		return $html;
	}

	/**
	 * Validate the token and store the account.
	 *
	 * @since 2.0.5
	 *
	 * @param string $api_token API token as typed by the user.
	 * @param string $label     Account name as typed by the user; the Drip user name or email when empty.
	 *
	 * @return string Key the account is stored under.
	 *
	 * @throws RuntimeException With a user-facing message when the token is empty or already connected, the request never reached Drip, or Drip rejects it.
	 */
	public function connect( string $api_token, string $label = '' ): string {

		$api_token = Helpers::sanitize_api_token( $api_token );

		if ( $api_token === '' ) {
			throw new RuntimeException( esc_html__( 'Drip API Token is required.', 'wpforms-lite' ) );
		}

		// The same token connected twice would list one account several times in the builder.
		if ( $this->is_connected( $api_token ) ) {
			throw new RuntimeException( esc_html__( 'This account is already connected.', 'wpforms-lite' ) );
		}

		$response = ( new Client( $api_token ) )->get( 'user' );
		$user     = $response->get( 'users' )[0] ?? [];

		if ( empty( $user ) && $response->get_response_code() === 0 ) {
			throw new RuntimeException( esc_html( $response->get_error_message() ) );
		}

		if ( empty( $user ) ) {
			throw new RuntimeException( esc_html__( 'Invalid Drip API credentials. Please check your information and try again.', 'wpforms-lite' ) );
		}

		$label = sanitize_text_field( $label );

		if ( $label === '' ) {
			$label = sanitize_text_field( ! empty( $user['name'] ) ? $user['name'] : ( $user['email'] ?? '' ) );
		}

		$key = uniqid();

		wpforms_update_providers_options(
			Core::SLUG,
			[
				'api_token' => $api_token,
				'label'     => $label,
				'date'      => time(),
			],
			$key
		);

		return $key;
	}

	/**
	 * Whether an account with this API token is already stored.
	 *
	 * @since 2.0.5
	 *
	 * @param string $api_token Sanitized API token.
	 *
	 * @return bool
	 */
	private function is_connected( string $api_token ): bool {

		foreach ( (array) wpforms_get_providers_options( Core::SLUG ) as $account ) {
			$stored = (string) ( $account['api_token'] ?? '' );

			if ( $stored === $api_token ) {
				return true;
			}
		}

		return false;
	}
}
