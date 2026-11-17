<?php
namespace TheTribalPlugin;

/**
 * Dashboard
 */
class Dashboard
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
		$retUpdate = $this->update();

		if($retUpdate) {
			$alertArgs = [
				'alert' => ($retUpdate['code'] == 'error') ? 'danger':'success',
				'code'  => $retUpdate['code'],
				'msg'   => $retUpdate['msg'],
				'msg-header' => $retUpdate['msg-header'] ?? '',
				'msg-content' => $retUpdate['msg-content'] ?? '',
				'close' => true
			];
		}

		$apiKey = WPOptions::get_instance()->apiKey();
		
		$publishPosts = WPOptions::get_instance()->publishPosts();
		
		$action = 'ttt_update_dashboard_user';
		if(!$apiKey && $apiKey == ''){
			$action = 'activate';
		}

		$lastDownload = HealthStatus::get_instance()->lastDownload([
            'action' => 'r',
        ]);
		
		$lastChecked = HealthStatus::get_instance()->lastChecked([
            'action' => 'r',
        ]);

		$nextScheduleCron = tttGetNextCronTimeDate();
		if( ! $nextScheduleCron ) {
			Logger::get_instance()->warning('Daily import cron (ttt_user_cron_hook) is not scheduled');
		}

		$defaultAuthor 	= WPOptions::get_instance()->defaultAuthor();

		$users = get_users();

        $template = tttc_get_plugin_dir() . 'admin/partials/dashboard/main.php';
		$partTemplateApi = tttc_get_plugin_dir() . 'admin/partials/dashboard/api.php';
		$partTemplateSettings = tttc_get_plugin_dir() . 'admin/partials/dashboard/settings.php';
		$partTemplateImport = tttc_get_plugin_dir() . 'admin/partials/dashboard/import.php';
		$partTemplateLogs = tttc_get_plugin_dir() . 'admin/partials/dashboard/logs.php';

        if ( is_file( $template ) ) {
            require_once $template;
        }
    }
    
	public function update()
	{
		if( $_POST )
		{
			// check_admin_referer() already stops the page itself when the check fails, so this never runs.
			//dead code, to be removed in future update
			if ( ! check_admin_referer( 'ttt_client_update_plugin' ) ) {
				wp_die( __( 'This page has been open for a while. Please go back, reload the page and try again.', 'the-tech-tribe' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'Only a site administrator can change these settings. Please ask an administrator to do this for you.', 'the-tech-tribe' ) );
			}

			Logger::get_instance()->captureFatalErrors();
			Logger::get_instance()->info('Dashboard form submitted', [
				'action'  => isset($_POST['action']) ? sanitize_text_field(wp_unslash($_POST['action'])) : '',
				'user_id' => get_current_user_id(),
			]);

			$generalErrorVerbage = tttThrowGeneralErrorMsg();

			$arrReturnMsg = [
				'code' => 'error',
				'msg-header' => $generalErrorVerbage['header'],
				'msg' => $generalErrorVerbage['msg'],
				'status' => 200,
				'msg-content' => '',
				'action' => false
			];

			$updateSync = $this->updateSync($_POST);
						
			if($updateSync){
				return $updateSync;
			}

			if( isset($_POST['action']) && $_POST['action'] == 'ttt_clear_logs' ) {
				Logger::get_instance()->clear();
				Logger::get_instance()->info('Log cleared', ['user_id' => get_current_user_id()]);

				return [
					'code' 		 => 'success',
					'msg-header' => 'Success',
					'msg' 		 => 'Log cleared.',
					'status' 	 => 200,
					'action' 	 => true
				];
			}

			if( isset($_POST['ttt_api_key']) && trim($_POST['ttt_api_key']) != '' ) {
				$updateApiKey = $this->updateAPIKey($_POST);

				if( $updateApiKey )
				{
					return $updateApiKey;
				}
			} 
			
			$forceImport = $this->forceImport($_POST);
			if( $forceImport )
			{
				return $forceImport;
			}
		}
	}

	private function forceImport($request)
	{
		if( $_POST && isset($request['action']) && $request['action'] == 'ttt_force_import' ){
			$arrReturnMsg['action'] = true;

			Logger::get_instance()->info('Import triggered manually (form)', ['user_id' => get_current_user_id()]);
			tttImportJobVia('Manual Import');

			$ret = \TheTribalPlugin\ImportPost::get_instance()->import();
			
			$returnCode = $ret->data['code'];
			$returnMsg = $ret->data['msg'];
			$returnMsgHeader = $ret->data['msg-header'] ?? '';
			$msgContent = '';

			if(isset($ret->data['code']) && ! $ret->data['success']) {
				// ImportPost always returns 'msg' as a plain string, so the ['errors']['invalid'] lookup never matches.
				//dead code, to be removed in future update
				$returnMsg = isset($ret->data['msg']['errors']['invalid'][0]) ? $ret->data['msg']['errors']['invalid'][0] : $ret->data['msg'];
				$returnCode = (!$ret->data['success']) ? 'error':'';
			}

			$msgContent = '';
			if(isset($ret->data['summary']) && isset($ret->data['post_count_imported']) && $ret->data['post_count_imported'] > 0) {
				$statusVerbage = \TheTribalPlugin\StatusVerbage::get_instance()->get('import');

				$msgContent .= '<p>';
				$msgContent .= '('. $ret->data['post_count_imported'].') '.$statusVerbage['imported']['msg'].' : ';
				$msgContent .= '</p>';

				$msgContent .= '<ul>';
				foreach($ret->data['summary']['post'] as $post) {
					$msgContent .= '<li>';
					$msgContent .= esc_html($post['title']);
					$msgContent .= '</li>';
				}
				$msgContent .= '</ul>';
			}

			$arrReturnMsg = [
				'code' 		=> $returnCode,
				'msg-header' => $returnMsgHeader,
				'msg' 		=> $returnMsg,
				'status' 	=> $ret->status,
				'msg-content' => $msgContent,
				'action' 	=> true
			];

			return $arrReturnMsg;
		}
		return false;
	}

	private function updateSync($request)
	{
		
		if( $_POST && isset($request['action']) && $request['action'] == 'ttt_update_dashboard_user' ){
			$arrReturnMsg['action'] = true;
			if(
				isset($request['ttt_publish_post'])
				|| isset($request['ttt_post_author'])
			) {
				$publishPosts 	= sanitize_text_field($request['ttt_publish_post']);
				WPOptions::get_instance()->publishPosts([
					'action' 	=> 'u',
					'value' 	=> $publishPosts
				]);
				
				$defaultAuthor 	= sanitize_text_field($request['ttt_post_author']);
				WPOptions::get_instance()->defaultAuthor([
					'action' 	=> 'u',
					'value' 	=> $defaultAuthor
				]);

				Logger::get_instance()->info('Settings saved', [
					'publish_setting' => $publishPosts,
					'default_author'  => $defaultAuthor,
					'user_id'         => get_current_user_id(),
				]);

				$settingsVerbage = StatusVerbage::get_instance()->get('settings');

				$arrReturnMsg = [
					'code' 		=> 'success',
					'msg-header' => $settingsVerbage['success']['header'],
					'msg' 		=> $settingsVerbage['success']['msg'],
					'status' 	=> 200,
					'action' 	=> true
				];

				return $arrReturnMsg;
			}
			
		}
		return false;
	}

	private function updateAPIKey($request)
	{
		if( $_POST && isset($request['action']) && $request['action'] == 'ttt_activate_key' ){
			$arrReturnMsg['action'] = true;

			if(
				isset($request['ttt_api_key']) 
				&& !empty(trim($request['ttt_api_key'])) 
			){
				$apiKey	= sanitize_text_field($request['ttt_api_key']);
				$apiVerbage = tttGetAPIVerbage();

				Logger::get_instance()->addSecret($apiKey);
				Logger::get_instance()->info('API key activation attempt', [
					'user_api_key'   => $apiKey,
					'domain'         => site_url(),
					'key_was_active' => tttIsKeyActive(),
					'user_id'        => get_current_user_id(),
				]);

				//verify the auth
				$verifyArgs = [
					'user_domain' 	=> site_url(),
					'user_api_key' 	=> $apiKey,
				];

				$ret = \TheTribalPlugin\User::get_instance()->isValid($verifyArgs);

				//insert api key
				WPOptions::get_instance()->apiKey([
					'action' 	=> 'u',
					'value' 	=> $apiKey
				]);
				
				//insert domain used
				WPOptions::get_instance()->domain([
					'action' 	=> 'u',
					'value' 	=> site_url()
				]);
	
				// ['ok', 'status', 'code', 'msg', 'data'], see APIPortal::post().
				if( $ret['ok'] ) {
					$returnCode = 'success';
					$returnMsgHeader = $apiVerbage['success']['header'];
					$returnMsg = $apiVerbage['success']['msg'];

					tttSetKeyActive(1);
				} else {
					$portalError = APIPortal::get_instance()->errorMessage($ret);
					$returnCode = 'error';
					$returnMsgHeader = $portalError['header'];
					$returnMsg = $portalError['msg'];

					// A key that could not be verified (for any reason) is not active.
					tttSetKeyActive(0);
				}

				$arrReturnMsg = [
					'code' 		=> $returnCode,
					'msg-header' => $returnMsgHeader,
					'msg' 		=> $returnMsg,
					'status' 	=> $ret['status'],
					'action' 	=> true
				];

				Logger::get_instance()->log(
					tttIsKeyActive() ? 'info' : 'error',
					'API key activation result',
					[
						'http_code'   => $ret['status'],
						'portal_code' => $ret['code'],
						'msg_header'  => $returnMsgHeader,
						'msg'         => $returnMsg,
						'key_active'  => tttIsKeyActive(),
					]
				);

				tttVerifyChecked($returnCode, $returnMsg);

				return $arrReturnMsg;
			}
		}
		return false;
	}

}