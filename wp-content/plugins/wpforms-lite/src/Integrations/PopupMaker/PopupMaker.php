<?php

namespace WPForms\Integrations\PopupMaker;

use WPForms\Integrations\IntegrationInterface;

/**
 * Popup Maker integration: forms in a popup get their own embed in the front-end themes editor.
 *
 * @since 2.0.5
 */
class PopupMaker implements IntegrationInterface {

	/**
	 * The z-index Popup Maker gives its overlay.
	 *
	 * @since 2.0.5
	 */
	const OVERLAY_Z_INDEX = 1999999999;

	/**
	 * Popup whose content is being rendered, 0 outside a popup.
	 *
	 * @since 2.0.5
	 *
	 * @var int
	 */
	private $popup_id = 0;

	/**
	 * Indicate if current integration is allowed to load.
	 *
	 * @since 2.0.5
	 *
	 * @return bool
	 */
	public function allow_load() {

		return class_exists( 'Popup_Maker', true );
	}

	/**
	 * Load an integration.
	 *
	 * @since 2.0.5
	 */
	public function load() {

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 2.0.5
	 */
	protected function hooks() {

		// Popup Maker renders and caches the content before the footer prints it: shortcodes run at
		// 8 and 11, blocks at 9, so the popup is known around them, as its own Gravity Forms bridge does.
		add_filter( 'pum_popup_content', [ $this, 'start_popup' ], 5, 2 );
		add_filter( 'pum_popup_content', [ $this, 'end_popup' ], 99 );
		add_filter( 'wpforms_frontend_embed_context_take', [ $this, 'set_popup_host' ] );

		// After the popups print at 10, once the panel is known to load on the page.
		add_action( 'wp_footer', [ $this, 'lift_themes_editor' ], 19 );
	}

	/**
	 * Remember the popup whose content is about to render.
	 *
	 * @since 2.0.5
	 *
	 * @param string|mixed $content  Popup content.
	 * @param int|mixed    $popup_id Popup ID.
	 *
	 * @return string|mixed
	 */
	public function start_popup( $content, $popup_id = 0 ) {

		$this->popup_id = absint( $popup_id );

		return $content;
	}

	/**
	 * Forget the popup once its content rendered.
	 *
	 * @since 2.0.5
	 *
	 * @param string|mixed $content Popup content.
	 *
	 * @return string|mixed
	 */
	public function end_popup( $content ) {

		$this->popup_id = 0;

		return $content;
	}

	/**
	 * Make the popup the host of a form rendered inside it, so its override is stored on the popup.
	 *
	 * @since 2.0.5
	 *
	 * @param array|mixed $context Embed context: type, id, host, legacy.
	 *
	 * @return array
	 */
	public function set_popup_host( $context ): array {

		$context = (array) $context;

		if ( $this->popup_id ) {
			$context['host'] = (string) $this->popup_id;
		}

		return $context;
	}

	/**
	 * Keep the themes editor and its modals above an open popup's overlay.
	 *
	 * @since 2.0.5
	 *
	 * @return void
	 */
	public function lift_themes_editor(): void {

		// The panel stylesheet may already have printed in the head, so this prints its own block.
		if ( ! wp_style_is( 'wpforms-themes-editor', 'enqueued' ) ) {
			return;
		}

		$panel = self::OVERLAY_Z_INDEX + 1;

		printf(
			'<style id="wpforms-themes-editor-popup-maker">%s</style>',
			esc_html(
				sprintf(
					'html.pum-open-overlay #wpforms-themes-editor.wpforms-themes-editor { z-index: %1$d; } html.pum-open-overlay .jconfirm, html.pum-open-overlay .media-modal-backdrop { z-index: %2$d; } html.pum-open-overlay .media-modal { z-index: %3$d; }',
					$panel,
					$panel + 1,
					$panel + 2
				)
			)
		);
	}
}
