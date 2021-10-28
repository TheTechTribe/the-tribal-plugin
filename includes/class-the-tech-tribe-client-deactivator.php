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
		$userId = get_current_user_id();

		//delete api key
		WPOptions::get_instance()->apiKey([
			'action' 	=> 'd',
		]);

		//delete email used
		WPOptions::get_instance()->emailRegistered([
			'action' 	=> 'd',
		]);
		
		//delete domain used
		WPOptions::get_instance()->dateImportBlog([
			'action' 	=> 'd',
		]);

		//delete domain used
		WPOptions::get_instance()->domain([
			'action' 	=> 'd',
		]);

		//delete domain used
		WPOptions::get_instance()->publishPosts([
			'action' 	=> 'd',
		]);

		//delete author
		WPOptions::get_instance()->defaultAuthor([
			'action' 	=> 'd',
		]);
		
		HealthStatus::get_instance()->lastDownload([
			'action' 	=> 'd',
		]);
		
		HealthStatus::get_instance()->lastChecked([
			'action' 	=> 'd',
		]);
		
		HealthStatus::get_instance()->verifyChecked([
			'action' 	=> 'd',
		]);
		
		HealthStatus::get_instance()->lastCheckedStatus([
			'action' 	=> 'd',
		]);
		
		HealthStatus::get_instance()->importJobStatus([
			'action' 	=> 'd',
		]);
		
		HealthStatus::get_instance()->importJobVia([
			'action' 	=> 'd',
		]);
		
		HealthStatus::get_instance()->importJobStart([
			'action' 	=> 'd',
		]);
		
		HealthStatus::get_instance()->importJobEnd([
			'action' 	=> 'd',
		]);

		HealthStatus::get_instance()->importLogReturnPost([
			'action' 	=> 'd',
		]);

		HealthStatus::get_instance()->isActive([
			'action' 	=> 'd',
		]);
	}

}
