<?php

namespace WPForms\SetupWizard;

use WP_Error;
use WPForms\SetupChecklist\Page;
use WPForms\SetupWizard\Service\StateManager;

/**
 * Setup Wizard launch screen.
 *
 * The seam `SetupWizard::maybe_launch()` delegates the launch tail to. The
 * base implementation starts a hosted wizard session and either redirects to
 * the Welcome fallback or renders the auto-POST bridge.
 * The Lite override renders the local Welcome screen instead (the wizard's
 * first screen must live on the WP site, see #18533).
 *
 * @since 2.0.1
 */
class Screen {

	/**
	 * Bridge service.
	 *
	 * @since 2.0.1
	 *
	 * @var Bridge
	 */
	protected $bridge;

	/**
	 * State manager service (edition-resolved).
	 *
	 * Unused by the base flow; stored because the constructor is shared with
	 * the Lite override, which persists the Welcome consent through it.
	 *
	 * @since 2.0.1
	 *
	 * @var StateManager
	 */
	protected $state_manager;

	/**
	 * Constructor.
	 *
	 * @since 2.0.1
	 *
	 * @param Bridge       $bridge        Bridge service.
	 * @param StateManager $state_manager State manager service.
	 */
	public function __construct( Bridge $bridge, StateManager $state_manager ) {

		$this->bridge        = $bridge;
		$this->state_manager = $state_manager;
	}

	/**
	 * Register hooks.
	 *
	 * The base flow has none; the Lite override registers its AJAX endpoint.
	 *
	 * @since 2.0.1
	 */
	public function hooks(): void {
	}

	/**
	 * Launch the wizard for the current request.
	 *
	 * Never exits; `SetupWizard::maybe_launch()` exits after this returns.
	 *
	 * @since 2.0.1
	 *
	 * @return bool Whether a real launch happened (as opposed to a fallback redirect).
	 */
	public function launch(): bool {

		// A top-level POST abandons the bridge page, so a wizard that cannot start a
		// session has to be caught here and the user sent to the Welcome fallback.
		$ticket = $this->bridge->request_session( $this->get_exit_url(), $this->get_restart_url() );

		if ( is_wp_error( $ticket ) ) {
			$this->log_session_failure( $ticket );

			wp_safe_redirect( $this->get_fallback_url() );

			return false;
		}

		$this->bridge->render( $ticket );

		return true;
	}

	/**
	 * Log why the hosted wizard session could not be started.
	 *
	 * @since 2.0.5.1
	 *
	 * @param WP_Error $error Error returned by the session request.
	 */
	protected function log_session_failure( WP_Error $error ): void {

		// A site owner forcing the local wizard through the filter is a choice, not a failure.
		if ( $error->get_error_code() === 'wpforms_setup_wizard_forced_unreachable' ) {
			return;
		}

		wpforms_log(
			'Setup Wizard: session start failed',
			[
				'code'    => $error->get_error_code(),
				'message' => $error->get_error_message(),
				'data'    => $error->get_error_data(),
			],
			[ 'type' => [ 'error' ] ]
		);
	}

	/**
	 * Whether the current launch is a mid-wizard re-entry rather than a first launch.
	 *
	 * The Stripe OAuth round-trip returns the user to wp-admin with the manual
	 * launch argument and a forced-step transient; such a launch must resume
	 * in the SPA, not restart at the first screen.
	 *
	 * @since 2.0.1
	 *
	 * @return bool
	 */
	protected function is_resume_request(): bool {

		$is_resume_request = get_transient( StripeConnect::TRANSIENT_FORCE_STEP ) !== false;

		/**
		 * Filter whether the current launch is a mid-wizard re-entry rather than a first launch.
		 *
		 * Allows other OAuth-style round-trips to signal a resume without editing this shared class.
		 *
		 * @since 2.0.1
		 *
		 * @param bool $is_resume_request Whether the current launch should resume in the SPA.
		 */
		return (bool) apply_filters( 'wpforms_setup_wizard_screen_is_resume_request', $is_resume_request );
	}

	/**
	 * Get the URL the wizard should return the user to on exit.
	 *
	 * @since 2.0.1
	 *
	 * @return string
	 */
	protected function get_exit_url(): string {

		return Page::get_url();
	}

	/**
	 * Get the URL the wizard should restart from.
	 *
	 * @since 2.0.1
	 *
	 * @return string
	 */
	protected function get_restart_url(): string {

		return add_query_arg( SetupWizard::QUERY_ARG, 1, $this->get_exit_url() );
	}

	/**
	 * Get the Welcome getting-started URL used as the launch fallback.
	 *
	 * @since 2.0.1
	 *
	 * @return string
	 */
	private function get_fallback_url(): string {

		return admin_url( 'index.php?page=wpforms-getting-started' );
	}
}
