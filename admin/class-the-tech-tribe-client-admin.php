<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       thetechtribe.com
 * @since      1.0.0
 *
 * @package    The_Tech_Tribe_Client
 * @subpackage The_Tech_Tribe_Client/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    The_Tech_Tribe_Client
 * @subpackage The_Tech_Tribe_Client/admin
 * @author     Nigel Moore <help@thetechtribe.com>
 */
class The_Tech_Tribe_Client_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in The_Tech_Tribe_Client_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The The_Tech_Tribe_Client_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/the-tech-tribe-client-admin.css', array(), $this->version, 'all' );
		wp_enqueue_style( $this->plugin_name . 'bootstrap-5-iso', tttc_get_plugin_dir_url() . 'assets/css/bootstrap-iso-v5.min.css', array(), 'v5', 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in The_Tech_Tribe_Client_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The The_Tech_Tribe_Client_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/the-tech-tribe-client-admin.js', array( 'jquery' ), $this->version, false );
		wp_enqueue_script( $this->plugin_name . 'bootstrap-5-iso', tttc_get_plugin_dir_url() . 'assets/js/bootstrap-v5-.bundle.min.js', array( 'jquery' ), 'v5', false );

	}

	public function cron_jobs()
	{
		\TheTechTribeClient\CronJobs::get_instance()->init();
	}

}
