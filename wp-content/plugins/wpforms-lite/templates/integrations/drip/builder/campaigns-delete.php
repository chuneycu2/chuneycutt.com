<?php
/**
 * Drip action block: unsubscribe from one or all Email Series campaigns.
 *
 * @since 2.0.5
 *
 * @var string $slug             Provider slug.
 * @var string $email_field_html Subscriber Email select, built by wpforms_panel_field().
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpforms-builder-provider-connection-block wpforms-builder-drip-provider-campaigns">
	<h3><?php esc_html_e( 'Unsubscribe From Campaign(s)', 'wpforms-lite' ); ?></h3>

	<div class="wpforms-builder-provider-connection-setting wpforms-builder-drip-provider-campaigns-campaign">
		<p class="description before">
			<?php esc_html_e( 'Sometimes you may want to give your users the option to unsubscribe themselves from your campaign(s) using your own form.', 'wpforms-lite' ); ?>
		</p>

		<?php
		echo wpforms_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'integrations/drip/builder/partials/campaign-select',
			[
				'slug'               => $slug,
				'all_option'         => true,
				'empty_message_html' => esc_html__( 'You have no campaigns yet. Consider creating at least one.', 'wpforms-lite' ),
			],
			true
		);
		?>
	</div>

	<div class="wpforms-builder-provider-connection-setting">
		<?php echo $email_field_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</div>
