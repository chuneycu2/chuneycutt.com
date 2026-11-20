<?php

namespace WPForms\SetupWizard;

use SplObjectStorage;
use WP_Error;
use WP_REST_Request;
use WPForms\SetupWizard\Transport\Cipher;

/**
 * Per-launch key authentication for the Setup Wizard.
 *
 * Each launch mints a raw key bound to the launching admin and retires the previous
 * one. The hosted wizard receives the derived wire key server to server and seals
 * every call with it, so a request is authentic only when its body opens.
 *
 * @since 2.0.0
 */
class Auth {

	/**
	 * Transient name storing the active key as `[ 'raw' => hex, 'user_id' => int, 'issued_at' => int ]`, for its hard cap.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const TRANSIENT_TOKEN = 'wpforms_setup_wizard_token';

	/**
	 * Idle lifetime of a key in seconds, refreshed by every verified call.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	private const TTL = HOUR_IN_SECONDS;

	/**
	 * Transient name prefix of a key's idle clock.
	 *
	 * Kept apart from the key row, so a verified call refreshing it cannot write back a
	 * key that a newer launch or a revoke has replaced.
	 *
	 * @since 2.0.5.1
	 *
	 * @var string
	 */
	private const TRANSIENT_SEEN = 'wpforms_setup_wizard_seen_';

	/**
	 * How long a key stays valid from the moment it was minted, whatever the activity.
	 *
	 * @since 2.0.5.1
	 *
	 * @var int
	 */
	private const MAX_LIFETIME = 2 * HOUR_IN_SECONDS;

	/**
	 * How far a request timestamp may be from this site's clock, either way.
	 *
	 * @since 2.0.5.1
	 *
	 * @var int
	 */
	public const SIGNATURE_WINDOW = 15 * 60;

	/**
	 * Option name prefix of a claimed nonce.
	 *
	 * @since 2.0.5.1
	 *
	 * @var string
	 */
	private const NONCE_PREFIX = 'wpforms_sw_nonce_';

	/**
	 * Requests verified in this PHP request, keyed by the request object.
	 *
	 * @since 2.0.5.1
	 *
	 * @var SplObjectStorage
	 */
	private $verified;

	/**
	 * Constructor.
	 *
	 * @since 2.0.5.1
	 */
	public function __construct() {

		$this->verified = new SplObjectStorage();
	}

	/**
	 * Mint a new key for the current user, retiring any previous one.
	 *
	 * The caller must check that the current user is permitted to launch the wizard.
	 *
	 * @since 2.0.5.1
	 *
	 * @return string Wire key for the hosted wizard, or an empty string without a current user.
	 */
	public function mint(): string {

		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return '';
		}

		$raw = random_bytes( 32 );

		set_transient(
			self::TRANSIENT_TOKEN,
			[
				'raw'       => bin2hex( $raw ),
				'user_id'   => $user_id,
				'issued_at' => time(),
			],
			self::MAX_LIFETIME
		);

		set_transient( self::seen_name( bin2hex( $raw ) ), 1, self::TTL );

		// A run closed without /complete or /revoke leaves its claims behind until a launch.
		$this->sweep_nonces();

		return Cipher::wire_key( $user_id, $raw );
	}

	/**
	 * Issue a token bound to the current user.
	 *
	 * @since 2.0.0
	 * @deprecated 2.0.5.1
	 *
	 * @return string Always an empty string.
	 */
	public function generate_token(): string {

		_deprecated_function( __METHOD__, '2.0.5.1 of the WPForms plugin', __CLASS__ . '::mint()' );

		// This value used to go to the browser, and the wire key is the sealing secret, so none is issued.
		return '';
	}

	/**
	 * Verify a sealed request and return the bound user ID.
	 *
	 * The body is opened before the timestamp is checked, so only a caller holding the
	 * key can learn that its clock is off.
	 *
	 * @since 2.0.5.1
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return int|WP_Error The bound user ID, or a WP_Error coded `auth_failed` or `clock_skew`.
	 */
	public function verify( WP_REST_Request $request ) {

		$timestamp = (string) $request->get_header( 'X-WPForms-Timestamp' );
		$nonce     = (string) $request->get_header( 'X-WPForms-Nonce' );
		$body      = json_decode( $request->get_body(), true );
		$stored    = $this->get_stored_key();

		if ( ! ctype_digit( $timestamp ) || ! is_array( $body ) || ! is_string( $body['c'] ?? null ) || $stored === null ) {
			return $this->get_refusal();
		}

		$user_id   = $stored['user_id'];
		$wire_key  = $stored['wire_key'];
		$endpoint  = self::endpoint( $request->get_route() );
		$plaintext = Cipher::open( $wire_key, $body['c'], Cipher::request_aad( $endpoint, $timestamp, $nonce ), $nonce );
		$params    = json_decode( $plaintext, true );

		if ( ! is_array( $params ) ) {
			return $this->get_refusal();
		}

		$now = time();

		if ( abs( $now - (int) $timestamp ) > self::SIGNATURE_WINDOW ) {
			// The site's time lets the wizard log how far off this clock is.
			return new WP_Error(
				'clock_skew',
				'',
				[
					'status' => 401,
					'time'   => $now,
				]
			);
		}

		if ( $now - (int) ( $stored['issued_at'] ?? 0 ) > self::MAX_LIFETIME || get_transient( self::seen_name( $stored['raw'] ) ) === false ) {
			return $this->get_refusal();
		}

		if ( ! $this->claim_nonce( $nonce, (int) $timestamp + self::SIGNATURE_WINDOW ) ) {
			return $this->get_refusal();
		}

		set_transient( self::seen_name( $stored['raw'] ), 1, self::TTL );

		$this->verified[ $request ] = [
			'user_id'  => $user_id,
			'params'   => $params,
			'nonce'    => $nonce,
			'endpoint' => $endpoint,
			'wire_key' => $wire_key,
		];

		return $user_id;
	}

	/**
	 * Get what verify() established for a request, if it passed.
	 *
	 * @since 2.0.5.1
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return array|null `user_id`, `params`, `nonce`, `endpoint` and `wire_key`, or null when unverified.
	 */
	public function verified( WP_REST_Request $request ): ?array {

		// SplObjectStorage::contains() is deprecated as of PHP 8.5; isset() goes through offsetExists().
		return isset( $this->verified[ $request ] ) ? $this->verified[ $request ] : null;
	}

	/**
	 * Validate a REST request and return the bound user ID.
	 *
	 * @since 2.0.0
	 * @deprecated 2.0.5.1
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return int Bound user ID, or 0 if the request is not verified.
	 */
	public function validate_request( WP_REST_Request $request ): int {

		_deprecated_function( __METHOD__, '2.0.5.1 of the WPForms plugin', __CLASS__ . '::verify()' );

		// The token header is no longer read: only a request verify() opened counts.
		return (int) ( $this->verified( $request )['user_id'] ?? 0 );
	}

	/**
	 * Revoke the active key (e.g., on wizard completion or exit).
	 *
	 * @since 2.0.0
	 */
	public function revoke(): void {

		$stored = $this->get_stored_key();

		delete_transient( self::TRANSIENT_TOKEN );

		if ( $stored !== null ) {
			delete_transient( self::seen_name( $stored['raw'] ) );
		}

		// No verified call follows a finished run, so the sampled sweep would never reach its claims.
		$this->sweep_nonces();
	}

	/**
	 * Revoke a key only while it is still the current one.
	 *
	 * A launch that failed must not delete the key a newer launch minted meanwhile.
	 *
	 * @since 2.0.5.1
	 *
	 * @param string $wire_key Wire key the caller minted.
	 */
	public function revoke_key( string $wire_key ): void {

		$stored = $this->get_stored_key();

		if ( $stored === null || ! hash_equals( $stored['wire_key'], $wire_key ) ) {
			return;
		}

		// The key row may already hold a newer launch's key that this request's option cache cannot see,
		// so only this key's idle clock is deleted; verify() refuses a key without one.
		delete_transient( self::seen_name( $stored['raw'] ) );

		$this->sweep_nonces();
	}

	/**
	 * Get the stored key with its wire key, or null when the row is missing or malformed.
	 *
	 * @since 2.0.5.1
	 *
	 * @return array|null `raw`, `user_id`, `issued_at` and `wire_key`.
	 */
	private function get_stored_key(): ?array {

		$stored = get_transient( self::TRANSIENT_TOKEN );

		if ( ! is_array( $stored ) || ! self::is_raw_key( $stored['raw'] ?? null ) || (int) ( $stored['user_id'] ?? 0 ) <= 0 ) {
			return null;
		}

		$stored['user_id']  = (int) $stored['user_id'];
		$stored['wire_key'] = Cipher::wire_key( $stored['user_id'], (string) hex2bin( $stored['raw'] ) );

		return $stored;
	}

	/**
	 * Whether a stored raw key has the shape mint() writes, so a corrupted row fails closed without a hex2bin() warning.
	 *
	 * @since 2.0.5.1
	 *
	 * @param mixed $raw Stored raw key.
	 *
	 * @return bool
	 */
	private static function is_raw_key( $raw ): bool {

		return is_string( $raw ) && preg_match( '/^[0-9a-f]{64}$/', $raw ) === 1;
	}

	/**
	 * Get the transient name of a key's idle clock.
	 *
	 * @since 2.0.5.1
	 *
	 * @param string $raw_hex Raw key, hex.
	 *
	 * @return string
	 */
	private static function seen_name( string $raw_hex ): string {

		return self::TRANSIENT_SEEN . substr( hash( 'sha256', $raw_hex ), 0, 32 );
	}

	/**
	 * Get the endpoint a request was sealed for, from the route it asked for.
	 *
	 * The route is the same under pretty permalinks and `?rest_route=`.
	 *
	 * @since 2.0.5.1
	 *
	 * @param string $route Requested route.
	 *
	 * @return string
	 */
	private static function endpoint( string $route ): string {

		return trim( (string) substr( $route, strlen( '/' . RestApi::ROUTE_NAMESPACE . RestApi::ROUTE_BASE ) ), '/' );
	}

	/**
	 * Claim a nonce, which succeeds once per nonce.
	 *
	 * A plain INSERT fails on the duplicate option name, so two concurrent replays
	 * cannot both claim it; add_option() checks first and would let both through.
	 *
	 * @since 2.0.5.1
	 *
	 * @param string $nonce   Request nonce.
	 * @param int    $expires When the claim may be swept.
	 *
	 * @return bool
	 */
	private function claim_nonce( string $nonce, int $expires ): bool {

		global $wpdb;

		$this->maybe_sweep_nonces();

		$suppressed = $wpdb->suppress_errors();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				self::NONCE_PREFIX . $nonce,
				(string) $expires
			)
		);

		$wpdb->suppress_errors( $suppressed );

		return $inserted === 1;
	}

	/**
	 * Delete expired nonce claims now and then.
	 *
	 * @since 2.0.5.1
	 */
	private function maybe_sweep_nonces(): void {

		if ( wp_rand( 1, 50 ) !== 1 ) {
			return;
		}

		$this->sweep_nonces();
	}

	/**
	 * Delete expired nonce claims.
	 *
	 * @since 2.0.5.1
	 */
	private function sweep_nonces(): void {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d",
				$wpdb->esc_like( self::NONCE_PREFIX ) . '%',
				time()
			)
		);
	}

	/**
	 * Get the one error every failed check returns, so a refusal does not say which check it tripped.
	 *
	 * @since 2.0.5.1
	 *
	 * @return WP_Error
	 */
	public function get_refusal(): WP_Error {

		return new WP_Error( 'auth_failed', '', [ 'status' => 401 ] );
	}
}
