<?php

namespace WPForms\Integrations\Drip;

/**
 * The Drip addon after Drip moved into core.
 *
 * The requirements table stops the addon from loading on a core that bundles Drip, which is what
 * keeps a submission from reaching Drip twice; this class explains that in the notice the
 * requirements check shows, and asks to delete the addon once the upgrade deactivated it.
 *
 * @since 2.0.5
 */
class RetiredAddon {

	/**
	 * Plugin basename of the Drip addon.
	 *
	 * @since 2.0.5
	 */
	const BASENAME = 'wpforms-drip/wpforms-drip.php';

	/**
	 * Option holding the time the upgrade deactivated the addon.
	 *
	 * @since 2.0.5
	 */
	const DEACTIVATED_OPTION = 'wpforms_drip_addon_deactivated';

	/**
	 * Core release that bundles Drip; the requirements table keeps the addon below it.
	 *
	 * @since 2.0.5
	 */
	const BUNDLED_SINCE = '2.0.5';

	/**
	 * Register hooks.
	 *
	 * @since 2.0.5
	 */
	public function hooks() {

		add_filter( 'wpforms_requirements_notice', [ $this, 'filter_requirements_notice' ], 10, 3 );
		add_action( 'admin_notices', [ $this, 'show_deactivated_notice' ] );
		add_action( 'network_admin_notices', [ $this, 'show_deactivated_notice' ] );
	}

	/**
	 * Ask to delete the addon the upgrade deactivated, until its files are gone.
	 *
	 * The upgrade deactivates the addon in whatever request runs first on the new core, often one
	 * without admin notices (Heartbeat, cron), so the notice cannot rely on that request. It stays
	 * out of a request that loaded the addon: the requirements check prints the notice there, for
	 * an addon activated again by hand as well as for one the upgrade deactivated moments ago.
	 *
	 * @since 2.0.5
	 */
	public function show_deactivated_notice() {

		if ( ! get_option( self::DEACTIVATED_OPTION ) ) {
			return;
		}

		if ( ! $this->is_addon_installed() ) {
			delete_option( self::DEACTIVATED_OPTION );

			return;
		}

		if ( $this->is_addon_loaded() || ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo wp_kses_post( $this->get_notice( false ) );
		echo '</p></div>';
	}

	/**
	 * Replace the generic "requires WPForms below 2.x" notice of the addon with the reason behind it.
	 *
	 * @since 2.0.5
	 *
	 * @param string|mixed $notice   Notice.
	 * @param array        $errors   Validation errors.
	 * @param string       $basename Plugin basename.
	 *
	 * @return string
	 */
	public function filter_requirements_notice( $notice, array $errors, string $basename ): string {

		if ( $basename !== self::BASENAME || ! in_array( 'wpforms', $errors, true ) ) {
			return (string) $notice;
		}

		return $this->get_notice( $this->is_addon_active() );
	}

	/**
	 * Build the notice for the state of the addon.
	 *
	 * @since 2.0.5
	 *
	 * @param bool $is_active Whether the addon is active: activated again by hand after the upgrade.
	 *
	 * @return string
	 *
	 * @noinspection HtmlUnknownTarget
	 */
	private function get_notice( bool $is_active ): string {

		if ( $is_active ) {
			/* translators: %1$s - WPForms version that includes Drip, %2$s - documentation URL. */
			$message = __( 'The Drip addon is outdated and must be deactivated: Drip is built into WPForms %1$s now. Please deactivate and delete the addon, your Drip connections keep working. <a href="%2$s" target="_blank" rel="noopener noreferrer">Learn more</a>', 'wpforms-lite' );
		} else {
			/* translators: %1$s - WPForms version that includes Drip, %2$s - documentation URL. */
			$message = __( 'The Drip addon was deactivated: Drip is built into WPForms %1$s now. Please delete the addon, your Drip connections keep working. <a href="%2$s" target="_blank" rel="noopener noreferrer">Learn more</a>', 'wpforms-lite' );
		}

		return sprintf(
			wp_kses(
				$message,
				[
					'a' => [
						'href'   => [],
						'target' => [],
						'rel'    => [],
					],
				]
			),
			self::BUNDLED_SINCE,
			esc_url( wpforms_utm_link( 'https://wpforms.com/docs/how-to-install-and-use-the-drip-addon-in-wpforms/', 'all-plugins', 'Drip Addon Notice' ) )
		);
	}

	/**
	 * Whether the Drip addon files are still on the site.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	protected function is_addon_installed(): bool {

		return file_exists( WP_PLUGIN_DIR . '/' . self::BASENAME );
	}

	/**
	 * Whether the Drip addon's main file loaded in this request: it defines the load function unconditionally.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	protected function is_addon_loaded(): bool {

		return function_exists( 'wpforms_drip_load' );
	}

	/**
	 * Whether the Drip addon is active when the notice prints.
	 *
	 * The requirements check only runs when the addon loaded, so an addon that is inactive by the time
	 * its requirements notice prints was deactivated during this request, by the upgrade routine that
	 * ran before the check. An active one was activated again by hand.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	private function is_addon_active(): bool {

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( self::BASENAME );
	}
}
