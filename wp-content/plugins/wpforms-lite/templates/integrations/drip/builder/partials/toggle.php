<?php
/**
 * Drip connection toggle: an on/off setting saved under the connection, with an optional description.
 *
 * @since 2.0.5
 *
 * @var string $slug        Provider slug.
 * @var string $key         Connection setting key the toggle saves to.
 * @var string $id_suffix   Trailing part of the input id, after the connection id placeholder.
 * @var string $label       Toggle label.
 * @var string $description Optional description shown under the toggle.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$toggle_id = 'wpforms-builder-drip-provider-%connection_id%-' . $id_suffix;
?>
<div class="wpforms-panel-field">
	<span class="wpforms-toggle-control">
		<input
			type="checkbox"
			value="on"
			id="<?php echo esc_attr( $toggle_id ); ?>"
			name="providers[<?php echo esc_attr( $slug ); ?>][%connection_id%][<?php echo esc_attr( $key ); ?>]"
			<# if ( _.isMatch( data.connection, { <?php echo esc_attr( $key ); ?>: 'on' } ) ) { #>checked<# } #>
		>
		<label class="wpforms-toggle-control-icon" for="<?php echo esc_attr( $toggle_id ); ?>"></label>
		<label for="<?php echo esc_attr( $toggle_id ); ?>" class="wpforms-toggle-control-label">
			<?php echo esc_html( $label ); ?>
		</label>
	</span>
	<?php if ( ! empty( $description ) ) : ?>
		<p class="description">
			<?php echo esc_html( $description ); ?>
		</p>
	<?php endif; ?>
</div>
