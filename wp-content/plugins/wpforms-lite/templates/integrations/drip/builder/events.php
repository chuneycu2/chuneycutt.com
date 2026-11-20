<?php
/**
 * Drip action block: record a custom event.
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
<div class="wpforms-builder-provider-connection-block wpforms-builder-drip-provider-events">
	<div class="wpforms-builder-provider-connection-setting">
		<?php echo $email_field_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>

	<div class="wpforms-builder-provider-connection-settings-group">
		<h3><?php esc_html_e( 'Custom Events', 'wpforms-lite' ); ?></h3>

		<p><?php esc_html_e( 'Select one of the existing events:', 'wpforms-lite' ); ?></p>

		<div class="wpforms-builder-provider-connection-setting">
			<select class="js-wpforms-builder-drip-provider-connection-event"
				id="wpforms-builder-drip-provider-%connection_id%-event"
				<# if ( _.isEmpty( data.events ) ) { #>disabled<# } #>
				name="providers[<?php echo esc_attr( $slug ); ?>][{{ data.connection.id }}][events][name]">

				<option value=""><?php esc_html_e( '--- Select Saved Event ---', 'wpforms-lite' ); ?></option>

				<# _.each( data.events, function( event ) { #>
					<option value="{{ event }}"<# if ( _.isMatch( data.connection.events, { name: event } ) ) { #> selected<# } #>>
						{{ event }}
					</option>
				<# } ); #>
			</select>

			<p class="text-btn-inlined">
				<span class="text-btn-inlined-text">
					<?php esc_html_e( 'or', 'wpforms-lite' ); ?>
				</span>
				<button class="button-secondary text-btn-inlined-btn js-wpforms-builder-drip-provider-connection-event-new" type="button">
					<?php esc_html_e( 'Add New', 'wpforms-lite' ); ?>
				</button>
			</p>
		</div>

		<div class="wpforms-builder-provider-connection-setting wpforms-builder-drip-provider-events-new">
			<input type="text" value=""
				id="wpforms-builder-drip-provider-%connection_id%-events-new"
				name="providers[<?php echo esc_attr( $slug ); ?>][{{ data.connection.id }}][events][new]"
				placeholder="<?php esc_attr_e( 'Your new custom event name', 'wpforms-lite' ); ?>">

			<p class="description">
				<?php esc_html_e( 'Example: Submitted Drip form', 'wpforms-lite' ); ?>
			</p>
		</div>
	</div>

	<div class="wpforms-builder-provider-connection-settings-group wpforms-builder-drip-provider-prospect">
		<div class="wpforms-builder-provider-connection-setting">
			<?php
			echo wpforms_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'integrations/drip/builder/partials/toggle',
				[
					'slug'      => $slug,
					'key'       => 'prospect',
					'id_suffix' => 'subscriber-prospect-check',
					'label'     => __( 'Mark user as a prospect', 'wpforms-lite' ),
				],
				true
			);
			?>
		</div>
	</div>
</div>
