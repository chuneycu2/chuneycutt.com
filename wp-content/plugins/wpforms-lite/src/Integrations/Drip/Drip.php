<?php

namespace WPForms\Integrations\Drip;

use WPForms\Integrations\IntegrationInterface;
use WPForms\Providers\Providers;

/**
 * Drip marketing integration, available on every license.
 *
 * @since 2.0.5
 */
class Drip implements IntegrationInterface {

	/**
	 * Whether the integration is allowed to load.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	public function allow_load(): bool {

		/**
		 * Whether the Drip integration is allowed to load.
		 *
		 * @since 2.0.5
		 *
		 * @param bool $is_allowed Integration loading state.
		 */
		return (bool) apply_filters( 'wpforms_integrations_drip_allow_load', true );
	}

	/**
	 * Load the integration.
	 *
	 * @since 2.0.5
	 */
	public function load() {

		Providers::get_instance()->register( Core::get_instance() );

		( new ProcessActionTask() )->hooks();
		( new RetiredAddon() )->hooks();
	}
}
