<?php
/**
 * Dashboard forms: API key activation, settings, clearing the log.
 *
 * @package The_Tribal_Plugin
 */

class Test_Dashboard extends TTT_TestCase {

	/* ---------------------------------------------------------------- activation */

	public function test_activating_a_valid_key_stores_it_and_marks_it_active() {
		update_option( 'ttt_is_active', 0 );
		$this->scenario = 'posts';

		$result = $this->activateKey( 'NEWKEY-abcdef0123456789' );

		$this->assertSame( 'success', $result['code'] );
		$this->assertSame( $this->verbage( 'api', 'success' )['header'], $result['msg-header'] );
		$this->assertTrue( tttIsKeyActive() );
		$this->assertSame( 'NEWKEY-abcdef0123456789', get_option( 'ttt_api_key_blog_post' ) );
		$this->assertSame( site_url(), get_option( 'ttt_domain_registered_blog_post' ) );
	}

	public static function activation_error_scenarios() {
		return [
			'wrong api key'          => [ 'invalid', [ 'api', 'error' ] ],
			'membership tag missing' => [ 'ac_tag', [ 'ac_tag', 'error' ] ],
			'domain not approved'    => [ 'domain', [ 'domain', 'error' ] ],
			'rate limited'           => [ 'rate_limited', 'UH-OH - TOO MANY ATTEMPTS' ],
			'portal unavailable'     => [ 'unavailable', 'UH-OH - SERVICE UNAVAILABLE' ],
			'request timeout'        => [ 'timeout', [ 'general_error', 'timeout' ] ],
			'response not json'      => [ 'not_json', [ 'general_error', 'error' ] ],
		];
	}

	/**
	 * @dataProvider activation_error_scenarios
	 */
	public function test_a_key_that_cannot_be_verified_is_inactive_with_the_right_message( $scenario, $expected ) {
		$this->scenario = $scenario;

		$result = $this->activateKey();

		$this->assertSame( 'error', $result['code'] );
		if ( is_array( $expected ) ) {
			$verbage = $this->verbage( $expected[0], $expected[1] );
			$this->assertSame( $verbage['header'], $result['msg-header'] );
			$this->assertSame( $verbage['msg'], $result['msg'] );
		} else {
			$this->assertSame( $expected, $result['msg-header'] );
		}
		$this->assertFalse( tttIsKeyActive() );
	}

	public function test_activation_calls_portal_v2_auth_with_domain_and_key() {
		$this->activateKey( 'NEWKEY-abcdef0123456789' );

		$request = $this->lastPortalRequest();
		$this->assertSame( 'v2', $request['version'] );
		$this->assertSame( 'auth', $request['endpoint'] );
		$this->assertSame( site_url(), $request['body']['user_domain'] );
		$this->assertSame( 'NEWKEY-abcdef0123456789', $request['body']['user_api_key'] );
	}

	public function test_activation_rejects_an_invalid_nonce() {
		wp_set_current_user( $this->adminId );
		$_POST = $_REQUEST = [ 'action' => 'ttt_activate_key', 'ttt_api_key' => 'X-1234567890', '_wpnonce' => 'bad' ];

		$this->expectException( WPDieException::class );
		( new \TheTribalPlugin\Dashboard() )->update();
	}

	public function test_activation_rejects_users_without_manage_options() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$_POST = $_REQUEST = [ 'action' => 'ttt_activate_key', 'ttt_api_key' => 'X-1234567890', '_wpnonce' => wp_create_nonce( 'ttt_client_update_plugin' ) ];

		$this->expectException( WPDieException::class );
		( new \TheTribalPlugin\Dashboard() )->update();
	}

	/* ---------------------------------------------------------------- settings & log */

	public function test_saving_settings_stores_publish_mode_and_author() {
		$author = self::factory()->user->create( [ 'role' => 'author' ] );
		wp_set_current_user( $this->adminId );
		$_POST = $_REQUEST = [
			'action'           => 'ttt_update_dashboard_user',
			'ttt_publish_post' => 'auto',
			'ttt_post_author'  => (string) $author,
			'_wpnonce'         => wp_create_nonce( 'ttt_client_update_plugin' ),
		];

		$result = ( new \TheTribalPlugin\Dashboard() )->update();

		$this->assertSame( 'success', $result['code'] );
		$this->assertSame( 'auto', get_option( 'ttt_auto_manual_publish_post' ) );
		$this->assertSame( (string) $author, get_option( 'ttt_auto_post_author' ) );
	}

	public function test_clear_log_action_empties_the_log() {
		\TheTribalPlugin\Logger::get_instance()->info( 'something before clearing' );
		wp_set_current_user( $this->adminId );
		$_POST = $_REQUEST = [ 'action' => 'ttt_clear_logs', '_wpnonce' => wp_create_nonce( 'ttt_client_update_plugin' ) ];

		$result = ( new \TheTribalPlugin\Dashboard() )->update();

		$this->assertSame( 'success', $result['code'] );
		$this->assertStringNotContainsString( 'something before clearing', $this->logContents() );
		$this->assertStringContainsString( 'Log cleared', $this->logContents() );
	}

	/* ---------------------------------------------------------------- form import & alert */

	public function test_form_import_lists_imported_titles_escaped() {
		$this->portalPosts = [ 901 => $this->portalPost( 1, [ 'title' => 'Fish & Chips <script>x</script>' ] ) ];
		wp_set_current_user( $this->adminId );
		$_POST = $_REQUEST = [ 'action' => 'ttt_force_import', '_wpnonce' => wp_create_nonce( 'ttt_client_update_plugin' ) ];

		$result = ( new \TheTribalPlugin\Dashboard() )->update();

		$this->assertSame( 'success', $result['code'] );
		$this->assertStringContainsString( '<li>Fish &amp; Chips &lt;script&gt;x&lt;/script&gt;</li>', $result['msg-content'] );
	}

	public function test_alert_renders_the_list_as_html_and_escapes_the_message() {
		ob_start();
		\TheTribalPlugin\ShowAlert::get_instance()->show( [
			'alert'       => 'success',
			'code'        => 'success',
			'msg-header'  => 'Success',
			'msg'         => 'Imported <b>2</b>',
			'msg-content' => '<p>(1) Blogs Imported : </p><ul><li>Fish &amp; Chips</li></ul><script>x</script>',
			'close'       => true,
		] );
		$html = ob_get_clean();

		$this->assertStringContainsString( '<ul><li>Fish &amp; Chips</li></ul>', $html, 'Summary list must render as HTML' );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'Imported &lt;b&gt;2&lt;/b&gt;', $html );
		$this->assertMatchesRegularExpression( '/class="alert alert-success alert-dismissible fade show"/', $html );
	}

	/* ---------------------------------------------------------------- admin page */

	public function test_admin_page_renders_all_tabs_for_an_active_key() {
		wp_set_current_user( $this->adminId );
		require_once ABSPATH . 'wp-admin/includes/admin.php';

		ob_start();
		( new \TheTribalPlugin\Dashboard() )->init();
		$html = ob_get_clean();

		foreach ( [ 'api-key', 'settings', 'import', 'logs' ] as $tab ) {
			$this->assertStringContainsString( 'id="' . $tab . '"', $html );
		}
		$this->assertStringNotContainsString( self::$API_KEY, $html, 'The API key must never be printed in the page' );
	}
}
