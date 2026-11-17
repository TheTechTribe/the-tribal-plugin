<?php
/**
 * "START MANUAL IMPORT" button (wp_ajax_ttt_import_post).
 *
 * @package The_Tribal_Plugin
 */

class Test_Ajax_Import extends TTT_Ajax_TestCase {

	private function callImport( $nonce = null ) {
		$_POST['nonce'] = $_REQUEST['nonce'] = null === $nonce ? wp_create_nonce( 'ttt_import_post_nonce' ) : $nonce;
		try {
			$this->_handleAjax( 'ttt_import_post' );
		} catch ( WPAjaxDieContinueException $e ) {
			// wp_send_json_* ends the request this way.
		}
		return json_decode( $this->_last_response, true );
	}

	public function test_rejects_an_invalid_nonce_with_a_friendly_message() {
		wp_set_current_user( $this->adminId );

		$response = $this->callImport( 'bad' );

		$this->assertFalse( $response['success'] );
		$this->assertSame( $this->verbage( 'import', 'expired' )['msg'], $response['data']['msg'] );
		$this->assertCount( 0, $this->importedPosts() );
	}

	public function test_rejects_users_without_manage_options_with_a_friendly_message() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$response = $this->callImport();

		$this->assertFalse( $response['success'] );
		$this->assertSame( $this->verbage( 'import', 'not_allowed' )['msg'], $response['data']['msg'] );
		$this->assertCount( 0, $this->importedPosts() );
	}

	public function test_a_successful_import_returns_success_with_the_imported_titles() {
		wp_set_current_user( $this->adminId );
		$this->portalPosts = [ 901 => $this->portalPost( 1, [ 'title' => 'Fish & Chips <script>x</script>' ] ) ];

		$response = $this->callImport();

		$this->assertTrue( $response['success'], 'A successful import must be reported as success' );
		$this->assertSame( 'success', $response['data']['code'] );
		$this->assertSame( 'Success', $response['data']['msg_header'] );
		$this->assertStringContainsString( '<li>Fish &amp; Chips &lt;script&gt;x&lt;/script&gt;</li>', $response['data']['msg_content'] );
		$this->assertNotEmpty( $response['data']['last_check'] );
		$this->assertNotEmpty( $response['data']['last_successfull_import'] );
	}

	public function test_a_failed_import_returns_an_error_with_the_portal_message() {
		wp_set_current_user( $this->adminId );
		$this->scenario = 'invalid';

		$response = $this->callImport();

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'error', $response['data']['code'] );
		$this->assertSame( $this->verbage( 'api', 'error' )['header'], $response['data']['msg_header'] );
		$this->assertSame( esc_html( $this->verbage( 'api', 'error' )['msg'] ), $response['data']['msg'] );
	}

	public function test_nothing_to_import_is_not_reported_as_an_error() {
		wp_set_current_user( $this->adminId );
		$this->scenario = 'empty';

		$response = $this->callImport();

		$this->assertTrue( $response['success'] );
		$this->assertSame( $this->verbage( 'import', 'nothing' )['header'], $response['data']['msg_header'] );
	}
}
