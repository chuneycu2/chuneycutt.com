<?php

namespace WPForms\Integrations\Drip\Settings;

use RuntimeException;
use WPForms\Integrations\Drip\Account;
use WPForms\Providers\Provider\Settings\PageIntegrations as PageIntegrationsAbstract;

/**
 * Drip on the Settings > Integrations page.
 *
 * @since 2.0.5
 */
class PageIntegrations extends PageIntegrationsAbstract {

	/**
	 * Connect a new account from the settings page.
	 *
	 * @since 2.0.5
	 */
	public function ajax_connect() {

		parent::ajax_connect();

		// The nonce and the capability were checked by the parent.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = wp_parse_args( wp_unslash( $_POST['data'] ?? '' ) );

		try {
			$key = ( new Account() )->connect(
				(string) ( $data['api_token'] ?? '' ),
				(string) ( $data['account_name'] ?? '' )
			);
		} catch ( RuntimeException $e ) {
			wp_send_json_error( [ 'error_msg' => $e->getMessage() ] );
		}

		$accounts = wpforms_get_providers_options( $this->core->slug );

		ob_start();
		$this->display_connected_account( $key, (array) ( $accounts[ $key ] ?? [] ) );

		wp_send_json_success( [ 'html' => ob_get_clean() ] );
	}

	/**
	 * Fields that collect the new account details.
	 *
	 * @since 2.0.5
	 */
	protected function display_add_new_connection_fields() {

		echo Account::get_fields_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
