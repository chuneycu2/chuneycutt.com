<?php

namespace WPForms\Lite\Frontend;

use WPForms\Frontend\ThemesEditor as ThemesEditorBase;
use WPForms\Lite\Integrations\Gutenberg\ThemesData;

/**
 * Front-end Form Themes editor, Lite.
 *
 * @since 2.0.5
 */
class ThemesEditor extends ThemesEditorBase {

	/**
	 * Initialize.
	 *
	 * @since 2.0.5
	 */
	public function init(): void {

		$this->themes_data_obj = new ThemesData();

		parent::init();
	}
}
