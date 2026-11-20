<?php
/**
 * Drip action block: create or update a subscriber.
 *
 * The campaign subscribe action reuses this block with some settings left out (`data.ignore`).
 *
 * @since 2.0.5
 *
 * @var string $slug                 Provider slug.
 * @var string $email_field_html     Subscriber Email select, built by wpforms_panel_field().
 * @var string $new_email_field_html New Subscriber Email select, built by wpforms_panel_field().
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpforms-builder-provider-connection-block wpforms-builder-drip-provider-subscriber-subscribe">
	<h3><?php esc_html_e( 'Create or Update Subscriber', 'wpforms-lite' ); ?></h3>

	<div class="wpforms-builder-provider-connection-setting">
		<?php echo $email_field_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>

	<# if ( _.indexOf( data.ignore, 'new_email' ) === -1 ) { #>
		<div class="wpforms-builder-provider-connection-setting">
			<?php echo $new_email_field_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	<# } #>

	<# if ( _.indexOf( data.ignore, 'user_id' ) === -1 ) { #>
		<div class="wpforms-builder-provider-connection-setting">
			<?php
			echo wpforms_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'integrations/drip/builder/partials/toggle',
				[
					'slug'        => $slug,
					'key'         => 'user_id',
					'id_suffix'   => 'subscriber-user_id',
					'label'       => __( 'Send user ID to Drip (if the user was logged in)', 'wpforms-lite' ),
					'description' => __( 'Make sure that users can submit this form only once. All repeated submissions will be ignored by Drip.', 'wpforms-lite' ),
				],
				true
			);
			?>
		</div>
	<# } #>

	<# if ( _.indexOf( data.ignore, 'ip_address' ) === -1 ) { #>
		<div class="wpforms-builder-provider-connection-setting">
			<?php
			echo wpforms_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'integrations/drip/builder/partials/toggle',
				[
					'slug'      => $slug,
					'key'       => 'ip_address',
					'id_suffix' => 'subscriber-ip_address',
					'label'     => __( 'Send user IP address to Drip', 'wpforms-lite' ),
				],
				true
			);
			?>
		</div>
	<# } #>

	<# if ( _.indexOf( data.ignore, 'prospect' ) === -1 ) { #>
		<div class="wpforms-builder-provider-settings-group wpforms-builder-drip-provider-prospect">
			<div class="wpforms-builder-provider-connection-setting wpforms-builder-drip-provider-prospect-check">
				<?php
				echo wpforms_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					'integrations/drip/builder/partials/toggle',
					[
						'slug'      => $slug,
						'key'       => 'prospect',
						'id_suffix' => 'subscriber-prospect',
						'label'     => __( 'Mark user as a prospect', 'wpforms-lite' ),
					],
					true
				);
				?>
			</div>

			<div class="wpforms-builder-provider-connection-setting wpforms-builder-drip-provider-prospect-score<# if ( ! _.isMatch( data.connection, { prospect: 'on' } ) ) { #> hidden<# } #>">
				<label for="wpforms-builder-drip-provider-%connection_id%-prospect-score">
					<?php esc_html_e( 'Starting Lead Score Value', 'wpforms-lite' ); ?>
				</label>

				<input type="number" value="{{ data.connection.base_lead_score }}"
					id="wpforms-builder-drip-provider-%connection_id%-prospect-score"
					name="providers[<?php echo esc_attr( $slug ); ?>][{{ data.connection.id }}][base_lead_score]">

				<p class="description">
					<?php esc_html_e( 'Lead scoring must be enabled in Drip and is only available to users who joined prior to June 2020.', 'wpforms-lite' ); ?>
				</p>
			</div>
		</div>
	<# } #>

	<# if ( _.indexOf( data.ignore, 'tags' ) === -1 ) { #>
		<div class="wpforms-builder-provider-connection-settings-group wpforms-builder-drip-provider-subscriber-subscribe-tags">
			<?php
			$tags_inputs = [
				'add'    => [
					'label'   => __( 'Tags To Add', 'wpforms-lite' ),
					'default' => 'WPForms',
					'ignore'  => '',
				],
				'delete' => [
					'label'   => __( 'Tags To Delete', 'wpforms-lite' ),
					'default' => '',
					'ignore'  => 'tags_delete',
				],
			];

			foreach ( $tags_inputs as $tags_key => $tags_input ) :
				if ( $tags_input['ignore'] !== '' ) :
					?>
					<# if ( _.indexOf( data.ignore, '<?php echo esc_attr( $tags_input['ignore'] ); ?>' ) === -1 ) { #>
					<?php
				endif;
				?>
				<div class="wpforms-builder-provider-connection-setting wpforms-builder-drip-provider-subscriber-subscribe-tags-<?php echo esc_attr( $tags_key ); ?>">
					<label for="wpforms-builder-drip-provider-%connection_id%-subscriber-subscribe-tags-<?php echo esc_attr( $tags_key ); ?>">
						<?php echo esc_html( $tags_input['label'] ); ?>
						<i class="fa fa-question-circle-o wpforms-help-tooltip" title="<?php esc_attr_e( 'Comma-separated list of tags is accepted.', 'wpforms-lite' ); ?>"></i>
					</label>

					<input type="text" class="regular-text"
						id="wpforms-builder-drip-provider-%connection_id%-subscriber-subscribe-tags-<?php echo esc_attr( $tags_key ); ?>"
						value="<# if ( ! _.isEmpty( data.connection.tags ) && ! _.isEmpty( data.connection.tags.<?php echo esc_attr( $tags_key ); ?> ) ) { #>{{ data.connection.tags.<?php echo esc_attr( $tags_key ); ?>.join() }}<# } else { #><?php echo esc_attr( $tags_input['default'] ); ?><# } #>"
						name="providers[<?php echo esc_attr( $slug ); ?>][{{ data.connection.id }}][tags][<?php echo esc_attr( $tags_key ); ?>]">
				</div>
				<?php if ( $tags_input['ignore'] !== '' ) : ?>
					<# } #>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	<# } #>
</div>
