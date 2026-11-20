<?php
/**
 * Drip campaign select with its empty-account message, shared by the campaign subscribe and unsubscribe blocks.
 *
 * @since 2.0.5
 *
 * @var string $slug               Provider slug.
 * @var bool   $all_option         Whether to append the "All Campaigns" option.
 * @var string $empty_message_html Message shown when the account has no campaigns, escaped by the caller.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<select class="wpforms-required js-wpforms-builder-drip-provider-connection-campaign"
	id="wpforms-builder-drip-provider-%connection_id%-campaigns-campaign"
	<# if ( _.isEmpty( data.campaigns ) ) { #>disabled<# } #>
	name="providers[<?php echo esc_attr( $slug ); ?>][{{ data.connection.id }}][campaign_id]">

	<option value=""><?php esc_html_e( '--- Select Campaign ---', 'wpforms-lite' ); ?></option>

	<# _.each( data.campaigns, function( campaign ) { #>
		<option value="{{ campaign.id }}"<# if ( String( campaign.id ) === String( data.connection.campaign_id ) ) { #> selected<# } #>>
			{{ campaign.name }} ({{ campaign.status }})
		</option>
	<# } ); #>

	<?php if ( $all_option ) : ?>
		<option value="all"<# if ( data.connection.campaign_id === 'all' ) { #> selected<# } #>><?php esc_html_e( 'All Campaigns', 'wpforms-lite' ); ?></option>
	<?php endif; ?>
</select>

<# if ( _.isEmpty( data.campaigns ) ) { #>
	<p class="description error-message">
		<?php echo $empty_message_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</p>
<# } #>
