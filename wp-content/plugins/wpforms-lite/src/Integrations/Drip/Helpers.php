<?php

namespace WPForms\Integrations\Drip;

/**
 * Drip integration helpers.
 *
 * @since 2.0.5
 */
class Helpers {

	/**
	 * Sanitize a custom field identifier the way Drip accepts it: latin letters, digits and
	 * underscores, with whitespace runs collapsed into one underscore.
	 *
	 * @since 2.0.5
	 *
	 * @param string $name Field name as typed in the builder.
	 *
	 * @return string
	 */
	public static function sanitize_field_id( string $name ): string {

		$name = preg_replace( '/[^A-Za-z0-9_ ]/', '', trim( $name ) );

		return preg_replace( '/\s+/', '_', $name );
	}

	/**
	 * Sanitize an API token: whitespace and quotes go, letter case stays.
	 *
	 * @since 2.0.5
	 *
	 * @param string $token Token as pasted by the user.
	 *
	 * @return string
	 */
	public static function sanitize_api_token( string $token ): string {

		return wpforms_sanitize_key( $token );
	}

	/**
	 * Get the Drip custom fields offered in the builder before the user types their own.
	 *
	 * @since 2.0.5
	 *
	 * @return array Field identifier => label.
	 */
	public static function get_predefined_custom_fields(): array {

		$fields = [
			'first_name' => __( 'First name', 'wpforms-lite' ),
			'last_name'  => __( 'Last name', 'wpforms-lite' ),
			'address1'   => __( 'Address line 1', 'wpforms-lite' ),
			'address2'   => __( 'Address line 2', 'wpforms-lite' ),
			'city'       => __( 'City', 'wpforms-lite' ),
			'state'      => __( 'State', 'wpforms-lite' ),
			'zip'        => __( 'ZIP', 'wpforms-lite' ),
			'country'    => __( 'Country', 'wpforms-lite' ),
			'phone'      => __( 'Phone', 'wpforms-lite' ),
			'sms_number' => __( 'SMS number', 'wpforms-lite' ),
		];

		/**
		 * Filter the predefined Drip custom fields.
		 *
		 * @since 2.0.5
		 *
		 * @param array $fields Field identifier => label.
		 */
		return (array) apply_filters( 'wpforms_drip_provider_form_builder_get_custom_fields', $fields ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- Preserved from the Drip addon for backward compatibility.
	}
}
