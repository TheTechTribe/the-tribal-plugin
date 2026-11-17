<?php
/**
 * Activation, deactivation and the cron hook.
 *
 * @package The_Tribal_Plugin
 */

class Test_Lifecycle extends TTT_TestCase {

	public function test_activation_schedules_the_daily_import() {
		wp_clear_scheduled_hook( 'ttt_user_cron_hook' );

		activate_the_tribal_plugin();

		$this->assertNotFalse( wp_next_scheduled( 'ttt_user_cron_hook' ) );
		$this->assertSame( 'daily', wp_get_schedule( 'ttt_user_cron_hook' ) );
	}

	public function test_the_cron_hook_runs_an_import() {
		do_action( 'ttt_user_cron_hook' );

		$this->assertCount( 1, $this->importedPosts() );
		$this->assertStringContainsString( 'Cron', (string) get_option( 'ttt_client_import_job_via' ) );
	}

	/**
	 * No cache flush here on purpose: deactivation must keep the options cache correct
	 * (sites with a persistent object cache would otherwise keep the deleted values).
	 */
	public function test_deactivation_unschedules_the_import_and_removes_plugin_data_and_logs() {
		activate_the_tribal_plugin();
		\TheTribalPlugin\Logger::get_instance()->info( 'x' );

		deactivate_the_tribal_plugin();

		$this->assertFalse( wp_next_scheduled( 'ttt_user_cron_hook' ) );
		$this->assertFalse( get_option( 'ttt_api_key_blog_post' ) );
		$this->assertFalse( get_option( 'ttt_is_active' ) );
		$this->assertDirectoryDoesNotExist( $this->uploadDir . '/ttt-logs' );
	}

	public function test_deactivation_does_not_delete_other_plugins_options() {
		update_option( 'tttx_unrelated_plugin_option', 'keep me' );

		deactivate_the_tribal_plugin();
		wp_cache_flush();

		$this->assertSame( 'keep me', get_option( 'tttx_unrelated_plugin_option' ) );
	}

	/* ---------------------------------------------------------------- next scheduled check (dashboard) */

	public function test_next_check_is_shown_in_site_time_without_changing_php_timezone() {
		update_option( 'timezone_string', 'Australia/Sydney' );
		wp_clear_scheduled_hook( 'ttt_user_cron_hook' );
		$next = time() + HOUR_IN_SECONDS;
		wp_schedule_event( $next, 'daily', 'ttt_user_cron_hook' );

		$shown = tttGetNextCronTimeDate();

		$this->assertSame( 'UTC', date_default_timezone_get(), 'WordPress requires PHP default timezone to stay UTC' );
		$this->assertSame( wp_date( 'd F Y h:i A', $next ), $shown );
	}

	public function test_next_check_is_the_soonest_scheduled_run() {
		wp_clear_scheduled_hook( 'ttt_user_cron_hook' );
		$soon  = time() + HOUR_IN_SECONDS;
		$later = time() + 3 * DAY_IN_SECONDS;
		wp_schedule_single_event( $later, 'ttt_user_cron_hook' );
		wp_schedule_single_event( $soon, 'ttt_user_cron_hook' );

		$this->assertSame( wp_date( 'd F Y h:i A', $soon ), tttGetNextCronTimeDate() );
	}
}
