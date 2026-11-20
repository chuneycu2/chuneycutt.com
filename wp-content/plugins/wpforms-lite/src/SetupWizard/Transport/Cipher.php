<?php

namespace WPForms\SetupWizard\Transport;

/**
 * AES-256-GCM sealing for the v2 transport between the hosted wizard and this site.
 *
 * The hosted wizard implements the same construction byte for byte, so nothing here
 * can change without a matching wizard release.
 *
 * Lite's `RestApi::is_pro_transport_ready()` detects a transport-ready Pro by this file's path,
 * so moving or renaming it needs a matching change there.
 *
 * @since 2.0.5.1
 */
class Cipher {

	/**
	 * OpenSSL cipher name.
	 *
	 * @since 2.0.5.1
	 *
	 * @var string
	 */
	private const ALGORITHM = 'aes-256-gcm';

	/**
	 * HKDF info string the AEAD key is derived under.
	 *
	 * @since 2.0.5.1
	 *
	 * @var string
	 */
	private const KEY_INFO = 'wpforms-setup-wizard/v2/aead';

	/**
	 * Protocol label that opens every associated-data string.
	 *
	 * @since 2.0.5.1
	 *
	 * @var string
	 */
	private const LABEL = 'wpforms-setup-wizard/2';

	/**
	 * Nonce length in bytes.
	 *
	 * @since 2.0.5.1
	 *
	 * @var int
	 */
	private const NONCE_BYTES = 12;

	/**
	 * GCM tag length in bytes.
	 *
	 * @since 2.0.5.1
	 *
	 * @var int
	 */
	private const TAG_LENGTH = 16;

	/**
	 * Get the wire key the hosted wizard receives for a raw key.
	 *
	 * Only the raw key is stored, so a database copy without the salts yields no key.
	 *
	 * @since 2.0.5.1
	 *
	 * @param int    $user_id User the key is bound to.
	 * @param string $raw     Raw key bytes.
	 *
	 * @return string
	 */
	public static function wire_key( int $user_id, string $raw ): string {

		return hash_hmac( 'sha512', $user_id . '|' . $raw, wp_salt() );
	}

	/**
	 * Get the AEAD key for a wire key.
	 *
	 * @since 2.0.5.1
	 *
	 * @param string $wire_key Wire key.
	 *
	 * @return string
	 */
	public static function aead_key( string $wire_key ): string {

		return hash_hkdf( 'sha256', $wire_key, 32, self::KEY_INFO, '' );
	}

	/**
	 * Get a fresh nonce.
	 *
	 * @since 2.0.5.1
	 *
	 * @return string 24 lowercase hex characters.
	 */
	public static function nonce(): string {

		return bin2hex( random_bytes( self::NONCE_BYTES ) );
	}

	/**
	 * Get the associated data a request is sealed under.
	 *
	 * @since 2.0.5.1
	 *
	 * @param string $endpoint  Route below the wizard's REST base.
	 * @param string $timestamp Request timestamp.
	 * @param string $nonce     Request nonce.
	 *
	 * @return string
	 */
	public static function request_aad( string $endpoint, string $timestamp, string $nonce ): string {

		return implode( "\n", [ self::LABEL, 'REQUEST', 'POST', $endpoint, $timestamp, $nonce ] );
	}

	/**
	 * Get the associated data a response is sealed under.
	 *
	 * @since 2.0.5.1
	 *
	 * @param string $endpoint       Route below the wizard's REST base.
	 * @param string $request_nonce  Nonce of the request being answered.
	 * @param string $response_nonce Nonce of the response.
	 *
	 * @return string
	 */
	public static function response_aad( string $endpoint, string $request_nonce, string $response_nonce ): string {

		return implode( "\n", [ self::LABEL, 'RESPONSE', $endpoint, $request_nonce, $response_nonce ] );
	}

	/**
	 * Whether this host can seal and open bodies.
	 *
	 * Neither WordPress nor the plugin requires the OpenSSL extension.
	 *
	 * @since 2.0.5.1
	 *
	 * @return bool
	 */
	public static function is_supported(): bool {

		static $supported = null;

		if ( $supported === null ) {
			$supported = function_exists( 'openssl_encrypt' )
				&& in_array( self::ALGORITHM, array_map( 'strtolower', openssl_get_cipher_methods() ), true );
		}

		return $supported;
	}

	/**
	 * Seal a plaintext.
	 *
	 * @since 2.0.5.1
	 *
	 * @param string $wire_key  Wire key.
	 * @param string $plaintext Plaintext.
	 * @param string $aad       Associated data.
	 * @param string $nonce     Nonce.
	 *
	 * @return string Base64 of the ciphertext followed by the tag, or an empty string on failure.
	 */
	public static function seal( string $wire_key, string $plaintext, string $aad, string $nonce ): string {

		$iv = self::iv( $nonce );

		if ( $iv === '' || ! self::is_supported() ) {
			return '';
		}

		$tag        = '';
		$ciphertext = openssl_encrypt( $plaintext, self::ALGORITHM, self::aead_key( $wire_key ), OPENSSL_RAW_DATA, $iv, $tag, $aad, self::TAG_LENGTH );

		return $ciphertext === false ? '' : base64_encode( $ciphertext . $tag ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Open a sealed body.
	 *
	 * @since 2.0.5.1
	 *
	 * @param string $wire_key Wire key.
	 * @param string $sealed   Base64 of the ciphertext followed by the tag.
	 * @param string $aad      Associated data.
	 * @param string $nonce    Nonce.
	 *
	 * @return string The plaintext, or an empty string when the body does not authenticate. Every
	 *                sealed body is JSON, so callers must never treat an empty string as a body.
	 */
	public static function open( string $wire_key, string $sealed, string $aad, string $nonce ): string {

		$iv  = self::iv( $nonce );
		$raw = base64_decode( $sealed, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( $iv === '' || $raw === false || strlen( $raw ) < self::TAG_LENGTH || ! self::is_supported() ) {
			return '';
		}

		$plaintext = openssl_decrypt(
			substr( $raw, 0, - self::TAG_LENGTH ),
			self::ALGORITHM,
			self::aead_key( $wire_key ),
			OPENSSL_RAW_DATA,
			$iv,
			substr( $raw, - self::TAG_LENGTH ),
			$aad
		);

		return $plaintext === false ? '' : $plaintext;
	}

	/**
	 * Get the IV bytes for a nonce.
	 *
	 * @since 2.0.5.1
	 *
	 * @param string $nonce Nonce.
	 *
	 * @return string The IV, or an empty string when the nonce is not 24 lowercase hex characters.
	 */
	private static function iv( string $nonce ): string {

		if ( ! preg_match( '/^[0-9a-f]{24}$/', $nonce ) ) {
			return '';
		}

		return (string) hex2bin( $nonce );
	}
}
