<?php

namespace WPForms\Integrations\Drip\Api;

// phpcs:ignore WPForms.PHP.UseStatement.UnusedUseStatement
use WP_Error;

/**
 * Drip API response wrapper.
 *
 * Drip reports failures as an `errors` list in the body, with any HTTP status,
 * so a response is an error when the transport failed, the status is outside 2xx,
 * or that list is present.
 *
 * @since 2.0.5
 */
class Response {

	/**
	 * Raw response as returned by wp_remote_request().
	 *
	 * @since 2.0.5
	 *
	 * @var array|WP_Error
	 */
	private $raw;

	/**
	 * Decoded response body.
	 *
	 * @since 2.0.5
	 *
	 * @var array
	 */
	private $body;

	/**
	 * Constructor.
	 *
	 * @since 2.0.5
	 *
	 * @param array|WP_Error $raw Raw response as returned by wp_remote_request().
	 */
	public function __construct( $raw ) {

		$this->raw  = $raw;
		$this->body = $this->decode_body();
	}

	/**
	 * Whether the request failed or Drip reported errors.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	public function has_errors(): bool {

		if ( is_wp_error( $this->raw ) ) {
			return true;
		}

		if ( isset( $this->body['errors'] ) ) {
			return true;
		}

		$code = $this->get_response_code();

		return $code < 200 || $code > 299;
	}

	/**
	 * Get a human-readable error message, empty for a successful response.
	 *
	 * @since 2.0.5
	 *
	 * @return string
	 */
	public function get_error_message(): string {

		if ( is_wp_error( $this->raw ) ) {
			return (string) $this->raw->get_error_message();
		}

		if ( ! $this->has_errors() ) {
			return '';
		}

		if ( ! empty( $this->body['errors'][0]['message'] ) ) {
			return (string) $this->body['errors'][0]['message'];
		}

		return (string) wp_remote_retrieve_response_message( $this->raw );
	}

	/**
	 * Get the HTTP status code, 0 when the request never reached the server.
	 *
	 * @since 2.0.5
	 *
	 * @return int
	 */
	public function get_response_code(): int {

		return (int) wp_remote_retrieve_response_code( $this->raw );
	}

	/**
	 * Get the decoded body.
	 *
	 * @since 2.0.5
	 *
	 * @return array
	 */
	public function get_body(): array {

		return $this->body;
	}

	/**
	 * Get a top-level body value.
	 *
	 * @since 2.0.5
	 *
	 * @param string $key Body key, e.g. `accounts`.
	 *
	 * @return mixed|null Null when the key is absent.
	 */
	public function get( string $key ) {

		return $this->body[ $key ] ?? null;
	}

	/**
	 * Decode the JSON body; anything that is not a JSON object becomes an empty array.
	 *
	 * @since 2.0.5
	 *
	 * @return array
	 */
	private function decode_body(): array {

		if ( is_wp_error( $this->raw ) ) {
			return [];
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $this->raw ), true );

		return is_array( $decoded ) ? $decoded : [];
	}
}
