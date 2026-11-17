<?php
namespace TheTribalPlugin;

use TheTribalPlugin\APIPortal;
use WP_Error;
use WP_REST_Response;
use User;

/**
 * Get the Post
 */
class Post
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

    public function get( $args = [] )
    {
		$apiKey = WPOptions::get_instance()->apiKey();

		if($apiKey == '') {
			Logger::get_instance()->warning('Portal request post/get skipped: no API key stored');

			// Same shape as a portal 401, so callers show the "wrong API key" message.
			return ['ok' => false, 'status' => 401, 'code' => 'invalid', 'msg' => 'No API key stored', 'data' => []];
		}

		$body = \TheTribalPlugin\User::get_instance()->getAccountKeys();
		// Keep the original WordPress markup (block comments, embeds, classes); v2 sanitizes by default.
		$body['content_format'] = 'raw';

		return APIPortal::get_instance()->post('post/get', $body);
    }
    
}