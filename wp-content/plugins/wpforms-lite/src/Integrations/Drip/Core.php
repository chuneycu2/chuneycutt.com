<?php

namespace WPForms\Integrations\Drip;

use WPForms\Integrations\Drip\Settings\FormBuilder;
use WPForms\Integrations\Drip\Settings\PageIntegrations;
use WPForms\Providers\Provider\Core as ProviderCore;

/**
 * Drip provider core.
 *
 * @since 2.0.5
 */
class Core extends ProviderCore {

	/**
	 * Priority for the provider, which affects its loading and placement order.
	 *
	 * Lower than the default priority of the addon providers, so Drip comes first
	 * in the builder Marketing panel and on the Settings > Integrations page.
	 *
	 * @since 2.0.5
	 */
	const PRIORITY = 5;

	/**
	 * Unique provider slug. Kept from the Drip addon so saved accounts and connections stay valid.
	 *
	 * @since 2.0.5
	 */
	const SLUG = 'drip';

	/**
	 * Constructor.
	 *
	 * @since 2.0.5
	 */
	public function __construct() {

		parent::__construct(
			[
				'slug' => self::SLUG,
				'name' => 'Drip',
				'icon' => WPFORMS_PLUGIN_URL . 'assets/images/addon-icon-drip.png',
			]
		);
	}

	/**
	 * The object that processes a submitted entry.
	 *
	 * @since 2.0.5
	 *
	 * @return Process
	 */
	public function get_process(): Process {

		static $process;

		if ( ! $process ) {
			$process = new Process( $this );
		}

		return $process;
	}

	/**
	 * The object that displays the provider on Settings > Integrations.
	 *
	 * @since 2.0.5
	 *
	 * @return PageIntegrations
	 */
	public function get_page_integrations(): PageIntegrations {

		static $integration;

		if ( ! $integration ) {
			$integration = new PageIntegrations( $this );
		}

		return $integration;
	}

	/**
	 * The object that displays the provider in the Form Builder.
	 *
	 * @since 2.0.5
	 *
	 * @return FormBuilder
	 */
	public function get_form_builder(): FormBuilder {

		static $builder;

		if ( ! $builder ) {
			$builder = new FormBuilder( $this );
		}

		return $builder;
	}
}
