<?php

namespace WPForms\Integrations\Drip\Api;

/**
 * Drip REST API v2 client.
 *
 * Authenticates with the account API token over Basic auth. Endpoints are scoped to the
 * Drip account the client was built with; without an account id they resolve to the
 * top-level endpoints such as `user` and `accounts`.
 *
 * @since 2.0.5
 */
class Client {

	/**
	 * API base URL.
	 *
	 * @since 2.0.5
	 */
	private const BASE_URL = 'https://api.getdrip.com/v2';

	/**
	 * Request timeout in seconds.
	 *
	 * @since 2.0.5
	 */
	private const TIMEOUT = 10;

	/**
	 * API token.
	 *
	 * @since 2.0.5
	 *
	 * @var string
	 */
	private $api_token;

	/**
	 * Drip account id the endpoints are scoped to.
	 *
	 * @since 2.0.5
	 *
	 * @var string
	 */
	private $account_id;

	/**
	 * Constructor.
	 *
	 * @since 2.0.5
	 *
	 * @param string $api_token  API token.
	 * @param string $account_id Drip account id, empty for the top-level endpoints.
	 */
	public function __construct( string $api_token, string $account_id = '' ) {

		$this->api_token  = $api_token;
		$this->account_id = $account_id;
	}

	/**
	 * GET request.
	 *
	 * @since 2.0.5
	 *
	 * @param string $endpoint Endpoint relative to the account, e.g. `campaigns`.
	 * @param array  $query    Query arguments.
	 *
	 * @return Response
	 */
	public function get( string $endpoint, array $query = [] ): Response {

		return $this->request( 'GET', add_query_arg( array_map( 'rawurlencode', $query ), $this->get_url( $endpoint ) ) );
	}

	/**
	 * POST request.
	 *
	 * @since 2.0.5
	 *
	 * @param string $endpoint Endpoint relative to the account, e.g. `subscribers`.
	 * @param array  $body     Payload, sent as JSON.
	 *
	 * @return Response
	 */
	public function post( string $endpoint, array $body = [] ): Response {

		return $this->request( 'POST', $this->get_url( $endpoint ), $body );
	}

	/**
	 * DELETE request.
	 *
	 * @since 2.0.5
	 *
	 * @param string $endpoint Endpoint relative to the account, e.g. `subscribers/{email}`.
	 *
	 * @return Response
	 */
	public function delete( string $endpoint ): Response {

		return $this->request( 'DELETE', $this->get_url( $endpoint ) );
	}

	/**
	 * Build the endpoint URL.
	 *
	 * @since 2.0.5
	 *
	 * @param string $endpoint Endpoint.
	 *
	 * @return string
	 */
	private function get_url( string $endpoint ): string {

		$account_path = $this->account_id !== '' ? '/' . $this->account_id : '';

		return self::BASE_URL . $account_path . '/' . ltrim( $endpoint, '/' );
	}

	/**
	 * Perform a request.
	 *
	 * @since 2.0.5
	 *
	 * @param string $method HTTP method.
	 * @param string $url    Full URL.
	 * @param array  $body   Payload, sent as JSON when not empty.
	 *
	 * @return Response
	 */
	private function request( string $method, string $url, array $body = [] ): Response {

		$args = [
			'method'  => $method,
			'timeout' => self::TIMEOUT,
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode( $this->api_token . ':' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Accept'        => 'application/vnd.api+json',
				'Content-Type'  => 'application/vnd.api+json',
			],
		];

		if ( $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		/**
		 * Filter the Drip API request arguments.
		 *
		 * @since 2.0.5
		 *
		 * @param array $args Arguments passed to wp_remote_request().
		 */
		$args = (array) apply_filters( 'wpforms_integrations_drip_api_client_request_args', $args );

		return new Response( wp_remote_request( $url, $args ) );
	}
}
