<?php
/**
 * Helpers functions for the Education pages.
 *
 * @since 1.8.2.2
 */

/**
 * Get the button.
 *
 * @since 1.8.2.2
 * @since 1.9.6.1 Add $license_level parameter.
 *
 * @param string $action        Action to perform.
 * @param bool   $plugin_allow  Is plugin allowed.
 * @param string $path          Plugin file.
 * @param string $url           URL for download plugin.
 * @param array  $utm           UTM parameters.
 * @param string $license_level License level.
 */
function wpforms_edu_get_button( $action, $plugin_allow, $path, $url, $utm, $license_level = '' ) {

	// If the user is not allowed to use the plugin, show the upgrade button.
	if ( ! $plugin_allow ) {
		wpforms_edu_get_upgrade_button( $utm, [], $license_level );

		return;
	}

	$status      = 'inactive';
	$data_plugin = $path;
	$title       = esc_html__( 'Activate', 'wpforms-lite' );
	$can_install = wpforms_can_install( 'addon' );

	if ( $action === 'install' ) {
		$status      = 'download';
		$data_plugin = $url;
		$title       = esc_html__( 'Install & Activate', 'wpforms-lite' );
	}

	?>

	<?php if ( $action === 'install' && ! $can_install ) : ?>
		<div class="wpforms-notice wpforms-error">
			<p><?php esc_html_e( 'Plugin installation is disabled for this site.', 'wpforms-lite' ); ?></p>
		</div>
	<?php else : ?>
		<button
			class="status-<?php echo esc_attr( $status ); ?> wpforms-btn wpforms-btn-lg wpforms-btn-blue wpforms-education-toggle-plugin-btn"
			data-type="addon"
			data-action="<?php echo esc_attr( $action ); ?>"
			data-plugin="<?php echo esc_attr( $data_plugin ); ?>">
			<i></i><?php echo esc_html( $title ); ?>
	<?php endif; ?>
	<?php
}

/**
 * Get the upgrade button.
 *
 * @since 1.8.2.2
 * @since 1.9.6.1 Add $license_level parameter.
 *
 * @param array  $utm           UTM parameters.
 * @param array  $classes       Classes.
 * @param string $license_level License level.
 */
function wpforms_edu_get_upgrade_button( $utm, $classes = [], $license_level = '' ) {

	$utm_medium  = isset( $utm['medium'] ) ? $utm['medium'] : '';
	$utm_content = isset( $utm['content'] ) ? $utm['content'] : '';

	$default_classes   = [ 'wpforms-btn', 'wpforms-btn-lg', 'wpforms-btn-orange' ];
	$default_classes[] = ! wpforms()->is_pro() ? 'wpforms-upgrade-modal' : '';

	$btn_classes = array_merge( $default_classes, (array) $classes );

	$upgrade_button_label = esc_html__( 'Upgrade to WPForms Pro', 'wpforms-lite' );

	if ( ! empty( $license_level ) && is_string( $license_level ) ) {
		$upgrade_button_label = sprintf(
			/* translators: %s: License name. */
			esc_html__( 'Upgrade to WPForms %s', 'wpforms-lite' ),
			esc_html( ucfirst( $license_level ) )
		);
	}
	?>
	<a
		href="<?php echo esc_url( wpforms_admin_upgrade_link( $utm_medium, $utm_content ) ); ?>"
		target="_blank"
		rel="noopener noreferrer"
		class="<?php echo esc_attr( implode( ' ', array_filter( $btn_classes ) ) ); ?>">
		<?php echo esc_html( $upgrade_button_label ); ?>
	</a>
	<?php
}

/**
 * Upgrade a link used within the various admin pages.
 *
 * Previously was only included as a method in wpforms-lite.php, but made
 * available globally in 1.3.9.
 *
 * @since 1.3.9
 * @since 2.0.5 Moved from `includes/admin/admin.php`, so the front-end themes editor can use it.
 *
 * @param string $medium  URL parameter: utm_medium.
 * @param string $content URL parameter: utm_content.
 *
 * @return string
 */
function wpforms_admin_upgrade_link( $medium = 'link', $content = '' ) {

	$url = 'https://wpforms.com/lite-upgrade/';

	if ( wpforms()->is_pro() ) {
		$license_key = wpforms_get_license_key();
		$url         = add_query_arg(
			'license_key',
			sanitize_text_field( $license_key ),
			'https://wpforms.com/pricing/'
		);
	}

	// phpcs:ignore WPForms.Comments.PHPDocHooks.RequiredHookDocumentation
	$upgrade = wpforms_utm_link( $url, apply_filters( 'wpforms_upgrade_link_medium', $medium ), $content );

	/**
	 * Modify upgrade link.
	 *
	 * @since 1.5.1
	 *
	 * @param string $upgrade Upgrade links.
	 */
	return apply_filters( 'wpforms_upgrade_link', $upgrade );
}

/**
 * Get an upgrade modal text.
 *
 * @since 1.4.4
 * @since 2.0.5 Moved from `includes/admin/admin.php`, so the front-end themes editor can use it.
 *
 * @param string $type Either "pro" or "elite". Default is "pro".
 *
 * @return string
 * @noinspection HtmlUnknownTarget
 */
function wpforms_get_upgrade_modal_text( $type = 'pro' ) {

	switch ( $type ) {
		case 'elite':
			$level = 'WPForms Elite';
			break;

		case 'pro':
		default:
			$level = 'WPForms Pro';
	}

	if ( wpforms()->is_pro() ) {
		return '<p>' .
			sprintf(
				wp_kses( /* translators: %s - WPForms.com contact page URL. */
					__( 'Thank you for considering upgrading. If you have any questions, please <a href="%s" target="_blank" rel="noopener noreferrer">let us know</a>.', 'wpforms-lite' ),
					[
						'a' => [
							'href'   => [],
							'target' => [],
							'rel'    => [],
						],
					]
				),
				esc_url(
					wpforms_utm_link(
						'https://wpforms.com/contact/',
						'Upgrade Follow Up Modal',
						'Contact Support'
					)
				)
			) .
			'</p>' .
			'<p>' .
			wp_kses(
				__( 'After upgrading, your license key will remain the same.<br>You may need to do a quick refresh to unlock your new addons. In your WordPress admin, go to <strong>WPForms &raquo; Settings</strong>. If you don\'t see your updated plan, click <em>refresh</em>.', 'wpforms-lite' ),
				[
					'strong' => [],
					'br'     => [],
					'em'     => [],
				]
			) .
			'</p>' .
			'<p>' .
			sprintf(
				wp_kses( /* translators: %s - WPForms.com upgrade from Lite to paid docs page URL. */
					__( 'Check out <a href="%s" target="_blank" rel="noopener noreferrer">our documentation</a> for step-by-step instructions.', 'wpforms-lite' ),
					[
						'a' => [
							'href'   => [],
							'target' => [],
							'rel'    => [],
						],
					]
				),
				esc_url( wpforms_utm_link( 'https://wpforms.com/docs/upgrade-wpforms-license/', 'Upgrade License Doc', 'Upgrade Now' ) )
			) .
			'</p>';
	}

	return '<p>' .
		sprintf(
			wp_kses( /* translators: %s - WPForms.com contact page URL. */
				__( 'If you have any questions or issues just <a href="%s" target="_blank" rel="noopener noreferrer">let us know</a>.', 'wpforms-lite' ),
				[
					'a' => [
						'href'   => [],
						'target' => [],
						'rel'    => [],
					],
				]
			),
			esc_url(
				wpforms_utm_link(
					'https://wpforms.com/contact/',
					'Upgrade Intention Alert',
					'Upgrade Intention Alert'
				)
			)
		) .
		'</p>' .
		'<p>' .
		sprintf(
			wp_kses( /* translators: %s - license level, WPForms Pro or WPForms Elite. */
				__( 'After purchasing a license, just <strong>enter your license key on the WPForms Settings page</strong>. This will let your site automatically upgrade to %s! (Don\'t worry, all your forms and settings will be preserved.)', 'wpforms-lite' ),
				[
					'strong' => [],
					'br'     => [],
				]
			),
			$level
		) .
		'</p>' .
		'<p>' .
		sprintf(
			wp_kses( /* translators: %s - WPForms.com upgrade from Lite to paid docs page URL. */
				__( 'Check out <a href="%s" target="_blank" rel="noopener noreferrer">our documentation</a> for step-by-step instructions.', 'wpforms-lite' ),
				[
					'a' => [
						'href'   => [],
						'target' => [],
						'rel'    => [],
					],
				]
			),
			esc_url(
				wpforms_utm_link(
					'https://wpforms.com/docs/upgrade-wpforms-lite-paid-license/',
					'Upgrade Intention Alert',
					'Upgrade Documentation'
				)
			)
		) .
		'</p>';
}
