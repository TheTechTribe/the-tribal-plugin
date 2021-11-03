<?php
namespace TheTechTribeClient;

use TheTechTribeClient\APIPortal;
use WP_Error;
use WP_REST_Response;

class Authenticate
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

    public function auth( $args = [] )
    {
        //get this in DB
		$ret = '';
		
		$apiKey = $args['user_api_key'] ?? '';
		$userDomain = $args['user_domain'] ?? '';

		if(is_user_logged_in()) {
			$current_user = wp_get_current_user();
		
			$urlAPIPortal = new APIPortal();
			$url = $urlAPIPortal->url() . 'auth';
			
			$response = wp_remote_post( $url, [
				'timeout'     => 45,
				'body'        => [
					'user_domain'		=> $userDomain,
					'user_api_key'		=> $apiKey,
				],
			]);
			
			$resCode = wp_remote_retrieve_response_code($response);
			$resBody = wp_remote_retrieve_body($response);
			$toArrayBody = json_decode($resBody, 1);
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				$ret = $error_message;
			} else {
				$ret = $toArrayBody;
			}
		}

		return rest_ensure_response($ret);
    }
    
}