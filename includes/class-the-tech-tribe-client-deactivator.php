<?php
use TheTechTribeClient\WPOptions;
use TheTechTribeClient\HealthStatus;
/**
 * Fired during plugin deactivation
 *
 * @link       thetechtribe.com
 * @since      1.0.0
 *
 * @package    The_Tech_Tribe_Client
 * @subpackage The_Tech_Tribe_Client/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    The_Tech_Tribe_Client
 * @subpackage The_Tech_Tribe_Client/includes
 * @author     Nigel Moore <help@thetechtribe.com>
 */
class The_Tech_Tribe_Client_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		tttRemoveInDbOptions();
	}

}
