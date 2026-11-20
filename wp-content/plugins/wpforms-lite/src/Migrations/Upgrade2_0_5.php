<?php

namespace WPForms\Migrations;

use WPForms\Integrations\Drip\RetiredAddon;

/**
 * Class upgrade for 2.0.5 release.
 *
 * @since 2.0.5
 *
 * @noinspection PhpUnused
 */
class Upgrade2_0_5 extends UpgradeBase {

	/**
	 * Run upgrade.
	 *
	 * @since 2.0.5
	 *
	 * @return bool Always true: the upgrade completes in this request.
	 */
	public function run() {

		// Drip is built into core from this release; left active, the addon would only show a requirements notice.
		if ( ! $this->is_addon_active() ) {
			return true;
		}

		// Right away, not on admin_init: the first request on the new core can be WP-CLI, cron, or an
		// admin URL that redirects to the login page before admin_init, and the migration runs once.
		$this->deactivate_addon();

		return true;
	}

	/**
	 * Whether the Drip addon is active: its main file defines the load function unconditionally.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	protected function is_addon_active(): bool {

		return function_exists( 'wpforms_drip_load' );
	}

	/**
	 * Deactivate the Drip addon and record it, so the notice asking to delete the addon outlives this request.
	 *
	 * @since 2.0.5
	 */
	private function deactivate_addon() {

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		deactivate_plugins( RetiredAddon::BASENAME );
		update_option( RetiredAddon::DEACTIVATED_OPTION, time() );
	}
}
