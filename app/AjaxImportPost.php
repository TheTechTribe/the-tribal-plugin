<?php
namespace TheTribalPlugin;

class AjaxImportPost
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
        add_action('wp_ajax_ttt_import_post', [$this, 'import']);
        //add_action('wp_ajax_ttt_import_post', [$this, 'import']);
    }

    public function import()
    {
		$importVerbage = \TheTribalPlugin\StatusVerbage::get_instance()->get('import');

		if ( ! check_ajax_referer( 'ttt_import_post_nonce', 'nonce', false ) ) {
			Logger::get_instance()->warning('Manual import (AJAX) refused: security check failed (page likely open too long)', ['user_id' => get_current_user_id()]);
			$this->sendRefusal( $importVerbage['expired'] );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			Logger::get_instance()->warning('Manual import (AJAX) refused: user lacks manage_options', ['user_id' => get_current_user_id()]);
			$this->sendRefusal( $importVerbage['not_allowed'] );
		}

		Logger::get_instance()->info('Import triggered manually (AJAX)', ['user_id' => get_current_user_id()]);

        $ret =  \TheTribalPlugin\ImportPost::get_instance()->import();
		
		$returnCode = $ret->data['code'];
		$returnMsg = $ret->data['msg'];
		$returnMsgHeader = $ret->data['msg-header'] ?? '';
		$msgContent = '';

		if(isset($ret->data['code']) && ! $ret->data['success']) {
			// ImportPost always returns 'msg' as a plain string, so the ['errors']['invalid'] lookup never matches.
			//dead code, to be removed in future update
			$rawMsg = isset($ret->data['msg']['errors']['invalid'][0]) ? $ret->data['msg']['errors']['invalid'][0] : $ret->data['msg'];
			$returnMsg = esc_html($rawMsg);
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

		$getLastCheck = \TheTribalPlugin\HealthStatus::get_instance()->lastChecked([
			'action' => 'r',
		]);
		
		$getLastImport = \TheTribalPlugin\HealthStatus::get_instance()->lastDownload([
			'action' => 'r',
		]);
		
		$dateGetLastImport = '';
		if( $getLastImport && $getLastImport != ''){
			$dateGetLastImport = date_i18n('d F Y h:i A', strtotime($getLastImport));
		}

		$arrReturnMsg = [
			'code' 						=> $returnCode,
			'msg_header' 				=> $returnMsgHeader,
			'msg' 						=> $returnMsg,
			'status' 					=> $ret->status,
			'msg_content' 				=> $msgContent,
			'action' 					=> true,
			'last_check' 				=> date_i18n('d F Y h:i A', strtotime($getLastCheck)),
			'last_successfull_import' 	=> $dateGetLastImport
		];

		Logger::get_instance()->debug('Manual import (AJAX) response sent to browser', $arrReturnMsg);

		// The admin JS reads response.data either way; the success flag now reflects the import.
		if( ! empty($ret->data['success']) ) {
			wp_send_json_success($arrReturnMsg);
		}
		wp_send_json_error($arrReturnMsg);
    }

	/**
	 * Refuse the import with a message the admin JS can show (it reads response.data).
	 */
	private function sendRefusal( $verbage )
	{
		wp_send_json_error( [
			'code'        => 'error',
			'msg_header'  => $verbage['header'],
			'msg'         => $verbage['msg'],
			'msg_content' => '',
		], 403 );
	}

}