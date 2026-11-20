<?php

namespace WPForms\Integrations\UsageTracking;

use WPForms\Integrations\Abilities\Abilities;
use WPForms\Integrations\IntegrationInterface;

/**
 * Monthly counters of WPForms Abilities API calls for usage tracking.
 *
 * Counts calls per UTC month, ability, channel and client bucket in one non-autoloaded
 * option written at most once per request, or once per MCP call in a long-running server.
 * No raw User-Agent, application password name, ability input or output is stored.
 *
 * @since 2.0.5
 */
class AbilitiesStats implements IntegrationInterface {

	/**
	 * Option holding the monthly counters.
	 *
	 * @since 2.0.5
	 */
	public const OPTION = 'wpforms_abilities_usage';

	/**
	 * Number of months kept in the option.
	 *
	 * @since 2.0.5
	 */
	private const MAX_MONTHS = 12;

	/**
	 * Number of most recent months whose per-ability detail is reported.
	 *
	 * @since 2.0.5
	 */
	private const DETAILED_MONTHS = 2;

	/**
	 * Prefix of the ability names that are counted.
	 *
	 * @since 2.0.5
	 */
	private const NAMESPACE_PREFIX = Abilities::ABILITY_NAMESPACE . '/';

	/**
	 * User-Agent keywords whose client bucket is named after the keyword itself.
	 *
	 * @since 2.0.5
	 */
	private const CLIENTS = [ 'claude', 'openai', 'cursor', 'gemini', 'wpvibe' ];

	/**
	 * MCP Adapter ability whose run wraps the ability an MCP client asked for.
	 *
	 * @since 2.0.5
	 */
	private const MCP_EXECUTE_ABILITY = 'mcp-adapter/execute-ability';

	/**
	 * Calls counted in this request and not saved yet, keyed by site ID and then by `Y_m` month.
	 *
	 * @since 2.0.5
	 *
	 * @var array
	 */
	private $counts = [];

	/**
	 * Cached usage tracking gate, keyed by site ID.
	 *
	 * @since 2.0.5
	 *
	 * @var array
	 */
	private $is_tracking_enabled = [];

	/**
	 * Whether the request carries a valid logged-in cookie, null until the first started call.
	 *
	 * @since 2.0.5
	 *
	 * @var bool|null
	 */
	private $has_logged_in_cookie;

	/**
	 * Number of MCP Adapter tool calls in progress, more than one when they nest.
	 *
	 * @since 2.0.5
	 *
	 * @var int
	 */
	private $mcp_tool_calls = 0;

	/**
	 * Whether the MCP Adapter ability that runs other abilities is in progress.
	 *
	 * @since 2.0.5
	 *
	 * @var bool
	 */
	private $is_in_mcp_execute_ability = false;

	/**
	 * Indicate if the current integration is allowed to load.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	public function allow_load(): bool {

		// Abilities exist only where the Abilities API does (WordPress 6.9+).
		return function_exists( 'wp_register_ability' );
	}

	/**
	 * Load the integration.
	 *
	 * @since 2.0.5
	 */
	public function load(): void {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.5
	 */
	private function hooks(): void {

		add_action( 'wp_ability_invoked', [ $this, 'count_invoked' ] );
		add_action( 'wp_before_execute_ability', [ $this, 'count_started' ] );
		add_action( 'wp_after_execute_ability', [ $this, 'count_ok' ] );
		// Last in and first out, so the marker spans the tool run alone and no other callback can leave it set.
		add_filter( 'mcp_adapter_pre_tool_call', [ $this, 'enter_mcp_tool_call' ], PHP_INT_MAX );
		add_filter( 'mcp_adapter_tool_call_result', [ $this, 'leave_mcp_tool_call' ], PHP_INT_MIN );
		add_action( 'shutdown', [ $this, 'save' ] );
	}

	/**
	 * Count an ability call before the permission check.
	 *
	 * @since 2.0.5
	 *
	 * @param string $ability_name Ability name.
	 */
	public function count_invoked( $ability_name ): void {

		$this->bump( (string) $ability_name, 'invoked' );
	}

	/**
	 * Count an ability call that passed validation and the permission check.
	 *
	 * @since 2.0.5
	 *
	 * @param string $ability_name Ability name.
	 */
	public function count_started( $ability_name ): void {

		// MCP Adapter versions before 0.5.0 have no tool call filters, so this ability marks their calls.
		if ( $ability_name === self::MCP_EXECUTE_ABILITY ) {
			$this->is_in_mcp_execute_ability = true;

			return;
		}

		$this->bump( (string) $ability_name, 'started' );
	}

	/**
	 * Count an ability call that returned a valid result.
	 *
	 * @since 2.0.5
	 *
	 * @param string $ability_name Ability name.
	 */
	public function count_ok( $ability_name ): void {

		if ( $ability_name === self::MCP_EXECUTE_ABILITY ) {
			$this->finish_mcp_call();

			return;
		}

		$this->bump( (string) $ability_name, 'ok' );
	}

	/**
	 * Mark the start of an MCP Adapter tool call.
	 *
	 * @since 2.0.5
	 *
	 * @param mixed $args Tool call arguments, or a WP_Error when another callback blocked the call.
	 *
	 * @return mixed
	 */
	public function enter_mcp_tool_call( $args ) {

		// A blocked call runs no tool and never reaches the result filter that ends it.
		if ( ! is_wp_error( $args ) ) {
			++$this->mcp_tool_calls;
		}

		return $args;
	}

	/**
	 * Mark the end of an MCP Adapter tool call.
	 *
	 * @since 2.0.5
	 *
	 * @param mixed $result Tool call result.
	 *
	 * @return mixed
	 */
	public function leave_mcp_tool_call( $result ) {

		$this->mcp_tool_calls = max( 0, $this->mcp_tool_calls - 1 );

		$this->finish_mcp_call();

		return $result;
	}

	/**
	 * Save once the outermost MCP call ends, so a long-running server that gets killed keeps its counts.
	 *
	 * @since 2.0.5
	 */
	private function finish_mcp_call(): void {

		if ( $this->mcp_tool_calls > 0 ) {
			return;
		}

		$this->is_in_mcp_execute_ability = false;

		$this->save();
	}

	/**
	 * Write the counted calls, at shutdown or after an MCP call.
	 *
	 * @since 2.0.5
	 */
	public function save(): void {

		$counts       = $this->counts;
		$this->counts = [];

		foreach ( $counts as $blog_id => $site_counts ) {
			$this->save_site_counts( (int) $blog_id, $site_counts );
		}
	}

	/**
	 * Add the calls counted on one site to that site's stored counters.
	 *
	 * @since 2.0.5
	 *
	 * @param int   $blog_id Site ID.
	 * @param array $counts  Calls counted on the site, keyed by `Y_m` month.
	 */
	private function save_site_counts( int $blog_id, array $counts ): void {

		// A call made inside switch_to_blog() belongs to that site, not to the one current when saving.
		$is_switched = is_multisite() && $blog_id !== get_current_blog_id();

		if ( $is_switched ) {
			switch_to_blog( $blog_id );
		}

		// A WP-CLI process can outlive a settings change, so it drops its own in-memory cache and asks again.
		if ( wpforms_doing_wp_cli() && wp_cache_supports( 'flush_runtime' ) ) {
			wp_cache_flush_runtime();

			unset( $this->is_tracking_enabled[ $blog_id ] );
		}

		if ( $this->is_tracking_enabled() ) {
			// Read now, past this process's cached copy, so what other requests saved since the last read is kept.
			wp_cache_delete( self::OPTION, 'options' );

			$stored = $this->add_counts( $this->get_stored(), $counts );

			krsort( $stored, SORT_STRING );

			// Non-atomic read-modify-write: concurrent requests may lose an increment, which is acceptable for telemetry.
			update_option( self::OPTION, array_slice( $stored, 0, self::MAX_MONTHS, true ), false );
		}

		if ( $is_switched ) {
			restore_current_blog();
		}
	}

	/**
	 * Get the stored counters without a damaged option or damaged month entries.
	 *
	 * @since 2.0.5
	 *
	 * @return array
	 */
	private function get_stored(): array {

		$stored = get_option( self::OPTION, [] );

		return is_array( $stored ) ? array_filter( $stored, 'is_array' ) : [];
	}

	/**
	 * Add counted calls to the stored counters.
	 *
	 * @since 2.0.5
	 *
	 * @param array $stored Stored counters.
	 * @param array $counts Counted calls, in the same shape.
	 *
	 * @return array
	 */
	private function add_counts( array $stored, array $counts ): array {

		foreach ( $counts as $key => $count ) {
			$value = $stored[ $key ] ?? null;

			// A stored value of the wrong type is replaced, so a damaged option cannot break the write.
			if ( is_array( $count ) ) {
				$stored[ $key ] = $this->add_counts( is_array( $value ) ? $value : [], $count );

				continue;
			}

			$stored[ $key ] = ( is_numeric( $value ) ? (int) $value : 0 ) + $count;
		}

		return $stored;
	}

	/**
	 * Get the stored counters in the usage tracking shape, newest month first.
	 *
	 * @since 2.0.5
	 *
	 * @return array
	 */
	public function get_report(): array {

		$data = $this->get_stored();

		krsort( $data, SORT_STRING );

		$now      = time();
		$detailed = [];

		// A month below 1 rolls back into the previous year.
		for ( $offset = 0; $offset < self::DETAILED_MONTHS; $offset++ ) {
			$detailed[] = gmdate( 'Y_m', gmmktime( 0, 0, 0, (int) gmdate( 'n', $now ) - $offset, 1, (int) gmdate( 'Y', $now ) ) );
		}

		foreach ( array_keys( $data ) as $month ) {
			if ( ! in_array( (string) $month, $detailed, true ) ) {
				unset( $data[ $month ]['abilities'] );
			}
		}

		return $data;
	}

	/**
	 * Increment one counter of a WPForms ability call.
	 *
	 * @since 2.0.5
	 *
	 * @param string $ability_name Ability name.
	 * @param string $counter      Counter name: `invoked`, `started` or `ok`.
	 */
	private function bump( string $ability_name, string $counter ): void {

		if ( strpos( $ability_name, self::NAMESPACE_PREFIX ) !== 0 || ! $this->is_tracking_enabled() ) {
			return;
		}

		$blog_id = get_current_blog_id();
		$month   = gmdate( 'Y_m', time() );
		$channel = $this->get_channel();

		if ( ! isset( $this->counts[ $blog_id ][ $month ] ) ) {
			$this->counts[ $blog_id ][ $month ] = [
				'total'     => 0,
				'abilities' => [],
				'clients'   => [],
			];
		}

		$stats = &$this->counts[ $blog_id ][ $month ];

		$stats['abilities'][ $ability_name ][ $channel ][ $counter ] = ( $stats['abilities'][ $ability_name ][ $channel ][ $counter ] ?? 0 ) + 1;

		if ( $counter === 'started' ) {
			$client = $this->get_client();

			$stats['total']              = ( $stats['total'] ?? 0 ) + 1;
			$stats['clients'][ $client ] = ( $stats['clients'][ $client ] ?? 0 ) + 1;
		}

		unset( $stats );
	}

	/**
	 * Whether usage tracking is enabled on the current site.
	 *
	 * Cached until the next save under WP-CLI and for the request otherwise.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	private function is_tracking_enabled(): bool {

		$blog_id = get_current_blog_id();

		if ( ! isset( $this->is_tracking_enabled[ $blog_id ] ) ) {
			$usage_tracking = wpforms()->obj( 'UsageTracking\UsageTracking' );

			$this->is_tracking_enabled[ $blog_id ] = $usage_tracking instanceof UsageTracking && $usage_tracking->allow_load() && $usage_tracking->is_enabled();
		}

		return $this->is_tracking_enabled[ $blog_id ];
	}

	/**
	 * Get the channel of the current ability call.
	 *
	 * @since 2.0.5
	 *
	 * @return string
	 */
	private function get_channel(): string {

		if ( $this->mcp_tool_calls > 0 || $this->is_in_mcp_execute_ability ) {
			return 'mcp';
		}

		if ( ! isset( $GLOBALS['wp'] ) || ! is_object( $GLOBALS['wp'] ) ) {
			return 'other';
		}

		$rest_route = ltrim( (string) ( $GLOBALS['wp']->query_vars['rest_route'] ?? '' ), '/' );

		// Core matches REST routes without regard to letter case.
		return stripos( $rest_route, 'wp-abilities/v1/' ) === 0 ? 'rest' : 'other';
	}

	/**
	 * Get the client bucket of the current ability call.
	 *
	 * @since 2.0.5
	 *
	 * @return string
	 */
	private function get_client(): string {

		// Core validates the logged-in cookie itself, so a forged one beside an application password does not count.
		// Asked once per request: every validation fires core's auth cookie actions again.
		if ( $this->has_logged_in_cookie === null ) {
			$this->has_logged_in_cookie = (bool) wp_validate_auth_cookie( '', 'logged_in' );
		}

		if ( $this->has_logged_in_cookie ) {
			return 'browser';
		}

		// The User-Agent is only matched against known keywords and never stored.
		$user_agent = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) );

		foreach ( self::CLIENTS as $client ) {
			if ( strpos( $user_agent, $client ) !== false ) {
				return $client;
			}
		}

		if ( strpos( $user_agent, 'visual studio code' ) !== false ) {
			return 'vscode';
		}

		return wpforms_doing_wp_cli() ? 'cli' : 'other';
	}
}
