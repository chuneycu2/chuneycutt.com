<?php
/**
 * Drip action block: delete a subscriber.
 *
 * @since 2.0.5
 *
 * @var string $email_field_html Subscriber Email select, built by wpforms_panel_field().
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpforms-builder-provider-connection-block wpforms-builder-drip-provider-subscriber-delete">
	<h3><?php esc_html_e( 'Delete a Subscriber', 'wpforms-lite' ); ?></h3>

	<div class="wpforms-builder-provider-connection-setting">
		<p class="description before">
			<?php esc_html_e( 'Sometimes you may want to give your users the option to remove themselves from your subscribers using your own form.', 'wpforms-lite' ); ?><br>
			<?php esc_html_e( 'This action is irreversible, you will lose all the data in Drip about the subscriber.', 'wpforms-lite' ); ?>
		</p>

		<?php echo $email_field_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</div>
