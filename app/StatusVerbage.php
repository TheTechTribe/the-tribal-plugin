<?php
namespace TheTribalPlugin;

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
                    'msg' => 'Your Tech Tribe plugin is now activated. Make sure you adjust your settings below.'
                ],
                'error' => [
                    'header' => 'UH-OH - THERE WAS AN ERROR',
                    'msg' => 'Uh-oh, it looks like the API key you entered was incorrect. Please copy and paste it again, making sure no extra spaces or characters come across. If it still fails, please email us at help@thetechtribe.com.'
                ],
                // Never read anywhere: a key that fails to verify uses api.error or the portal error mapping.
                //dead code, to be removed in future update
                'notverified' => [
                    'header' => 'Error',
                    'msg' => 'We couldn\'t confirm your API key. Please copy it again from your Tech Tribe portal and try again. If it still fails, please email help@thetechtribe.com.'
                ],
            ],
            'ac_tag' => [
                'error' => [
                    'header' => 'UH-OH - THERE WAS AN ERROR',
                    'msg' => 'Uh-oh, we couldn\'t confirm that your Tech Tribe membership includes the blog content. Please email us at help@thetechtribe.com and we\'ll check your account for you.'
                ],
            ],
            'domain' => [
                'error' => [
                    'header' => 'UH-OH - ACTIVATION ISSUE',
                    'msg'   => 'Your API key has already been activated on a different domain name to this one. If you are changing the URL of your website, please email help@thetechtribe.com with your new domain and we\'ll update it for you.'
                ],
            ],
            'settings' => [
                'success' => [
                    'header' => 'Success',
                    'msg' => 'Your settings have been saved.'
                ]
            ],
            'import' => [
                'success' => [
                    'header' => 'Success',
                    'msg' => 'Your new blog posts have been imported.'
                ],
                'error' => [
                    'header' => 'UH-OH - THE IMPORT DIDN\'T WORK',
                    'msg' => 'We couldn\'t import your blog posts this time. Please try again shortly and if it still continues - please email help@thetechtribe.com.'
                ],
                'nothing' => [
                    'header' => 'CHECK SUCCESSFUL',
                    'msg' => 'There were no new blog posts to import. We release new posts monthly and your plugin will automatically check for these every 24 hours.'
                ],
                'imported' => [
                    'header' => 'Success',
                    'msg' => 'Blog posts imported'
                ],
                'expired' => [
                    'header' => 'PLEASE RELOAD THE PAGE',
                    'msg' => 'This page has been open for a while. Please reload it and click START MANUAL IMPORT again.'
                ],
                'not_allowed' => [
                    'header' => 'ADMINISTRATORS ONLY',
                    'msg' => 'Only a site administrator can run a manual import. Please ask an administrator to do this for you.'
                ],
                'running' => [
                    'header' => 'UH-OH - IMPORT ALREADY RUNNING',
                    'msg' => 'Another import is already in progress (it may have been started automatically). Please wait a few minutes and check the Import Status again.'
                ]
            ],
            // Never read anywhere: saving settings uses settings.success.
            //dead code, to be removed in future update
            'default_dashboard' => [
                'success' => [
                    'header' => 'Success',
                    'msg' => 'Settings updated.'
                ],
                'error' => [
                    'header' => 'Error',
                    'msg' => 'Settings not updated.'
                ]
            ],
            'general_error' => [
                'timeout' => [
                    'header' => 'UH-OH - TIME-OUT ISSUE',
                    'msg' => 'For some reason, there has been a time-out issue trying to run this task. Please try again shortly and if it still continues - please email help@thetechtribe.com.'
                ],
                'error' => [
                    'header' => 'UH-OH - THERE WAS AN ERROR',
                    'msg' => 'Uh-oh, something went wrong. Please try again shortly. If this continually happens, please let us know via email at help@thetechtribe.com and let us know what you were trying to do at the time.'
                ],
                'rate_limited' => [
                    'header' => 'UH-OH - TOO MANY ATTEMPTS',
                    'msg' => 'Your website has checked in with us too many times in a short period. Please wait a minute and try again. If it still continues - please email help@thetechtribe.com.'
                ],
                'unavailable' => [
                    'header' => 'UH-OH - SERVICE UNAVAILABLE',
                    'msg' => 'The Tech Tribe content service is temporarily unavailable. Please try again shortly and if it still continues - please email help@thetechtribe.com.'
                ],
            ],
        ];

        if($key != '' && isset($arrStatus[$key])){
            return $arrStatus[$key];
        }

        return $arrStatus;
		
    }
    
}