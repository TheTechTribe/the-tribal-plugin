<?php
namespace TheTechTribeClient;

use TheTechTribeClient\APIPortal;
use WP_Error;
use WP_REST_Response;
use User;

/**
 * Cron Jobs
 */
class CronJobs
{
    /**
	 * instance of this class
	 *
	 * @since 0.0.1
	 * @access protected
	 * @var	null
	 * */
	protected static $instance = null;

	/**
	 * Return an instance of this class.
	 *
	 * @since     0.0.1
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		/*
		 * - Uncomment following lines if the admin class should only be available for super admins
		 */
		/* if( ! is_super_admin() ) {
			return;
		} */

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function __construct(){}

    public function init()
    {
        $this->scheduleSyncBlog();
    }

    public function scheduleSyncBlog()
    {
		HealthStatus::get_instance()->importJobVia([
			'action' => 'u',
			'value' => date('Y/m/d H:i:s') . ' : Cron Jobs'
		]);

        return \TheTechTribeClient\ImportPost::get_instance()->import();
		
    }
    
}