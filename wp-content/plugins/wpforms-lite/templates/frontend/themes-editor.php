<?php
/**
 * Front-end Form Themes editor panel.
 *
 * @since 2.0.5
 *
 * @var array $sections       Sections from Data::get_sections(): id => [ label, rows, tier, locked ].
 * @var bool  $multiple_forms Whether the page contains multiple editable forms.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Hidden until an entry point or, on mobile, the floating bar shows it.
?>

<div id="wpforms-themes-editor" class="wpforms-themes-editor<?php echo $sections ? '' : ' wpforms-themes-editor-compact'; ?> wpforms-themes-editor-collapsed wpforms-themes-editor-closed" role="dialog" aria-labelledby="wpforms-themes-editor-title">

	<div class="wpforms-themes-editor-header">
		<h3 id="wpforms-themes-editor-title">
			<i class="fa-solid fa-palette" aria-hidden="true"></i>
			<?php esc_html_e( 'Form Theme', 'wpforms-lite' ); ?>
		</h3>
		<button type="button" class="wpforms-themes-editor-collapse" aria-controls="wpforms-themes-editor-body" aria-expanded="false" aria-label="<?php esc_attr_e( 'Expand', 'wpforms-lite' ); ?>" title="<?php esc_attr_e( 'Expand', 'wpforms-lite' ); ?>">
			<i class="fa-solid fa-chevron-up" aria-hidden="true"></i>
		</button>
	</div>

	<div class="wpforms-themes-editor-body" id="wpforms-themes-editor-body">

		<?php if ( $multiple_forms ) : ?>
			<div class="wpforms-themes-editor-form-selector">
				<label for="wpforms-themes-editor-form"><?php esc_html_e( 'Form', 'wpforms-lite' ); ?></label>
				<select id="wpforms-themes-editor-form"></select>
			</div>
		<?php endif; ?>

		<section class="wpforms-themes-editor-section wpforms-themes-editor-section-open" data-section="themes">
			<button type="button" class="wpforms-themes-editor-section-toggle" aria-expanded="true" aria-controls="wpforms-themes-editor-section-themes">
				<span><?php esc_html_e( 'Themes', 'wpforms-lite' ); ?></span>
				<i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
			</button>
			<div class="wpforms-themes-editor-section-content" id="wpforms-themes-editor-section-themes">
				<div class="wpforms-themes-editor-themes-list" role="listbox" aria-label="<?php esc_attr_e( 'Themes', 'wpforms-lite' ); ?>" tabindex="-1"></div>

				<div class="wpforms-themes-editor-theme-meta wpforms-themes-editor-hidden">
					<div class="wpforms-themes-editor-control">
						<label for="wpforms-themes-editor-theme-name"><?php esc_html_e( 'Theme Name', 'wpforms-lite' ); ?></label>
						<input type="text" id="wpforms-themes-editor-theme-name" spellcheck="false">
					</div>
					<button type="button" id="wpforms-themes-editor-delete-theme"><?php esc_html_e( 'Delete Theme', 'wpforms-lite' ); ?></button>
				</div>
			</div>
		</section>

		<?php foreach ( $sections as $section_key => $section ) : ?>
			<?php
			$is_open   = $section_key !== 'advanced';
			$is_locked = ! empty( $section['locked'] );
			?>
			<section class="wpforms-themes-editor-section<?php echo $is_open ? ' wpforms-themes-editor-section-open' : ''; ?><?php echo $is_locked ? ' wpforms-themes-editor-section-locked' : ''; ?>" data-section="<?php echo esc_attr( $section_key ); ?>"<?php echo $is_locked ? ' data-tier="pro"' : ''; ?>>
				<button type="button" class="wpforms-themes-editor-section-toggle<?php echo $is_locked ? ' wpforms-themes-editor-section-pro-blocked' : ''; ?>" aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>" aria-controls="wpforms-themes-editor-section-<?php echo esc_attr( $section_key ); ?>">
					<span><?php echo esc_html( $section['label'] ); ?></span>
					<i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
				</button>
				<div class="wpforms-themes-editor-section-content" id="wpforms-themes-editor-section-<?php echo esc_attr( $section_key ); ?>">
					<?php foreach ( $section['rows'] as $row ) : ?>
						<div class="wpforms-themes-editor-row wpforms-themes-editor-row-<?php echo count( $row ); ?>-col">
							<?php foreach ( $row as $control ) : ?>
								<?php
								$disabled   = $control['editable'] ? '' : ' disabled';
								$control_id = 'wpforms-themes-editor-setting-' . $control['key'];
								?>
								<div class="wpforms-themes-editor-control wpforms-themes-editor-control-<?php echo esc_attr( $control['type'] ); ?>" data-tier="<?php echo esc_attr( $control['tier'] ); ?>">
									<?php if ( $control['label'] !== '' && $control['type'] !== 'tiles' ) : ?>
										<label for="<?php echo esc_attr( $control_id ); ?>"><?php echo esc_html( $control['label'] ); ?></label>
									<?php endif; ?>

									<?php if ( $control['type'] === 'select' ) : ?>
										<select id="<?php echo esc_attr( $control_id ); ?>" data-setting="<?php echo esc_attr( $control['key'] ); ?>"<?php echo esc_attr( $disabled ); ?>>
											<?php foreach ( $control['options'] as $option_value => $option_label ) : ?>
												<option value="<?php echo esc_attr( $option_value ); ?>"><?php echo esc_html( $option_label ); ?></option>
											<?php endforeach; ?>
										</select>

									<?php elseif ( $control['type'] === 'number' ) : ?>
										<span class="wpforms-themes-editor-number">
											<input type="number" min="0" id="<?php echo esc_attr( $control_id ); ?>" data-setting="<?php echo esc_attr( $control['key'] ); ?>"<?php echo esc_attr( $disabled ); ?>>
											<?php if ( $control['unit'] === 'px' ) : ?>
												<em>px</em>
											<?php endif; ?>
										</span>

									<?php elseif ( $control['type'] === 'color' ) : ?>
										<span class="wpforms-themes-editor-color">
											<input type="text" id="<?php echo esc_attr( $control_id ); ?>" data-setting="<?php echo esc_attr( $control['key'] ); ?>" spellcheck="false"<?php echo esc_attr( $disabled ); ?>>
										</span>

									<?php elseif ( $control['type'] === 'tiles' ) : ?>
										<div class="wpforms-themes-editor-tiles" role="radiogroup" aria-label="<?php echo esc_attr( $control['label'] ); ?>">
											<?php foreach ( $control['options'] as $option_value => $option_label ) : ?>
												<label class="wpforms-themes-editor-tile-option wpforms-themes-editor-tile-option-<?php echo esc_attr( $option_value ); ?>" title="<?php echo esc_attr( $option_label ); ?>">
													<input type="radio" name="wpforms-themes-editor-<?php echo esc_attr( $control['key'] ); ?>" value="<?php echo esc_attr( $option_value ); ?>" data-setting="<?php echo esc_attr( $control['key'] ); ?>"<?php echo esc_attr( $disabled ); ?>>
													<span class="wpforms-themes-editor-tile" aria-hidden="true"><i></i></span>
													<span class="wpforms-themes-editor-visually-hidden"><?php echo esc_html( $option_label ); ?></span>
												</label>
											<?php endforeach; ?>
										</div>

									<?php elseif ( $control['type'] === 'textarea' ) : ?>
										<textarea rows="4" id="<?php echo esc_attr( $control_id ); ?>" data-setting="<?php echo esc_attr( $control['key'] ); ?>" spellcheck="false"<?php echo esc_attr( $disabled ); ?>></textarea>
										<?php if ( $control['description'] !== '' ) : ?>
											<span class="wpforms-themes-editor-control-desc"><?php echo esc_html( $control['description'] ); ?></span>
										<?php endif; ?>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>

					<?php if ( $section_key === 'background_styles' ) : ?>
						<div class="wpforms-themes-editor-bg-selector wpforms-themes-editor-hidden">
							<button type="button" class="wpforms-themes-editor-bg-choose wpforms-themes-editor-hidden"><?php esc_html_e( 'Choose Image', 'wpforms-lite' ); ?></button>
							<button type="button" class="wpforms-themes-editor-bg-preview wpforms-themes-editor-hidden" aria-label="<?php esc_attr_e( 'Change background image', 'wpforms-lite' ); ?>"></button>
							<button type="button" class="wpforms-themes-editor-bg-remove wpforms-themes-editor-hidden"><?php esc_html_e( 'Remove Image', 'wpforms-lite' ); ?></button>
						</div>
					<?php endif; ?>
				</div>
			</section>
		<?php endforeach; ?>

	</div>

	<div class="wpforms-themes-editor-footer">
		<button type="button" id="wpforms-themes-editor-save" class="wpforms-themes-editor-save">
			<?php esc_html_e( 'Save Changes', 'wpforms-lite' ); ?>
		</button>
		<span class="wpforms-themes-editor-status" role="status" aria-live="polite"></span>
	</div>

</div>
