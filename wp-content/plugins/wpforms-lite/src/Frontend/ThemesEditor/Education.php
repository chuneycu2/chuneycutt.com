<?php

namespace WPForms\Frontend\ThemesEditor;

use WPForms\Admin\Education\StringsTrait;

/**
 * The builder's education modals in the front-end themes editor: the upsell of the Pro sections.
 *
 * Loads the same scripts and strings the builder and the block use, so the modals are the same.
 *
 * @since 2.0.5
 */
class Education {

	use StringsTrait;

	/**
	 * The UTM medium of the modal links: the editor opens on the form preview page.
	 *
	 * @since 2.0.5
	 */
	const UTM_MEDIUM = 'Form Preview';

	/**
	 * Get the UTM medium of the upgrade links.
	 *
	 * @since 2.0.5
	 *
	 * @return string
	 */
	protected function get_upgrade_utm_medium(): string {

		return self::UTM_MEDIUM;
	}

	/**
	 * Enqueue the education script and its strings.
	 *
	 * @since 2.0.5
	 *
	 * @return void
	 */
	public function enqueue(): void {

		$min = wpforms_get_min_suffix();

		wp_enqueue_script(
			'wpforms-admin-education-core',
			WPFORMS_PLUGIN_URL . "assets/js/admin/education/core{$min}.js",
			[ 'jquery', 'jquery-confirm' ],
			WPFORMS_VERSION,
			true
		);

		wp_localize_script(
			'wpforms-admin-education-core',
			'wpforms_education',
			$this->get_js_strings()
		);
	}
}
