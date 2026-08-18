<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FCSA_Admin {

	public static function init(): void {
		add_action( 'add_meta_boxes', [ self::class, 'register_metabox' ] );
		add_action( 'admin_menu', [ self::class, 'register_settings_page' ] );
		add_action( 'restrict_manage_posts', [ self::class, 'render_import_button' ], 10, 2 );
		add_filter( 'post_row_actions', [ self::class, 'add_export_row_action' ], 10, 2 );
		add_filter( 'page_row_actions', [ self::class, 'add_export_row_action' ], 10, 2 );
		add_action( 'admin_post_fcsa_export', [ self::class, 'handle_export' ] );
		add_action( 'admin_post_fcsa_import', [ self::class, 'handle_import' ] );
		add_action( 'wp_ajax_fcsa_import', [ self::class, 'handle_ajax_import' ] );
		add_action( 'admin_post_fcsa_save_settings', [ self::class, 'handle_save_settings' ] );
		add_action( 'admin_notices', [ self::class, 'show_notices' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}

	/**
	 * Adds an "Import" button next to the Filter button on the post/page
	 * list table, for post types enabled in Flexible Sync settings. Clicking
	 * it opens an inline popover that uploads via AJAX — the list table is
	 * already inside a <form>, so a nested upload <form> isn't valid HTML.
	 */
	public static function render_import_button( string $post_type, string $which ): void {
		if ( 'top' !== $which || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! in_array( $post_type, FCSA_Settings::get_enabled_post_types(), true ) ) {
			return;
		}
		?>
		<span class="fcsa-import-widget">
			<button type="button" class="button fcsa-import-toggle"><?php esc_html_e( 'Import', 'flexible-content-sync-for-acf' ); ?></button>
			<div class="fcsa-import-popover" hidden>
				<p>
					<label for="fcsa-import-file-<?php echo esc_attr( $post_type ); ?>"><?php esc_html_e( 'Choose export file:', 'flexible-content-sync-for-acf' ); ?></label><br />
					<input type="file" id="fcsa-import-file-<?php echo esc_attr( $post_type ); ?>" class="fcsa-import-file" accept=".json" />
				</p>
				<p>
					<button type="button" class="button button-secondary fcsa-import-submit"><?php esc_html_e( 'Import From File', 'flexible-content-sync-for-acf' ); ?></button>
					<span class="spinner fcsa-import-spinner"></span>
				</p>
				<p class="fcsa-import-message" role="status"></p>
			</div>
		</span>
		<?php
	}

	/**
	 * Adds an "Export" row action to each post/page in the list table, for
	 * post types enabled in Flexible Sync settings.
	 *
	 * @param array<string, string> $actions
	 */
	public static function add_export_row_action( array $actions, WP_Post $post ): array {
		if ( ! in_array( $post->post_type, FCSA_Settings::get_enabled_post_types(), true ) ) {
			return $actions;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$actions['fcsa_export'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( self::get_export_url( $post->ID ) ),
			esc_html__( 'Export', 'flexible-content-sync-for-acf' )
		);

		return $actions;
	}

	public static function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php', 'edit.php', 'toplevel_page_fcsa-settings' ], true ) ) {
			return;
		}

		wp_enqueue_style( 'fcsa-admin', FCSA_URL . 'admin/css/admin.css', [], FCSA_VERSION );

		// Match render_import_button()'s own visibility gate — otherwise a
		// user who can't see the Import widget would still get a working
		// nonce localized into their page, letting them call wp_ajax_fcsa_import
		// directly (e.g. via devtools) despite the UI implying they can't.
		if ( 'edit.php' !== $hook || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_script( 'fcsa-import-popover', FCSA_URL . 'admin/js/import-popover.js', [], FCSA_VERSION, true );
		wp_localize_script(
			'fcsa-import-popover',
			'fcsaImport',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'fcsa_import' ),
				'i18n'    => [
					'noFile'    => __( 'Choose a .json file first.', 'flexible-content-sync-for-acf' ),
					'importing' => __( 'Importing…', 'flexible-content-sync-for-acf' ),
					'failed'    => __( 'Import failed.', 'flexible-content-sync-for-acf' ),
				],
			]
		);
	}

	public static function register_metabox(): void {
		foreach ( FCSA_Settings::get_enabled_post_types() as $post_type ) {
			add_meta_box(
				'fcsa-metabox',
				__( 'Flexible Sync', 'flexible-content-sync-for-acf' ),
				[ self::class, 'render_metabox' ],
				$post_type,
				'side',
				'low'
			);
		}
	}

	public static function render_metabox( WP_Post $post ): void {
		$export_url = '';
		if ( 'auto-draft' !== $post->post_status ) {
			$export_url = self::get_export_url( $post->ID );
		}
		?>
		<div class="fcsa-metabox">
			<p>
				<?php if ( '' !== $export_url ) : ?>
					<a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Export This Page', 'flexible-content-sync-for-acf' ); ?>
					</a>
				<?php else : ?>
					<span class="description"><?php esc_html_e( 'Save this post before you can export it.', 'flexible-content-sync-for-acf' ); ?></span>
				<?php endif; ?>
			</p>

			<hr />

			<p class="description">
				<?php
				printf(
					/* translators: %s: link to the Flexible Content Sync for ACF settings page */
					esc_html__( 'To import an exported file, go to %s.', 'flexible-content-sync-for-acf' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=fcsa-settings' ) ) . '">' . esc_html__( 'the Flexible Sync menu', 'flexible-content-sync-for-acf' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	private static function get_export_url( int $post_id ): string {
		return wp_nonce_url(
			add_query_arg(
				[
					'action'  => 'fcsa_export',
					'post_id' => $post_id,
				],
				admin_url( 'admin-post.php' )
			),
			'fcsa_export_' . $post_id
		);
	}

	public static function handle_export(): void {
		$post_id = absint( $_GET['post_id'] ?? 0 );
		check_admin_referer( 'fcsa_export_' . $post_id );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Permission denied.', 'flexible-content-sync-for-acf' ) );
		}

		FCSA_Exporter::stream_download( $post_id );
	}

	public static function handle_import(): void {
		check_admin_referer( 'fcsa_import', 'fcsa_import_nonce' );

		// The only UI path to this form (the Settings page) already requires
		// manage_options to even view — this just makes the backend agree,
		// rather than relying solely on the form being unreachable otherwise.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'flexible-content-sync-for-acf' ) );
		}

		$result = self::process_import_upload();

		if ( isset( $result['error'] ) ) {
			self::redirect_with_error( $result['error'] );
		}

		$redirect_args = [
			'post'             => $result['post_id'],
			'action'           => 'edit',
			'fcsa_notice'  => 'success',
		];

		if ( ! empty( $result['warnings'] ) ) {
			set_transient( 'fcsa_import_warnings_' . get_current_user_id(), $result['warnings'], MINUTE_IN_SECONDS * 5 );
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'post.php' ) ) );
		exit;
	}

	/**
	 * AJAX counterpart of handle_import(), used by the inline "Import" popover
	 * on the post/page list table — that table is already inside a <form>, so
	 * a nested upload <form> submitting to admin-post.php isn't valid HTML.
	 */
	public static function handle_ajax_import(): void {
		check_ajax_referer( 'fcsa_import', 'nonce' );

		// Matches render_import_button()'s visibility gate and handle_import()'s
		// own check — only a manage_options user is ever shown a nonce for this.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'flexible-content-sync-for-acf' ) ], 403 );
		}

		$result = self::process_import_upload();

		if ( isset( $result['error'] ) ) {
			wp_send_json_error( [ 'message' => $result['error'] ] );
		}

		wp_send_json_success(
			[
				'editUrl'  => get_edit_post_link( $result['post_id'], 'raw' ),
				'warnings' => $result['warnings'],
			]
		);
	}

	/**
	 * Validates the uploaded fcsa_file and imports it. Shared by the
	 * form-based (handle_import) and AJAX (handle_ajax_import) entry points.
	 *
	 * @return array{error: string}|array{post_id: int, warnings: string[]}
	 */
	private static function process_import_upload(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- both callers (handle_import(), handle_ajax_import()) already verify a nonce before invoking this shared helper; upload error code, extension, and JSON are validated below.
		$file = $_FILES['fcsa_file'] ?? [];

		if ( empty( $file['tmp_name'] ) || UPLOAD_ERR_OK !== ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return [ 'error' => __( 'No file uploaded, or the upload failed.', 'flexible-content-sync-for-acf' ) ];
		}

		$ext = strtolower( pathinfo( sanitize_file_name( $file['name'] ?? '' ), PATHINFO_EXTENSION ) );
		if ( 'json' !== $ext ) {
			return [ 'error' => __( 'Only .json export files are accepted.', 'flexible-content-sync-for-acf' ) ];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents -- reading an uploaded tmp file, not a remote URL.
		$contents = file_get_contents( $file['tmp_name'] );
		$data     = null !== $contents ? json_decode( $contents, true ) : null;

		if ( ! is_array( $data ) ) {
			return [ 'error' => __( 'Could not read that file — it is not valid JSON.', 'flexible-content-sync-for-acf' ) ];
		}

		return FCSA_Importer::import( $data );
	}

	private static function redirect_with_error( string $message ): void {
		set_transient( 'fcsa_import_error_' . get_current_user_id(), $message, MINUTE_IN_SECONDS * 5 );
		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}

	public static function show_notices(): void {
		$user_id = get_current_user_id();

		$error = get_transient( 'fcsa_import_error_' . $user_id );
		if ( $error ) {
			delete_transient( 'fcsa_import_error_' . $user_id );
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $error ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only, reads own redirect param set after a nonce-verified handler.
		if ( isset( $_GET['fcsa_notice'] ) && 'success' === sanitize_key( $_GET['fcsa_notice'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Imported as a new draft.', 'flexible-content-sync-for-acf' ) . '</p></div>';

			$warnings = get_transient( 'fcsa_import_warnings_' . $user_id );
			if ( ! empty( $warnings ) && is_array( $warnings ) ) {
				delete_transient( 'fcsa_import_warnings_' . $user_id );
				echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__( 'Some references could not be resolved:', 'flexible-content-sync-for-acf' ) . '</strong></p><ul>';
				foreach ( $warnings as $warning ) {
					echo '<li>' . esc_html( $warning ) . '</li>';
				}
				echo '</ul></div>';
			}
		}
	}

	// ─── Settings page ────────────────────────────────────────────────────────

	public static function register_settings_page(): void {
		add_menu_page(
			__( 'Flexible Sync', 'flexible-content-sync-for-acf' ),
			__( 'Flexible Sync', 'flexible-content-sync-for-acf' ),
			'manage_options',
			'fcsa-settings',
			[ self::class, 'render_settings_page' ],
			'dashicons-migrate',
			79
		);
	}

	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'flexible-content-sync-for-acf' ) );
		}

		$enabled = FCSA_Settings::get_enabled_post_types();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Flexible Sync', 'flexible-content-sync-for-acf' ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fcsa_save_settings" />
				<?php wp_nonce_field( 'fcsa_save_settings', 'fcsa_settings_nonce' ); ?>

				<h2><?php esc_html_e( 'Enabled Post Types', 'flexible-content-sync-for-acf' ); ?></h2>
				<p class="description"><?php esc_html_e( 'The Export/Import metabox will appear on these post types.', 'flexible-content-sync-for-acf' ); ?></p>

				<fieldset>
					<?php foreach ( FCSA_Settings::available_post_types() as $post_type ) : ?>
						<?php $obj = get_post_type_object( $post_type ); ?>
						<label class="fcsa-checkbox-label">
							<input
								type="checkbox"
								name="post_types[]"
								value="<?php echo esc_attr( $post_type ); ?>"
								<?php checked( in_array( $post_type, $enabled, true ) ); ?>
							/>
							<?php echo esc_html( $obj ? $obj->labels->singular_name : $post_type ); ?>
						</label><br />
					<?php endforeach; ?>
				</fieldset>

				<?php submit_button( __( 'Save Settings', 'flexible-content-sync-for-acf' ) ); ?>
			</form>

			<hr />

			<h2 id="fcsa-import"><?php esc_html_e( 'Import', 'flexible-content-sync-for-acf' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Upload a file exported from the Flexible Sync box. Import always creates a new draft post — it never overwrites an existing one.', 'flexible-content-sync-for-acf' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="fcsa_import" />
				<?php wp_nonce_field( 'fcsa_import', 'fcsa_import_nonce' ); ?>
				<p>
					<label for="fcsa-import-file"><?php esc_html_e( 'Choose export file:', 'flexible-content-sync-for-acf' ); ?></label><br />
					<input
						type="file"
						id="fcsa-import-file"
						name="fcsa_file"
						accept=".json"
						required
						aria-required="true"
					/>
				</p>
				<?php submit_button( __( 'Import From File', 'flexible-content-sync-for-acf' ), 'secondary' ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_save_settings(): void {
		check_admin_referer( 'fcsa_save_settings', 'fcsa_settings_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'flexible-content-sync-for-acf' ) );
		}

		FCSA_Settings::save( wp_unslash( $_POST ) );

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => 'fcsa-settings', 'fcsa_notice' => 'success' ],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
