<?php
namespace TheTechTribeClient;

/**
 * 
 */
class StatusVerbage
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

    public function get($key)
    {
		$arrStatus = [
            'api' => [
                'success' => [
                    'header' => 'WOOHOO - IT WORKED!',
                    'msg' => 'Your Tech Tribe Plugin is now Activated and you can manage your Settings on the other tabs.'
                ],
                'error' => [
                    'header' => 'Error',
                    'msg' => 'Error API Key.'
                ],
                'notverified' => [
                    'header' => 'Error',
                    'msg' => 'Error API Key.'
                ]
            ],
            'import' => [
                'success' => [
                    'header' => 'Success',
                    'msg' => 'Successfully imported blog(s).'
                ],
                'error' => [
                    'header' => 'Error',
                    'msg' => 'Un-Successfully imported blog(s).'
                ],
                'nothing' => [
                    'header' => 'Success',
                    'msg' => 'Nothing to import blog(s).'
                ],
                'imported' => [
                    'header' => 'Success',
                    'msg' => 'Blogs Imported'
                ]
            ],
            'default_dashboard' => [
                'success' => [
                    'header' => 'Success',
                    'msg' => 'Settings updated.'
                ],
                'error' => [
                    'header' => 'Error',
                    'msg' => 'Settings not updated.'
                ]
            ]
        ];

        if($key != '' && isset($arrStatus[$key])){
            return $arrStatus[$key];
        }

        return $arrStatus;
		
    }
    
}