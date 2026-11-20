<?php
/**
 * Drip connection: the account and action selects, with the action block rendered below them.
 *
 * @since 2.0.5
 *
 * @var string $slug Provider slug.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpforms-builder-provider-connection wpforms-builder-drip-provider-connection" data-connection_id="{{ data.connection.id }}">
	<input type="hidden" class="wpforms-builder-provider-connection-option_id"
		name="providers[<?php echo esc_attr( $slug ); ?>][{{ data.connection.id }}][option_id]"
		value="{{ data.connection.option_id }}">

	<div class="wpforms-builder-provider-connection-title">
		{{ data.connection.name }}
		<button class="wpforms-builder-provider-connection-delete js-wpforms-builder-provider-connection-delete" type="button">
			<i class="fa fa-trash-o"></i>
		</button>
		<input type="hidden"
			id="wpforms-builder-drip-provider-{{ data.connection.id }}-name"
			name="providers[<?php echo esc_attr( $slug ); ?>][{{ data.connection.id }}][name]"
			value="{{ data.connection.name }}">
	</div>

	<div class="wpforms-builder-provider-connection-block wpforms-builder-drip-provider-accounts">
		<label for="wpforms-builder-drip-provider-{{ data.connection.id }}-account"><?php esc_html_e( 'Select Account', 'wpforms-lite' ); ?><span class="required">*</span></label>

		<select class="js-wpforms-builder-drip-provider-connection-account wpforms-required"
			id="wpforms-builder-drip-provider-{{ data.connection.id }}-account"
			<# if ( _.isEmpty( data.accounts ) ) { #>disabled<# } #>
			name="providers[<?php echo esc_attr( $slug ); ?>][{{ data.connection.id }}][account_id]">

			<option value=""><?php esc_html_e( '--- Select Account ---', 'wpforms-lite' ); ?></option>

			<# _.each( data.accounts, function( accounts, optionId ) { #>
				<# _.each( accounts, function( account ) { #>
					<option value="{{ account.id }}" data-option_id="{{ optionId }}"<# if ( String( account.id ) === String( data.connection.account_id ) ) { #> selected<# } #>>
						{{ account.name }}
					</option>
				<# } ); #>
			<# } ); #>
		</select>
	</div>

	<# if ( ! _.isEmpty( data.accounts ) ) { #>
		<div class="wpforms-builder-provider-connection-block wpforms-builder-drip-provider-actions">
			<label for="wpforms-builder-drip-provider-{{ data.connection.id }}-action"><?php esc_html_e( 'Action To Perform', 'wpforms-lite' ); ?><span class="required">*</span></label>

			<select class="wpforms-required js-wpforms-builder-drip-provider-connection-action"
				id="wpforms-builder-drip-provider-{{ data.connection.id }}-action"
				<# if ( _.isEmpty( data.connection.account_id ) ) { #>disabled<# } #>
				name="providers[<?php echo esc_attr( $slug ); ?>][{{ data.connection.id }}][action]">

				<option value=""><?php esc_html_e( '--- Select Action ---', 'wpforms-lite' ); ?></option>

				<?php
				$actions = [
					'subscriber_subscribe'   => __( 'Subscriber: Create or Update', 'wpforms-lite' ),
					'subscriber_unsubscribe' => __( 'Subscriber: Unsubscribe', 'wpforms-lite' ),
					'subscriber_delete'      => __( 'Subscriber: Delete', 'wpforms-lite' ),
					'campaign_sub'           => __( 'Campaign: Subscribe', 'wpforms-lite' ),
					'campaign_unsub'         => __( 'Campaign: Unsubscribe', 'wpforms-lite' ),
					'event'                  => __( 'Record Event', 'wpforms-lite' ),
				];

				foreach ( $actions as $action_slug => $label ) :
					?>
					<option value="<?php echo esc_attr( $action_slug ); ?>"<# if ( data.connection.action === '<?php echo esc_attr( $action_slug ); ?>' ) { #> selected<# } #>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	<# } #>

	<!-- The action block is rendered here from its own template. -->
	<div class="wpforms-builder-drip-provider-actions-data"></div>

	<# if ( ! _.isEmpty( data.accounts ) ) { #>
		{{{ data.conditional }}}
	<# } #>
</div>
