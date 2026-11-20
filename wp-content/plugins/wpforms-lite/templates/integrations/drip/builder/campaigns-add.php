<?php
/**
 * Drip action block: subscribe to an Email Series campaign.
 *
 * @since 2.0.5
 *
 * @var string $slug Provider slug.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$empty_message_html = sprintf(
	wp_kses( /* translators: %s - URL to the Drip documentation article. */
		__( 'You have no campaigns yet. Consider creating at least one. Here is <a href="%s" rel="noopener noreferrer" target="_blank">how to do it</a>.', 'wpforms-lite' ),
		[
			'a' => [
				'href'   => [],
				'rel'    => [],
				'target' => [],
			],
		]
	),
	esc_url( wpforms_utm_link( 'https://wpforms.com/docs/how-to-install-and-use-the-drip-addon-in-wpforms/#create-campaign', 'Marketing Integrations', 'Drip Documentation' ) )
);
?>
<div class="wpforms-builder-provider-connection-block wpforms-builder-drip-provider-campaigns">
	<label for="wpforms-builder-drip-provider-%connection_id%-campaigns-campaign"><?php esc_html_e( 'Select Campaign', 'wpforms-lite' ); ?><span class="required">*</span></label>

	<div class="wpforms-builder-provider-connection-setting wpforms-builder-drip-provider-campaigns-campaign">
		<?php
		echo wpforms_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'integrations/drip/builder/partials/campaign-select',
			[
				'slug'               => $slug,
				'all_option'         => false,
				'empty_message_html' => $empty_message_html,
			],
			true
		);
		?>
	</div>
</div>
