<?php

namespace WPForms\Forms\Fields\FileUpload;

use WPForms\Forms\Fields\Traits\ProField as ProFieldTrait;
use WPForms\Forms\Fields\Traits\CameraTrait;
use WPForms\Forms\Fields\Traits\AccessRestrictionsTrait;
use WPForms_Field;

/**
 * File upload field.
 *
 * @since 1.9.4
 */
class Field extends WPForms_Field {

	use ProFieldTrait;
	use CameraTrait;
	use AccessRestrictionsTrait;

	/**
	 * Classic (old) style of the file uploader field.
	 *
	 * @since 1.9.4
	 *
	 * @var string
	 */
	public const STYLE_CLASSIC = 'classic';

	/**
	 * Modern style of the file uploader field.
	 *
	 * @since 1.9.4
	 *
	 * @var string
	 */
	public const STYLE_MODERN = 'modern';

	/**
	 * Maximum file number.
	 *
	 * @since 1.9.4
	 *
	 * @var int
	 */
	private const MAX_FILE_NUM = 100;

	/**
	 * Replaceable (either in PHP or JS) template for a maximum file number.
	 *
	 * @since 1.9.4
	 *
	 * @var string
	 */
	protected const TEMPLATE_MAXFILENUM = '{maxFileNumber}';

	/**
	 * Primary class constructor.
	 *
	 * @since 1.9.4
	 */
	public function init() {

		// Define field type information.
		$this->name     = esc_html__( 'File Upload', 'wpforms-lite' );
		$this->keywords = esc_html__( 'attach, attachment, document, upload, resume, cv', 'wpforms-lite' );
		$this->type     = 'file-upload';
		$this->icon     = 'fa-upload';
		$this->order    = 100;
		$this->group    = 'fancy';

		$this->default_settings = [
			'style' => self::STYLE_MODERN,
		];

		$this->init_pro_field();
	}

	/**
	 * Field options panel inside the builder.
	 *
	 * @since 1.9.4
	 *
	 * @param array $field Field data and settings.
	 *
	 * @noinspection HtmlUnknownTarget
	 */
	public function field_options( $field ) {

		$style = ! empty( $field['style'] ) ? $field['style'] : self::STYLE_MODERN;

		/*
		 * Basic field options.
		 */

		// Options open markup.
		$this->field_option(
			'basic-options',
			$field,
			[
				'markup'      => 'open',
				'after_title' => $this->get_field_options_notice(),
			]
		);

		// Label.
		$this->field_option( 'label', $field );

		// Description.
		$this->field_option( 'description', $field );

		// Allowed extensions.
		$lbl = $this->field_element(
			'label',
			$field,
			[
				'slug'          => 'extensions',
				'value'         => esc_html__( 'Allowed File Extensions', 'wpforms-lite' ),
				'tooltip'       => esc_html__( 'Enter the extensions you would like to allow, comma separated.', 'wpforms-lite' ),
				'after_tooltip' => sprintf(
					'<a href="%1$s" class="after-label-description" target="_blank" rel="noopener noreferrer">%2$s</a>',
					esc_url( wpforms_utm_link( 'https://wpforms.com/docs/a-complete-guide-to-the-file-upload-field/#file-types', 'Field Options', 'File Upload Extensions Documentation' ) ),
					esc_html__( 'See More Details', 'wpforms-lite' )
				),
			],
			false
		);

		$fld = $this->field_element(
			'text',
			$field,
			[
				'slug'  => 'extensions',
				'value' => ! empty( $field['extensions'] ) ? $field['extensions'] : '',
			],
			false
		);

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'extensions',
				'content' => $lbl . $fld,
			]
		);

		// Max file size.
		$lbl = $this->field_element(
			'label',
			$field,
			[
				'slug'    => 'max_size',
				'value'   => esc_html__( 'Max File Size', 'wpforms-lite' ),
				'tooltip' => sprintf( /* translators: %s - max upload size. */
					esc_html__( 'Enter the max size of each file, in megabytes, to allow. If left blank, the value defaults to the maximum size the server allows which is %s.', 'wpforms-lite' ),
					wpforms_max_upload()
				),
			],
			false
		);
		$fld = $this->field_element(
			'text',
			$field,
			[
				'slug'  => 'max_size',
				'type'  => 'number',
				'attrs' => [
					'min'     => 1,
					'max'     => 512,
					'step'    => 1,
					'pattern' => '[0-9]',
				],
				'value' => ! empty( $field['max_size'] ) ? abs( $field['max_size'] ) : '',
			],
			false
		);

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'max_size',
				'content' => $lbl . $fld,
			]
		);

		// Max file number.
		$lbl = $this->field_element(
			'label',
			$field,
			[
				'slug'    => 'max_file_number',
				'value'   => esc_html__( 'Max File Uploads', 'wpforms-lite' ),
				'tooltip' => esc_html__( 'Enter the max number of files to allow. If left blank, the value defaults to 1.', 'wpforms-lite' ),
			],
			false
		);

		$fld = $this->field_element(
			'text',
			$field,
			[
				'slug'  => 'max_file_number',
				'type'  => 'number',
				'attrs' => [
					'min'     => 1,
					'max'     => self::MAX_FILE_NUM,
					'step'    => 1,
					'pattern' => '[0-9]',
				],
				'value' => $this->get_max_file_number( $field ),
			],
			false
		);

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'max_file_number',
				'content' => $lbl . $fld,
				'class'   => $style === self::STYLE_CLASSIC ? 'wpforms-hidden' : '',
			]
		);

		// Restrict Dimensions toggle.
		$fld = $this->field_element(
			'toggle',
			$field,
			[
				'slug'    => 'restrict_dimensions',
				'value'   => ! empty( $field['restrict_dimensions'] ) ? 1 : '',
				'desc'    => esc_html__( 'Restrict Dimensions', 'wpforms-lite' ),
				'tooltip' => esc_html__( 'Reject images whose width or height in pixels is outside the limits below.', 'wpforms-lite' ),
				'class'   => 'wpforms-file-upload-restrict-dimensions',
			],
			false
		);

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'restrict_dimensions',
				'content' => $fld,
			]
		);

		$this->field_option_dimensions_row( $field, 'exact', esc_html__( 'Exact Dimensions', 'wpforms-lite' ) );
		$this->field_option_dimensions_row( $field, 'min', esc_html__( 'Min Dimensions', 'wpforms-lite' ) );
		$this->field_option_dimensions_row( $field, 'max', esc_html__( 'Max Dimensions', 'wpforms-lite' ) );
		$this->field_option_camera_dimensions_notice( $field );

		// Required toggle.
		$this->field_option( 'required', $field );

		// Options close markup.
		$this->field_option( 'basic-options', $field, [ 'markup' => 'close' ] );

		/*
		 * Advanced field options.
		 */

		// Options open markup.
		$this->field_option( 'advanced-options', $field, [ 'markup' => 'open' ] );

		// Style.
		$lbl = $this->field_element(
			'label',
			$field,
			[
				'slug'    => 'style',
				'value'   => esc_html__( 'Style', 'wpforms-lite' ),
				'tooltip' => esc_html__( 'Modern Style supports multiple file uploads, displays a drag-and-drop upload box, and uses AJAX. Classic Style supports single file upload and displays a traditional upload button.', 'wpforms-lite' ),
			],
			false
		);

		$fld = $this->field_element(
			'select',
			$field,
			[
				'slug'    => 'style',
				'value'   => $style,
				'options' => [
					self::STYLE_MODERN  => esc_html__( 'Modern', 'wpforms-lite' ),
					self::STYLE_CLASSIC => esc_html__( 'Classic', 'wpforms-lite' ),
				],
			],
			false
		);

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'style',
				'content' => $lbl . $fld,
			]
		);

		// Custom CSS classes.
		$this->field_option( 'css', $field );

		// Media Library toggle.
		$fld = $this->field_element(
			'toggle',
			$field,
			[
				'slug'    => 'media_library',
				'value'   => ! empty( $field['media_library'] ) ? 1 : '',
				'desc'    => esc_html__( 'Store Files in WordPress Media Library', 'wpforms-lite' ),
				'tooltip' => esc_html__( 'Check this option to store the final uploaded file in the WordPress Media Library', 'wpforms-lite' ),
				'class'   => 'wpforms-file-upload-media-library',
			],
			false
		);

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'media_library',
				'content' => $fld,
			]
		);

		// Access Restrictions.
		$this->access_restrictions_options( $field );

		// Camera.
		$this->camera_options( $field );

		// Hide Label.
		$this->field_option( 'label_hide', $field );

		// Options close markup.
		$this->field_option(
			'advanced-options',
			$field,
			[
				'markup' => 'close',
			]
		);
	}

	/**
	 * Render a Width / Height pair of dimension inputs as one field option row.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $field  Field data and settings.
	 * @param string $prefix Option key prefix: `exact`, `min` or `max`.
	 * @param string $label  Row label.
	 */
	private function field_option_dimensions_row( array $field, string $prefix, string $label ): void {

		$is_locked      = $this->is_dimensions_row_locked( $field, $prefix );
		$conflict_sides = $prefix === 'exact' ? [] : $this->get_dimension_conflict_sides( $field );
		$inputs         = [
			'width'  => $this->get_dimension_input( $field, $prefix, 'width', $is_locked, in_array( 'width', $conflict_sides, true ) ),
			'height' => $this->get_dimension_input( $field, $prefix, 'height', $is_locked, in_array( 'height', $conflict_sides, true ) ),
		];
		$conflict_error = $prefix === 'max' ? $this->get_dimensions_conflict_error( $field, ! empty( $conflict_sides ) ) : '';

		$lbl = $this->field_element(
			'label',
			$field,
			[
				'slug'  => "{$prefix}_width",
				'value' => $label,
			],
			false
		);

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => "{$prefix}_dimensions",
				'class'   => empty( $field['restrict_dimensions'] ) ? 'wpforms-hidden' : '',
				'content' => $lbl . sprintf(
					'<div class="wpforms-input-row">
						<div class="width">%1$s<label for="wpforms-field-option-%2$d-%3$s_width" class="sub-label">%4$s</label></div>
						<div class="height">%5$s<label for="wpforms-field-option-%2$d-%3$s_height" class="sub-label">%6$s</label></div>
					</div>',
					$inputs['width'],
					(int) $field['id'],
					esc_attr( $prefix ),
					esc_html__( 'Width', 'wpforms-lite' ),
					$inputs['height'],
					esc_html__( 'Height', 'wpforms-lite' )
				) . $conflict_error,
			]
		);
	}

	/**
	 * Get the sides whose Min dimension is larger than the Max dimension.
	 *
	 * @since 2.0.5
	 *
	 * @param array $field Field data and settings.
	 *
	 * @return array Sides in conflict: `width`, `height` or both.
	 */
	private function get_dimension_conflict_sides( array $field ): array {

		// While an exact dimension is set, the range is locked and ignored.
		if ( $this->is_dimensions_row_locked( $field, 'min' ) ) {
			return [];
		}

		$sides = [];

		foreach ( [ 'width', 'height' ] as $side ) {
			$min = $this->get_dimension_option( $field, "min_{$side}" );
			$max = $this->get_dimension_option( $field, "max_{$side}" );

			if ( $min > 0 && $max > 0 && $min > $max ) {
				$sides[] = $side;
			}
		}

		return $sides;
	}

	/**
	 * Get the error row shown under the Max dimensions while a Min dimension is larger.
	 *
	 * @since 2.0.5
	 *
	 * @param array $field      Field data and settings.
	 * @param bool  $is_visible Whether the error is shown on load.
	 *
	 * @return string Error row markup.
	 */
	private function get_dimensions_conflict_error( array $field, bool $is_visible ): string {

		return $this->field_element(
			'row',
			$field,
			[
				'slug'    => 'dimensions_error',
				'content' => esc_html__( "Min Dimensions can't be larger than Max Dimensions.", 'wpforms-lite' ),
				'class'   => [
					$is_visible ? '' : 'wpforms-hidden',
					'wpforms-error',
					'wpforms-error-message',
				],
			],
			false
		);
	}

	/**
	 * Render the notice that dimension limits skip camera photos, shown while both Restrict Dimensions and the camera are on.
	 *
	 * @since 2.0.5
	 *
	 * @param array $field Field data and settings.
	 */
	private function field_option_camera_dimensions_notice( array $field ): void {

		$is_visible = ! empty( $field['restrict_dimensions'] ) && ! empty( $field['camera_enabled'] );

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'dimensions_camera_notice',
				'class'   => $is_visible ? '' : 'wpforms-hidden',
				'content' => sprintf(
					'<div class="wpforms-alert-warning wpforms-alert"><p>%s</p></div>',
					esc_html__( 'Dimension limits don’t apply to photos taken with a camera, since users can’t control the image size their device captures.', 'wpforms-lite' )
				),
			]
		);
	}

	/**
	 * Whether the inputs of a dimensions row are locked at zero.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $field  Field data and settings.
	 * @param string $prefix Option key prefix: `exact`, `min` or `max`.
	 *
	 * @return bool True for a min or max row when an exact dimension is set.
	 */
	private function is_dimensions_row_locked( array $field, string $prefix ): bool {

		return $prefix !== 'exact' && ( $this->get_dimension_option( $field, 'exact_width' ) > 0 || $this->get_dimension_option( $field, 'exact_height' ) > 0 );
	}

	/**
	 * Get a dimension option as a whole number of pixels.
	 *
	 * Every reader goes through it, so the builder and the form agree on any saved value, legacy negative or decimal ones included.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $field Field data and settings.
	 * @param string $key   Option key, e.g. `min_width`.
	 *
	 * @return int Pixels, 0 when the option is not set.
	 */
	private function get_dimension_option( array $field, string $key ): int {

		return absint( $field[ $key ] ?? 0 );
	}

	/**
	 * Get the markup of a single width or height dimension input.
	 *
	 * @since 2.0.5
	 *
	 * @param array  $field     Field data and settings.
	 * @param string $prefix    Option key prefix: `exact`, `min` or `max`.
	 * @param string $side      Dimension side: `width` or `height`.
	 * @param bool   $is_locked Whether the input is locked at zero by an exact dimension.
	 * @param bool   $has_error Whether the input is marked as conflicting with its pair.
	 *
	 * @return string Input markup.
	 */
	private function get_dimension_input( array $field, string $prefix, string $side, bool $is_locked, bool $has_error ): string {

		$key   = "{$prefix}_{$side}";
		$value = $this->get_dimension_option( $field, $key );

		$input = $this->field_element(
			'text',
			$field,
			[
				'slug'  => $key,
				'type'  => 'number',
				'class' => [
					$prefix === 'exact' ? 'wpforms-file-upload-exact-dimension' : 'wpforms-file-upload-range-dimension',
					$has_error ? 'wpforms-error' : '',
					$is_locked ? 'wpforms-hidden' : '',
				],
				'attrs' => [
					'min'     => 0,
					'step'    => 1,
					'pattern' => '[0-9]*',
				],
				'value' => $value > 0 ? $value : '',
			],
			false
		);

		if ( ! $is_locked ) {
			return $input;
		}

		// The real input stays in the form, hidden, so a save keeps its value. This one only shows the lock.
		return $input . '<input type="number" class="wpforms-file-upload-range-dimension-locked" value="0" disabled>';
	}

	/**
	 * Field preview panel inside the builder.
	 *
	 * @since 1.9.4
	 *
	 * @param array $field Field data.
	 */
	public function field_preview( $field ) {

		// Label.
		$this->field_preview_option(
			'label',
			$field,
			[
				'label_badge' => $this->get_field_preview_badge(),
			]
		);

		$modern_classes  = [ 'wpforms-file-upload-builder-modern' ];
		$classic_classes = [ 'wpforms-file-upload-builder-classic' ];

		if ( empty( $field['style'] ) || $field['style'] !== self::STYLE_CLASSIC ) {
			$classic_classes[] = 'wpforms-hide';
		} else {
			$modern_classes[] = 'wpforms-hide';
		}

		$strings         = $this->get_strings();
		$max_file_number = $this->get_max_file_number( $field );

		/**
		 * Filter the classic camera text.
		 *
		 * @since 1.9.8
		 *
		 * @param string $classic_camera The classic camera text.
		 */
		$classic_camera_text = (string) apply_filters(
			'wpforms_forms_fields_file_upload_field_classic_camera_text',
			esc_html__( 'Capture With Your Camera', 'wpforms-lite' )
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wpforms_render(
			'fields/file-upload/file-upload-backend',
			[
				'max_file_number' => $max_file_number,
				'preview_hint'    => str_replace( self::TEMPLATE_MAXFILENUM, $max_file_number, $strings['preview_hint'] ),
				'modern_classes'  => implode( ' ', $modern_classes ),
				'classic_classes' => implode( ' ', $classic_classes ),
				'is_camera'       => ! empty( $field['camera_enabled'] ) ? 1 : '',
				'classic_camera'  => $classic_camera_text,
			],
			true
		);

		// Description.
		$this->field_preview_option( 'description', $field );
	}

	/**
	 * File Uploads specific strings.
	 *
	 * @since 1.9.4
	 *
	 * @return array Field-specific strings.
	 */
	public function get_strings(): array {

		return [
			'preview_title_single'        => sprintf(
				/* translators: %1$s: Choose File to Upload opening tag, %2$s: Choose File to Upload closing tag. */
				esc_html__( 'Drag & Drop File or %1$sChoose File to Upload%2$s', 'wpforms-lite' ),
				'<span class="wpforms-file-upload-choose-file">',
				'</span>'
			),
			'preview_title_plural'        => sprintf(
				/* translators: %1$s: Choose Files to Upload opening tag, %2$s: Choose Files to Upload closing tag. */
				esc_html__( 'Drag & Drop Files or %1$sChoose Files to Upload%2$s', 'wpforms-lite' ),
				'<span class="wpforms-file-upload-choose-file">',
				'</span>'
			),
			'preview_title_single_camera' => sprintf(
				/* translators: %1$s: Choose File to Upload opening tag, %2$s: Closing tag, %3$s: Capture With Camera opening tag. */
				esc_html__( 'Drag & Drop File, %1$sChoose File to Upload%2$s, or %3$sCapture With Camera%2$s', 'wpforms-lite' ),
				'<span class="wpforms-file-upload-choose-file">',
				'</span>',
				'<span class="wpforms-file-upload-capture-camera">'
			),
			'preview_title_plural_camera' => sprintf(
				/* translators: %1$s: Choose Files to Upload opening tag, %2$s: Closing tag, %3$s: Capture With Camera opening tag. */
				esc_html__( 'Drag & Drop Files, %1$sChoose Files to Upload%2$s, or %3$sCapture With Camera%2$s', 'wpforms-lite' ),
				'<span class="wpforms-file-upload-choose-file">',
				'</span>',
				'<span class="wpforms-file-upload-capture-camera">'
			),
			'preview_hint'                => sprintf( /* translators: % - max number of files as a template string (not a number), replaced by a number later. */
				esc_html__( 'You can upload up to %s files.', 'wpforms-lite' ),
				self::TEMPLATE_MAXFILENUM
			),
			'password_match_error_title'  => esc_html__( 'Passwords Do Not Match', 'wpforms-lite' ),
			'password_match_error_text'   => esc_html__( 'Please check the password for the following fields: {fields}', 'wpforms-lite' ),
			'password_empty_error_title'  => esc_html__( 'Passwords Are Empty', 'wpforms-lite' ),
			'password_empty_error_text'   => esc_html__( 'Please enter a password for the following fields: {fields}', 'wpforms-lite' ),
			'dimensions_error_title'      => esc_html__( 'Invalid Image Dimensions', 'wpforms-lite' ),
			'dimensions_error_text'       => esc_html__( 'Min Dimensions can\'t be larger than Max Dimensions for the following fields: {fields}', 'wpforms-lite' ),
			'notification_warning_title'  => esc_html__( 'Cannot Enable Restrictions', 'wpforms-lite' ),
			'notification_warning_text'   => esc_html__( 'This field is attached to Notifications. In order to enable restrictions, please first remove it from File Upload Attachments in Notifications.', 'wpforms-lite' ),
			'notification_error_title'    => esc_html__( 'Cannot Enable Attachments', 'wpforms-lite' ),
			'notification_error_text'     => esc_html__( 'The following fields ({fields}) cannot be attached to notifications because restrictions are enabled for them.', 'wpforms-lite' ),
			'all_user_roles_selected'     => esc_html__( 'All User Roles already selected', 'wpforms-lite' ),
			'incompatible_addon_text'     => esc_html__( 'File Upload Restrictions can\'t be enabled because the current version of the Post Submissions addon is incompatible.', 'wpforms-lite' ),
		];
	}

	/**
	 * Getting max file number.
	 *
	 * @since 1.9.4
	 *
	 * @param array $field Field data.
	 *
	 * @return int
	 * @noinspection PhpMissingParamTypeInspection
	 */
	protected function get_max_file_number( $field ): int {

		if ( empty( $field['max_file_number'] ) ) {
			return 1;
		}

		$max_file_number = absint( $field['max_file_number'] );

		if ( $max_file_number < 1 ) {
			return 1;
		}

		if ( $max_file_number > self::MAX_FILE_NUM ) {
			return self::MAX_FILE_NUM;
		}

		return $max_file_number;
	}

	/**
	 * Get the image dimension limits set for the field.
	 *
	 * @since 2.0.5
	 *
	 * @param array $field Field data.
	 *
	 * @return array Limits in pixels keyed by option name, e.g. `[ 'min_width' => 800 ]`. Empty when nothing is restricted.
	 */
	protected function get_image_dimension_limits( array $field ): array {

		if ( empty( $field['restrict_dimensions'] ) ) {
			return [];
		}

		$limits = [];

		foreach ( [ 'exact_width', 'exact_height', 'min_width', 'min_height', 'max_width', 'max_height' ] as $key ) {
			$limits[ $key ] = $this->get_dimension_option( $field, $key );
		}

		$limits = array_filter( $limits );

		// Exact dimensions override the range, including stale range values saved before the exact ones.
		if ( isset( $limits['exact_width'] ) || isset( $limits['exact_height'] ) ) {
			$limits = array_intersect_key( $limits, array_flip( [ 'exact_width', 'exact_height' ] ) );
		}

		return $limits;
	}

	/**
	 * Field display on the form front-end.
	 *
	 * @since 1.9.4
	 *
	 * @param array $field      Field data and settings.
	 * @param array $deprecated Deprecated field attributes. Use field properties.
	 * @param array $form_data  Form data and settings.
	 */
	public function field_display( $field, $deprecated, $form_data ) {
	}
}
