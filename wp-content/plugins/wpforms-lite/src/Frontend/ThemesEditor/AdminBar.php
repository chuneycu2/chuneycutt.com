<?php

namespace WPForms\Frontend\ThemesEditor;

use WPForms\Frontend\ThemesEditor;
use WP_Admin_Bar;

/**
 * Admin bar entry point of the front-end themes editor and its NEW badge.
 *
 * @since 2.0.5
 */
class AdminBar {

	/**
	 * User meta key holding the timestamp of the first click.
	 *
	 * @since 2.0.5
	 */
	const META_KEY = 'wpforms_themes_editor_badge_clicked';

	/**
	 * Class of the item until the panel reveals it.
	 *
	 * @since 2.0.5
	 */
	const PENDING_CLASS = 'wpforms-themes-editor-pending';

	/**
	 * How long the NEW badge stays after the first click, in seconds.
	 *
	 * @since 2.0.5
	 */
	const BADGE_LIFETIME = WEEK_IN_SECONDS;

	/**
	 * Editor.
	 *
	 * @since 2.0.5
	 *
	 * @var ThemesEditor
	 */
	private $editor;

	/**
	 * Constructor.
	 *
	 * @since 2.0.5
	 *
	 * @param ThemesEditor $editor Editor.
	 */
	public function __construct( ThemesEditor $editor ) {

		$this->editor = $editor;
	}

	/**
	 * Register the admin bar item for users who can edit forms.
	 *
	 * Themes calling `wp_body_open` render the bar before any form, so the item
	 * cannot wait for a collected form: it registers hidden and the panel
	 * styles reveal it.
	 *
	 * @since 2.0.5
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar.
	 */
	public function register_item( WP_Admin_Bar $wp_admin_bar ): void {

		if ( ! $this->is_available() ) {
			return;
		}

		$title = esc_html__( 'Edit Form Theme', 'wpforms-lite' );

		// Own markup: the shared Education\Helpers::get_badge() styles load in admin only.
		if ( $this->is_badge_visible() ) {
			$title .= '<span class="wpforms-themes-editor-badge">' . esc_html__( 'New', 'wpforms-lite' ) . '</span>';
		}

		$wp_admin_bar->add_node(
			[
				'id'    => 'wpforms-themes-editor',
				'title' => $title,
				'href'  => '#',
				'meta'  => [
					'title' => esc_attr__( 'Edit Form Theme', 'wpforms-lite' ),
					'class' => self::PENDING_CLASS,
				],
			]
		);
	}

	/**
	 * Hide the pending item; printed with the admin bar stylesheet in the head.
	 *
	 * @since 2.0.5
	 *
	 * @return void
	 */
	public function hide_pending_item(): void {

		if ( ! $this->is_available() ) {
			return;
		}

		wp_add_inline_style( 'admin-bar', '#wp-admin-bar-wpforms-themes-editor.' . self::PENDING_CLASS . '{display:none}' );
	}

	/**
	 * Whether the item is offered on this site to this user.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	private function is_available(): bool {

		return is_user_logged_in() && $this->editor->is_supported() && wpforms_current_user_can( 'edit_forms' );
	}

	/**
	 * Whether the current user clicked the item at least once.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	public function is_clicked(): bool {

		return (bool) get_user_meta( get_current_user_id(), self::META_KEY, true );
	}

	/**
	 * Whether the NEW badge still shows: until a week after the first click.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	private function is_badge_visible(): bool {

		$first_click = (int) get_user_meta( get_current_user_id(), self::META_KEY, true );

		return ! $first_click || time() < $first_click + self::BADGE_LIFETIME;
	}

	/**
	 * Record the first click; later clicks leave the timer alone.
	 *
	 * @since 2.0.5
	 *
	 * @param int $user_id User ID.
	 */
	public function record_click( int $user_id ): void {

		if ( ! $user_id || get_user_meta( $user_id, self::META_KEY, true ) ) {
			return;
		}

		update_user_meta( $user_id, self::META_KEY, time() );
	}
}
