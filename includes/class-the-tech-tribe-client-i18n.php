<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       thetechtribe.com
 * @since      1.0.0
 *
 * @package    The_Tech_Tribe_Client
 * @subpackage The_Tech_Tribe_Client/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    The_Tech_Tribe_Client
 * @subpackage The_Tech_Tribe_Client/includes
 * @author     Nigel Moore <help@thetechtribe.com>
 */
class The_Tech_Tribe_Client_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'the-tech-tribe-client',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
