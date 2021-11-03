<?php
namespace TheTechTribeClient;

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

		$defaultAuthor 	= WPOptions::get_instance()->defaultAuthor();

		$users = get_users();

        $template = tttc_get_plugin_dir() . 'admin/partials/dashboard/main.php';
        if ( is_file( $template ) ) {
            require_once $template;
        }
    }
    
	public function update()
	{
		$userId = 0;
	
		if( $_POST )
		{
			$statusVerbage = \TheTechTribeClient\StatusVerbage::get_instance()->get('api');

			$arrReturnMsg = [
				'code' => 'error',
				'msg-header' => $statusVerbage['error']['header'],
				'msg' => $statusVerbage['error']['msg'],
				'status' => 200,
				'msg-content' => '',
				'action' => false
			];

			if ( 
				empty( $_POST['_wpnonce'] ) 
				&& ! wp_verify_nonce( $_POST['_wpnonce'], 'ttt_client_update_plugin' ) 
				&& check_admin_referer( $_POST['_wp_http_referer'], 'ttt_client_update_plugin' ) 
			) {
				return;
			}

			$this->updateSync($_POST);
			// $updateApiKey = $this->updateAPIKey($_POST);
			// if( $updateApiKey )
			// {
			// 	return $updateApiKey;
			// }

			$apiKeyDB	= WPOptions::get_instance()->apiKey(['action' => 'r']);
			if( isset($_POST['ttt_api_key']) && $apiKeyDB != $_POST['ttt_api_key']) {
				$updateApiKey = $this->updateAPIKey($_POST);
				if( $updateApiKey )
				{
					return $updateApiKey;
				}
			} else {
				$settingsVerbage = \TheTechTribeClient\StatusVerbage::get_instance()->get('settings');
				$arrReturnMsg = [
					'code' => 'success',
					'msg-header' => $settingsVerbage['success']['header'],
					'msg' => $settingsVerbage['success']['msg'],
					'status' => 200,
					'msg-content' => '',
					'action' => false
				];
			}
			
			$forceImport = $this->forceImport($_POST);
			if( $forceImport )
			{
				return $forceImport;
			}
			
			return $arrReturnMsg;
		}
	}

	private function forceImport($request)
	{
		if( $_POST && isset($request['action']) && $request['action'] == 'ttt_force_import' ){
			$arrReturnMsg['action'] = true;

			tttImportJobVia('Manual Import');

			$ret = \TheTechTribeClient\ImportPost::get_instance()->import();
			
			$returnCode = $ret->data['code'];
			$returnMsg = $ret->data['msg'];
			$returnMsgHeader = $ret->data['msg-header'] ?? '';
			$msgContent = '';

			if(isset($ret->data['code']) && ! $ret->data['success']) {
				$returnMsg = isset($ret->data['msg']['errors']['invalid'][0]) ? $ret->data['msg']['errors']['invalid'][0] : $ret->data['msg'];
				$returnCode = (!$ret->data['success']) ? 'error':'';
			}

			$msgContent = '';
			if(isset($ret->data['summary']) && isset($ret->data['post_count_imported']) && $ret->data['post_count_imported'] > 0) {
				$statusVerbage = \TheTechTribeClient\StatusVerbage::get_instance()->get('import');

				$msgContent .= '<p>';
				$msgContent .= '('. $ret->data['post_count_imported'].') '.$statusVerbage['imported']['msg'].' : ';
				$msgContent .= '</p>';

				$msgContent .= '<ul>';
				foreach($ret->data['summary']['post'] as $post) {
					$msgContent .= '<li>';
					$msgContent .= $post['title'];
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

			$publishPosts 	= $request['ttt_publish_post'];

			WPOptions::get_instance()->publishPosts([
				'action' 	=> 'u',
				'value' 	=> $publishPosts
			]);
			
			$defaultAuthor 	= $request['ttt_post_author'];
			WPOptions::get_instance()->defaultAuthor([
				'action' 	=> 'u',
				'value' 	=> $defaultAuthor
			]);

			$arrReturnMsg = [
				'code' 		=> 'success',
				'msg' 		=> 'Sucessfully Updated',
				'status' 	=> 200,
				'action' 	=> true
			];

			return $arrReturnMsg;
		}
		return false;
	}

	private function updateAPIKey($request)
	{
		if( $_POST && isset($request['action']) && $request['action'] == 'ttt_update_dashboard_user' ){
			$arrReturnMsg['action'] = true;

			if(
				isset($request['ttt_api_key']) 
				&& !empty(trim($request['ttt_api_key'])) 
			){
				$apiKey	= $request['ttt_api_key'];
				
				//verify the auth
				$verifyArgs = [
					'user_domain' 	=> site_url(),
					'user_api_key' 	=> $apiKey,
				];

				$ret = \TheTechTribeClient\User::get_instance()->isValid($verifyArgs);

				//insert api key
				WPOptions::get_instance()->apiKey([
					'action' 	=> 'u',
					'value' 	=> $_POST['ttt_api_key']
				]);
				
				//insert domain used
				WPOptions::get_instance()->domain([
					'action' 	=> 'u',
					'value' 	=> site_url()
				]);
	
				$returnCode = $ret->data['code'] ?? 'error';
				$returnMsg = $ret->data['msg'] ?? '';
				$returnMsgHeader = $ret->data['msg-header'] ?? '';

				if(isset($ret->data['code']) && ! $ret->data['success']) {
					$returnMsg = isset($ret->data['msg']['errors']['invalid'][0]) ? $ret->data['msg']['errors']['invalid'][0] : $ret->data['msg'];
					$returnCode = (!$ret->data['success']) ? 'error':'';
					tttSetKeyActive(0);
					tttRemoveCronJob();
				}

				if(!isset($ret->data['code']) && !is_array($ret->data)){
					$returnMsg = $ret->data;
					tttSetKeyActive(0);
					tttRemoveCronJob();
				}

				if(isset($ret->data['code']) && $ret->data['success']) {
					tttSetKeyActive(1);
					tttInitCronJob();
				}
				
				$arrReturnMsg = [
					'code' 		=> $returnCode,
					'msg-header' => $returnMsgHeader,
					'msg' 		=> $returnMsg,
					'status' 	=> $ret->status,
					'action' 	=> true
				];

				tttVerifyChecked($returnCode, $returnMsg);
				
				return $arrReturnMsg;
			}
		}
		return false;
	}

}