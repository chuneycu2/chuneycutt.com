<?php
/**
 * Drip action block: unsubscribe a subscriber from all mailings.
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
<div class="wpforms-builder-provider-connection-block wpforms-builder-drip-provider-subscriber-unsubscribe">
	<h3><?php esc_html_e( 'Unsubscribe Subscriber', 'wpforms-lite' ); ?></h3>

	<div class="wpforms-builder-provider-connection-setting">
		<?php echo $email_field_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</div>
