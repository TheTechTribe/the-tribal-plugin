<?php
namespace TheTechTribeClient;

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
        $ret =  \TheTechTribeClient\ImportPost::get_instance()->import();
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
			'msg_header' => $returnMsgHeader,
			'msg' 		=> $returnMsg,
			'status' 	=> $ret->status,
			'msg_content' => $msgContent,
			'action' 	=> true
		];
		wp_send_json_error($arrReturnMsg);
    }

}