<?php
/**
 * Logger: storage, protection, masking, rotation, fatal capture.
 *
 * @package The_Tribal_Plugin
 */

use TheTribalPlugin\Logger;

class Test_Logger extends TTT_TestCase {

	private function logger() {
		return Logger::get_instance();
	}

	public function test_creates_a_protected_folder_with_a_random_log_name() {
		$this->logger()->info( 'hello' );

		$dir = $this->uploadDir . '/ttt-logs';
		$this->assertFileExists( $dir . '/.htaccess' );
		$this->assertStringContainsString( 'Require all denied', file_get_contents( $dir . '/.htaccess' ) );
		$this->assertFileExists( $dir . '/index.php' );
		$this->assertMatchesRegularExpression( '#/ttt-[A-Za-z0-9]{32}\.log$#', $this->logger()->file() );
		$this->assertStringContainsString( '| INFO    |', $this->logContents() );
		$this->assertStringContainsString( 'hello', $this->logContents() );
	}

	public function test_masks_values_under_secret_looking_keys() {
		$this->logger()->info( 'ctx', [ 'user_api_key' => 'SOMEOTHERKEY-9999abcd', 'nested' => [ 'password' => 'hunter2hunter2' ] ] );

		$log = $this->logContents();
		$this->assertStringNotContainsString( 'SOMEOTHERKEY-9999abcd', $log );
		$this->assertStringContainsString( '****abcd', $log );
		$this->assertStringNotContainsString( 'hunter2hunter2', $log );
	}

	public function test_masks_the_stored_api_key_and_registered_secrets_anywhere_in_a_line() {
		$this->logger()->addSecret( 'ANOTHER-secret-123456' );
		$this->logger()->info( 'free text ' . self::$API_KEY . ' and ANOTHER-secret-123456' );

		$log = $this->logContents();
		$this->assertStringNotContainsString( self::$API_KEY, $log );
		$this->assertStringNotContainsString( 'ANOTHER-secret-123456', $log );
	}

	public function test_masks_email_addresses() {
		$this->logger()->info( 'member', [ 'email' => 'someone@example.com' ] );

		$this->assertStringNotContainsString( 'someone@example.com', $this->logContents() );
		$this->assertStringContainsString( '[email]', $this->logContents() );
	}

	public function test_truncates_long_strings_without_breaking_utf8() {
		$this->logger()->info( 'long', [ 'body' => str_repeat( 'é', 15000 ), 'bad' => "ok\xC3(" ] );

		$log = $this->logContents();
		$this->assertStringContainsString( '[truncated', $log );
		$this->assertStringNotContainsString( '"body":null', $log );
		$this->assertStringNotContainsString( '"bad":null', $log );
		$this->assertTrue( mb_check_encoding( $log, 'UTF-8' ) );
	}

	public function test_rotates_when_the_file_exceeds_the_size_limit() {
		$this->logger()->info( 'first' );
		$file = $this->logger()->file();
		file_put_contents( $file, str_repeat( 'x', Logger::MAX_BYTES + 1 ), FILE_APPEND );

		$this->logger()->info( 'after rotation' );

		$this->assertFileExists( $file . '.1' );
		$this->assertLessThan( 1000, filesize( $file ) );
		$this->assertStringContainsString( 'after rotation', file_get_contents( $file ) );
	}

	public function test_tail_returns_newest_first() {
		$this->logger()->info( 'older' );
		$this->logger()->info( 'newer' );

		$lines = explode( "\n", $this->logger()->tail( 2 ) );
		$this->assertStringContainsString( 'newer', $lines[0] );
		$this->assertStringContainsString( 'older', $lines[1] );
	}

	public function test_clear_empties_the_log_and_removes_the_backup() {
		$this->logger()->info( 'x' );
		$file = $this->logger()->file();
		file_put_contents( $file . '.1', 'old' );

		$this->logger()->clear();

		$this->assertSame( '', file_get_contents( $file ) );
		$this->assertFileDoesNotExist( $file . '.1' );
	}

	public function test_removes_legacy_log_files_when_the_new_log_is_created() {
		$legacy = $this->uploadDir . '/' . TTT_LOG_FILE_NAME;
		file_put_contents( $legacy, 'old log with secrets' );

		$this->logger()->info( 'first entry' );

		$this->assertFileDoesNotExist( $legacy );
		$this->assertStringContainsString( 'Removed legacy log file', $this->logContents() );
	}

	public function test_delete_all_removes_the_folder_and_legacy_files() {
		$this->logger()->info( 'x' );
		$legacy = $this->uploadDir . '/' . TTT_LOG_FILE_NAME;
		file_put_contents( $legacy, 'old' );

		$this->logger()->deleteAll();

		$this->assertDirectoryDoesNotExist( $this->uploadDir . '/ttt-logs' );
		$this->assertFileDoesNotExist( $legacy );
	}

	public function test_logs_php_fatal_errors_once_via_the_wordpress_handler() {
		$this->logger()->captureFatalErrors();
		$error = [ 'type' => E_ERROR, 'message' => 'Uncaught TypeError: boom', 'file' => 'x.php', 'line' => 3 ];

		apply_filters( 'wp_php_error_message', 'message', $error );
		apply_filters( 'wp_php_error_message', 'message', $error );

		$this->assertSame( 1, substr_count( $this->logContents(), 'PHP fatal error' ) );
		$this->assertStringContainsString( 'Uncaught TypeError: boom', $this->logContents() );
	}

	public function test_falls_back_quietly_when_the_log_file_is_not_writable() {
		$this->logger()->info( 'first' );
		$file = $this->logger()->file();
		chmod( $file, 0444 );
		$previous = ini_set( 'error_log', '/dev/null' );

		try {
			$this->logger()->info( 'second' ); // must not raise a warning (warnings fail tests)
		} finally {
			ini_set( 'error_log', $previous );
			chmod( $file, 0644 );
		}

		$this->assertStringNotContainsString( 'second', file_get_contents( $file ) );
	}

	public function test_download_requires_manage_options() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'ttt_download_log' );

		$this->expectException( WPDieException::class );
		$this->logger()->download();
	}

	public function test_the_api_key_never_reaches_the_log_during_import_or_activation() {
		$this->runImport();
		$this->activateKey( 'NEWKEY-abcdef0123456789' );

		$log = $this->logContents();
		$this->assertNotSame( '', $log );
		$this->assertStringNotContainsString( self::$API_KEY, $log );
		$this->assertStringNotContainsString( 'NEWKEY-abcdef0123456789', $log );
	}

	public function test_download_contains_the_rotated_backup_followed_by_the_current_log() {
		$this->logger()->info( 'current entry' );
		file_put_contents( $this->logger()->file() . '.1', "older entry\n" );

		$content = $this->logger()->downloadContent();

		$this->assertLessThan( strpos( $content, 'current entry' ), strpos( $content, 'older entry' ) );
	}
}
