<?php

namespace WPForms\SetupWizard;

use WP_Error;
use WPForms\SetupWizard\Transport\Cipher;

/**
 * Hands off to the hosted wizard.
 *
 * The per-launch key goes to the wizard server to server and comes back as a
 * single-use ticket; the browser carries only the ticket, so the key never
 * reaches the page, its history or a browser extension.
 *
 * @since 2.0.0
 */
class Bridge {

	/**
	 * Auth service used to mint the per-launch key.
	 *
	 * @since 2.0.0
	 *
	 * @var Auth
	 */
	private $auth;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Auth $auth Auth service.
	 */
	public function __construct( Auth $auth ) {

		$this->auth = $auth;
	}

	/**
	 * Render the auto-submit POST form that carries the ticket to the wizard.
	 *
	 * The caller is responsible for `exit`-ing after this method returns.
	 *
	 * @since 2.0.0
	 * @since 2.0.5.1 Takes the ticket. A payload array is deprecated and read for its `ticket` key.
	 *
	 * @param string|array $ticket Ticket from `request_session()`, or a payload carrying it.
	 *
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function render( $ticket ): void {

		if ( is_array( $ticket ) ) {
			_deprecated_argument( __METHOD__, '2.0.5.1 of the WPForms plugin', 'Pass the ticket from request_session().' );

			$ticket = $ticket['ticket'] ?? '';
		}

		$ticket = (string) $ticket;

		$action   = esc_url( $this->get_handoff_url() );
		$charset  = esc_attr( get_bloginfo( 'charset' ) );
		$language = esc_attr( get_bloginfo( 'language' ) );
		$title    = esc_html__( 'WPForms Setup Wizard', 'wpforms-lite' );
		$cta      = esc_html__( 'Continue to Setup Wizard', 'wpforms-lite' );

		$style = 'html,body{margin:0}body{min-height:100vh;background:#FFFFFF}';

		self::send_standalone_document_headers();

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<!DOCTYPE html><html lang="' . $language . '"><head>'
			. '<meta charset="' . $charset . '" />'
			. '<meta name="robots" content="noindex,nofollow" />'
			. '<title>' . $title . '</title>'
			. '<style>' . $style . '</style>'
			. '</head><body>'
			. '<form id="wpforms-setup-wizard-bridge" method="POST" action="' . $action . '">'
			. '<input type="hidden" name="ticket" value="' . esc_attr( $ticket ) . '" />'
			. '<noscript><button type="submit">' . $cta . '</button></noscript>'
			. '</form>'
			. '<script>document.getElementById("wpforms-setup-wizard-bridge").submit();</script>'
			. '</body></html>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Send the security headers shared by the wizard's standalone documents.
	 *
	 * Used by both the bridge handoff page and the Lite Welcome screen, so a
	 * future hardening pass only needs to change this one place.
	 *
	 * @since 2.0.1
	 */
	public static function send_standalone_document_headers(): void {

		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Frame-Options: DENY' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
	}

	/**
	 * Trade a freshly minted key for a ticket with the hosted wizard.
	 *
	 * The wizard proves this site holds the key with a sealed call back before it
	 * answers, so a failure here also covers an unreachable wizard or site.
	 *
	 * @since 2.0.5.1
	 *
	 * @param string $exit_url    Where to send the user on close.
	 * @param string $restart_url Where to send the user on restart.
	 *
	 * @return string|WP_Error The ticket, or why the hosted wizard cannot be used.
	 */
	public function request_session( string $exit_url, string $restart_url ) {

		$refusal = $this->get_launch_refusal();

		if ( $refusal !== null ) {
			return $refusal;
		}

		$key = $this->auth->mint();

		if ( $key === '' ) {
			return new WP_Error( 'wpforms_setup_wizard_no_user' );
		}

		/**
		 * Filter the session request the plugin sends to the hosted wizard.
		 *
		 * Used by the Stripe OAuth flow to inject `current_step=payments`. The values
		 * travel server to server, never through the browser.
		 *
		 * @since 2.0.0
		 * @since 2.0.5.1 The values go to the wizard's `/session`, not the browser form.
		 *
		 * @param array $payload Session request fields.
		 */
		$payload = (array) apply_filters(
			'wpforms_setup_wizard_bridge_payload',
			[
				'exit_url'    => $exit_url,
				'restart_url' => $restart_url,
			]
		);

		$response = wp_remote_post(
			$this->get_session_url(),
			[
				// The wizard needs up to 8 seconds to reach this site and back.
				'timeout'     => 12,
				'sslverify'   => true,
				// A redirect would carry the key to wherever it points.
				'redirection' => 0,
				'body'        => array_merge(
					$payload,
					[
						'protocol' => 2,
						'key'      => $key,
						'rest_url' => $this->get_rest_url(),
					]
				),
			]
		);

		if ( is_wp_error( $response ) ) {
			// The fresh key already retired any live session; it must not outlive this failed launch.
			$this->auth->revoke_key( $key );

			return $response;
		}

		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$ticket = is_array( $body ) ? (string) ( $body['ticket'] ?? '' ) : '';

		$status = wp_remote_retrieve_response_code( $response );

		if ( $status !== 200 || ! preg_match( '/^[0-9a-f]{64}$/', $ticket ) ) {
			$code = is_array( $body ) && is_string( $body['error_code'] ?? null ) ? sanitize_text_field( $body['error_code'] ) : '';

			$this->auth->revoke_key( $key );

			// WP_Error drops an empty code, which would leave the log entry blank.
			return new WP_Error( $code !== '' ? $code : 'wpforms_setup_wizard_session_failed', '', [ 'status' => $status ] );
		}

		/**
		 * Fires once the hosted wizard has started a session for the payload.
		 *
		 * @since 2.0.5.1
		 *
		 * @param array $payload Session request fields.
		 */
		do_action( 'wpforms_setup_wizard_bridge_request_session', $payload );

		return $ticket;
	}

	/**
	 * Get why the hosted wizard must not be launched, checked before any key is minted.
	 *
	 * @since 2.0.5.1
	 *
	 * @return WP_Error|null The refusal, or null when the launch may proceed.
	 */
	private function get_launch_refusal(): ?WP_Error {

		/**
		 * Filter whether the hosted wizard is reachable.
		 *
		 * Return false to skip the hosted wizard and use the local one.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $reachable Whether to try the hosted wizard.
		 */
		if ( apply_filters( 'wpforms_setup_wizard_bridge_spa_reachable', true ) === false ) {
			return new WP_Error( 'wpforms_setup_wizard_forced_unreachable' );
		}

		$handoff_url = $this->get_handoff_url();

		if ( ! $this->is_secure_handoff_url( $handoff_url ) ) {
			return new WP_Error( 'wpforms_setup_wizard_insecure_handoff' );
		}

		if ( ! Cipher::is_supported() ) {
			return new WP_Error( 'wpforms_setup_wizard_no_cipher' );
		}

		return null;
	}

	/**
	 * Build the handshake payload for the current user.
	 *
	 * @since 2.0.0
	 * @deprecated 2.0.5.1
	 *
	 * @param string $exit_url    Where to send the user on close.
	 * @param string $restart_url Where to send the user on restart.
	 *
	 * @return array The `ticket` for `render()`, or an empty array when the hosted wizard cannot be used.
	 */
	public function build_payload( string $exit_url, string $restart_url ): array {

		_deprecated_function( __METHOD__, '2.0.5.1 of the WPForms plugin', __CLASS__ . '::request_session()' );

		$ticket = $this->request_session( $exit_url, $restart_url );

		return is_wp_error( $ticket ) ? [] : [ 'ticket' => $ticket ];
	}

	/**
	 * Get the handoff form data for a client-side submit.
	 *
	 * The Lite Welcome screen POSTs the handoff from JS after its AJAX round-trip,
	 * so it needs the form action and the ticket rather than a rendered document.
	 *
	 * @since 2.0.1
	 * @deprecated 2.0.5.1
	 *
	 * @param string $exit_url    Where to send the user on close.
	 * @param string $restart_url Where to send the user on restart.
	 *
	 * @return array `action` and a `payload` carrying the ticket, or an empty array when the hosted wizard cannot be used.
	 */
	public function get_handoff_data( string $exit_url, string $restart_url ): array {

		_deprecated_function( __METHOD__, '2.0.5.1 of the WPForms plugin', __CLASS__ . '::request_handoff_data()' );

		$handoff = $this->request_handoff_data( $exit_url, $restart_url );

		return is_wp_error( $handoff ) ? [] : $handoff;
	}

	/**
	 * Start a hosted wizard session and get its handoff form data for a client-side submit.
	 *
	 * @since 2.0.5.1
	 *
	 * @param string $exit_url    Where to send the user on close.
	 * @param string $restart_url Where to send the user on restart.
	 *
	 * @return array|WP_Error `action` and `payload`, or why the hosted wizard cannot be used.
	 */
	public function request_handoff_data( string $exit_url, string $restart_url ) {

		$ticket = $this->request_session( $exit_url, $restart_url );

		if ( is_wp_error( $ticket ) ) {
			return $ticket;
		}

		return [
			'action'  => $this->get_handoff_url(),
			'payload' => [ 'ticket' => $ticket ],
		];
	}

	/**
	 * Whether the hosted wizard may be launched.
	 *
	 * @since 2.0.0
	 * @deprecated 2.0.5.1
	 *
	 * @return bool
	 */
	public function is_spa_reachable(): bool {

		_deprecated_function( __METHOD__, '2.0.5.1 of the WPForms plugin', __CLASS__ . '::request_session()' );

		return $this->get_launch_refusal() === null;
	}

	/**
	 * Whether the key may be sent to a handoff URL: https, or plain http only on a local or development site.
	 *
	 * @since 2.0.5.1
	 *
	 * @param string $url Handoff URL.
	 *
	 * @return bool
	 */
	private function is_secure_handoff_url( string $url ): bool {

		if ( strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) === 'https' ) {
			return true;
		}

		return in_array( wp_get_environment_type(), [ 'local', 'development' ], true );
	}

	/**
	 * Get the wizard's session endpoint, next to the handoff endpoint.
	 *
	 * @since 2.0.5.1
	 *
	 * @return string
	 */
	private function get_session_url(): string {

		return trailingslashit( $this->get_handoff_url() ) . 'session';
	}

	/**
	 * Get this site's wizard REST base, the URL the SPA's calls are proxied to.
	 *
	 * @since 2.0.2.2
	 *
	 * @return string
	 */
	private function get_rest_url(): string {

		return rest_url( 'wpforms/v1/setup-wizard' );
	}

	/**
	 * Get the SPA endpoint that consumes the handshake POST.
	 *
	 * The `WPFORMS_SETUP_WIZARD_URL` constant overrides the default for local
	 * development against a locally hosted wizard.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function get_handoff_url(): string {

		return $this->get_handoff_url_override() ?? 'https://sw.wpformsapi.com/setupwizard/v1';
	}

	/**
	 * Get the handoff URL the `WPFORMS_SETUP_WIZARD_URL` constant sets, if any.
	 *
	 * @since 2.0.5
	 *
	 * @return string|null
	 */
	protected function get_handoff_url_override(): ?string {

		return defined( 'WPFORMS_SETUP_WIZARD_URL' ) ? (string) WPFORMS_SETUP_WIZARD_URL : null;
	}
}
