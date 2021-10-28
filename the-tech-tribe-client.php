<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              thetechtribe.com
 * @since             1.0.0
 * @package           The_Tech_Tribe_Client
 *
 * @wordpress-plugin
 * Plugin Name:       The Tribal Plugin
 * Plugin URI:        thetechtribe.com
 * Description:       This plugin is for members of The Tech Tribe to manage features such as Automated Blog Posting etc.
 * Version:           1.0.0
 * Author:            The Tech Tribe
 * Author URI:        thetechtribe.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       the-tech-tribe-client
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'THE_TECH_TRIBE_CLIENT_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-the-tech-tribe-client-activator.php
 */
function activate_the_tech_tribe_client() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-the-tech-tribe-client-activator.php';
	The_Tech_Tribe_Client_Activator::activate();

	if ( ! wp_next_scheduled( 'ttt_user_cron_hook' ) ) {
		wp_schedule_event( time(), 'daily', 'ttt_user_cron_exec' );
	}
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-the-tech-tribe-client-deactivator.php
 */
function deactivate_the_tech_tribe_client() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-the-tech-tribe-client-deactivator.php';
	The_Tech_Tribe_Client_Deactivator::deactivate();

	$timestamp = wp_next_scheduled( 'ttt_user_cron_hook' );
    wp_unschedule_event( $timestamp, 'ttt_user_cron_hook' );

	wp_clear_scheduled_hook( 'ttt_user_cron_hook' );
}

register_activation_hook( __FILE__, 'activate_the_tech_tribe_client' );
register_deactivation_hook( __FILE__, 'deactivate_the_tech_tribe_client' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-the-tech-tribe-client.php';

require plugin_dir_path( __FILE__ ) . 'helpers/utilities.php';

require_once plugin_dir_path(__FILE__) . '/vendor/autoload.php';

function tttc_get_plugin_details(){
	// Check if get_plugins() function exists. This is required on the front end of the
	// site, since it is in a file that is normally only loaded in the admin.
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$ret = get_plugins();
	return $ret['the-tech-tribe-client/the-tech-tribe-client.php'];
}

function tttc_get_text_domain(){
	$ret = tttc_get_plugin_details();
	return $ret['TextDomain'];
}

function tttc_get_plugin_dir(){
	return plugin_dir_path( __FILE__ );
}

/**
* get the plugin url path.
**/
function tttc_get_plugin_dir_url() {
	return plugin_dir_url( __FILE__ );
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_the_tech_tribe_client() {

	$plugin = new The_Tech_Tribe_Client();
	$plugin->run();
	
	\TheTechTribeClient\WPMenu::get_instance()->init();
}
add_action('plugins_loaded', 'run_the_tech_tribe_client');

function ttt_init_client()
{
	if(!is_admin())
	{
		
	}
}
add_action('init', 'ttt_init_client');

function ttt_blog_cron_intervals($schedules) {
    $schedules['ttt_every_four_hours'] = array(
        'interval' => 14400,
        'display' => __('Every 4 Hours')
    );
	
    $schedules['ttt_every_six_hours'] = array(
        'interval' => 21600,
        'display' => __('Every 6 Hours')
    );
	
	$schedules[ 'every-5-minutes' ] = array( 'interval' => 5 * MINUTE_IN_SECONDS, 'display' => __( 'Every 5 minutes' ) );
    
	return $schedules;
}
//add_filter( 'cron_schedules', 'ttt_blog_cron_intervals');

function ttt_user_cron_exec()
{
	\TheTechTribeClient\CronJobs::get_instance()->init();
}
add_action( 'ttt_user_cron_hook', 'ttt_user_cron_exec' );