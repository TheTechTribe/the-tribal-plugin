<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define('TTT_LOG_FILE_NAME', 'The-Tribal-Plugin.log');

if(!function_exists('ttt_str_contains')){
    function ttt_str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

if(!function_exists('ttt_ensure_response_api')){
    function ttt_ensure_response_api($msg = '', $code = true, $meta = [])
    {
        $args = [
            'msg'   => $msg,
            'code'  => $code,
            'meta' => $meta
        ];
            
        return rest_ensure_response($args);
    }
}

if(!function_exists('ttt_dd')){
    function ttt_dd($arr = [], $exit = false)
    {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return;
        }
        echo '<pre>';
        print_r($arr);
        echo '</pre>';
        if($exit) exit();
    }
}

if(!function_exists('tttResetDownloadStatusStartEnd')){
    function tttResetDownloadStatusStartEnd()
    {
        \TheTribalPlugin\HealthStatus::get_instance()->importJobEnd([
            'action' => 'd',
        ]);
        \TheTribalPlugin\HealthStatus::get_instance()->importJobStart([
            'action' => 'd',
        ]);
    }
}

if(!function_exists('tttIsKeyActive')){
    function tttIsKeyActive()
    {
        $isActive = \TheTribalPlugin\HealthStatus::get_instance()->isActive([
            'action' => 'r',
        ]);

        if($isActive && $isActive == 1) {
            return true;
        }

        return false;
    }
}

if(!function_exists('tttSetKeyActive')){
    function tttSetKeyActive($active = 0)
    {
        \TheTribalPlugin\HealthStatus::get_instance()->isActive([
            'action' => 'u',
            'value' => $active,
        ]);
    }
}

/**
 * Soonest scheduled run of a cron hook (Unix timestamp), or false.
 */
function tttGetNextCronTime( $cron_name ){
	return wp_next_scheduled( $cron_name );
}

/**
 * Next scheduled import in the site's timezone. Uses wp_date() so PHP's default
 * timezone (which WordPress requires to stay UTC) is never changed.
 */
function tttGetNextCronTimeDate() {
    $nextSchedule = tttGetNextCronTime('ttt_user_cron_hook');
    return ($nextSchedule) ? wp_date('d F Y h:i A', $nextSchedule) : '';
}

function tttStartImport()
{
    \TheTribalPlugin\HealthStatus::get_instance()->importJobStart([
        'action' => 'u',
        'value' => date_i18n('Y/m/d H:i:s') . ' : Start'
    ]);
}

function tttEndImport()
{
    \TheTribalPlugin\HealthStatus::get_instance()->importJobEnd([
        'action' => 'u',
        'value' => date_i18n('Y/m/d H:i:s') . ' : End'
    ]);
}

function tttLastDownload()
{
    \TheTribalPlugin\HealthStatus::get_instance()->lastDownload([
        'action' => 'u',
        'value' => date_i18n('Y/m/d H:i:s')
    ]);
}

function tttLastChecked()
{
    \TheTribalPlugin\HealthStatus::get_instance()->lastChecked([
        'action' => 'u',
        'value' => date_i18n('Y/m/d H:i:s')
    ]);
}

function tttLastCheckedStatus($code, $msg)
{
    \TheTribalPlugin\HealthStatus::get_instance()->lastCheckedStatus([
        'action' => 'u',
        'value' => date_i18n('Y/m/d H:i:s') .' : '. $code . ' : ' . $msg
    ]);
}

function tttLogReturn($ret = [])
{
    \TheTribalPlugin\HealthStatus::get_instance()->importLogReturnPost([
        'action' => 'u',
        'value' => $ret
    ]);
}

function tttImportJobVia($via)
{
    \TheTribalPlugin\HealthStatus::get_instance()->importJobVia([
        'action' => 'u',
        'value' => date_i18n('Y/m/d H:i:s') . ' : ' . $via
    ]);
}

function tttVerifyChecked($code, $msg)
{
    \TheTribalPlugin\HealthStatus::get_instance()->verifyChecked([
        'action' => 'u',
        'value' => $code . ' : ' . $msg
    ]);
}

function tttRemoveInDbOptions()
{
    global $wpdb;
    // Escape "_" (a LIKE wildcard) so only this plugin's "ttt_" options match, and delete through
    // delete_option() so the options cache (including persistent object caches) stays correct.
    $names = $wpdb->get_col( $wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like( 'ttt_' ) . '%'
    ) );
    foreach ( $names as $name ) {
        delete_option( $name );
    }
}

function tttInitCronJob()
{
    if ( ! wp_next_scheduled( 'ttt_user_cron_hook' ) ) {
		$scheduled = wp_schedule_event( time(), 'daily', 'ttt_user_cron_hook' );
		\TheTribalPlugin\Logger::get_instance()->log(
			$scheduled ? 'info' : 'error',
			'Plugin activated: daily import cron ' . ( $scheduled ? 'scheduled' : 'could not be scheduled' )
		);
	}
}

function tttRemoveCronJob()
{
    $timestamp = wp_next_scheduled( 'ttt_user_cron_hook' );
    wp_unschedule_event( $timestamp, 'ttt_user_cron_hook' );

	wp_clear_scheduled_hook( 'ttt_user_cron_hook' );
}

function tttThrowTimeOutError($msg)
{
    if(ttt_str_contains($msg, 'cURL error 28')) {
        $ret = \TheTribalPlugin\StatusVerbage::get_instance()->get('general_error');
        return $ret['timeout'];
    }
    return false;
}

function tttThrowGeneralErrorMsg()
{
    $general = \TheTribalPlugin\StatusVerbage::get_instance()->get('general_error');
    return $general['error'];
}

function tttGetAPIVerbage()
{
    return \TheTribalPlugin\StatusVerbage::get_instance()->get('api');
}

/**
 * Kept for backward compatibility; logging goes through \TheTribalPlugin\Logger.
 */
function tttCustomLogs($log) {
    if(is_scalar($log)) {
        \TheTribalPlugin\Logger::get_instance()->info((string) $log);
    } else {
        \TheTribalPlugin\Logger::get_instance()->info('data', ['data' => $log]);
    }
}

/**
 * Deletes the log folder and any legacy log files (TTT_LOG_FILE_NAME) from older versions.
 */
function tttCustomLogsDelete() {
    \TheTribalPlugin\Logger::get_instance()->deleteAll();
}

function tttAllowedAdminAssetInclude()
{
    $currentScreen = get_current_screen();
    $arrAllowedPage = ['toplevel_page_the-tribal-plugin'];
	
    if( in_array($currentScreen->base, $arrAllowedPage) && ! is_customize_preview() ){
        return true;
    }

}

function tttGetTheUserIp() 
{
    $ip = '';
    if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
        //check ip from share internet
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        //to check ip is pass from proxy
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // Validate IP address - fall back to REMOTE_ADDR if invalid
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
    }
    return apply_filters( 'ttt_wp_get_ip', $ip );
}