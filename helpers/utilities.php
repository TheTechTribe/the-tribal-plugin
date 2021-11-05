<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

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
        echo '<pre>';
        print_r($arr);
        echo '</pre>';
        if($exit) exit();
    }
}

if(!function_exists('tttResetDownloadStatusStartEnd')){
    function tttResetDownloadStatusStartEnd()
    {
        \TheTechTribeClient\HealthStatus::get_instance()->importJobEnd([
            'action' => 'd',
        ]);
        \TheTechTribeClient\HealthStatus::get_instance()->importJobStart([
            'action' => 'd',
        ]);
    }
}

if(!function_exists('tttIsKeyActive')){
    function tttIsKeyActive()
    {
        $isActive = \TheTechTribeClient\HealthStatus::get_instance()->isActive([
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
        \TheTechTribeClient\HealthStatus::get_instance()->isActive([
            'action' => 'u',
            'value' => $active,
        ]);
    }
}

function tttGetNextCronTime( $cron_name ){
	$getCron = _get_cron_array();
	
	$timeStamp = [];
    foreach( $getCron as $timestamp => $crons ){
        if( in_array( $cron_name, array_keys( $crons ) ) ){
            $timeStamp[] = $timestamp;
        }
    }
	return end($timeStamp);
}

function tttGetNextCronTimeDate() {
    date_default_timezone_set(wp_timezone_string());
    $nextSchedule = tttGetNextCronTime('ttt_user_cron_exec');
    //return ($nextSchedule) ? date_i18n('d F Y h:i A', $nextSchedule) : '';
    return ($nextSchedule) ? date('d F Y h:i A', $nextSchedule) : '';
}

function tttStartImport()
{
    \TheTechTribeClient\HealthStatus::get_instance()->importJobStart([
        'action' => 'u',
        'value' => date_i18n('Y/m/d H:i:s') . ' : Start'
    ]);
}

function tttEndImport()
{
    \TheTechTribeClient\HealthStatus::get_instance()->importJobEnd([
        'action' => 'u',
        'value' => date_i18n('Y/m/d H:i:s') . ' : End'
    ]);
}

function tttLastDownload()
{
    \TheTechTribeClient\HealthStatus::get_instance()->lastDownload([
        'action' => 'u',
        'value' => date_i18n('Y/m/d H:i:s')
    ]);
}

function tttLastChecked()
{
    \TheTechTribeClient\HealthStatus::get_instance()->lastChecked([
        'action' => 'u',
        'value' => date_i18n('Y/m/d H:i:s')
    ]);
}

function tttLastCheckedStatus($code, $msg)
{
    \TheTechTribeClient\HealthStatus::get_instance()->lastCheckedStatus([
        'action' => 'u',
        'value' => date_i18n('Y/m/d H:i:s') .' : '. $code . ' : ' . $msg
    ]);
}

function tttLogReturn($ret = [])
{
    \TheTechTribeClient\HealthStatus::get_instance()->importLogReturnPost([
        'action' => 'u',
        'value' => $ret
    ]);
}

function tttImportJobVia($via)
{
    \TheTechTribeClient\HealthStatus::get_instance()->importJobVia([
        'action' => 'u',
        'value' => date_i18n('Y/m/d H:i:s') . ' : ' . $via
    ]);
}

function tttVerifyChecked($code, $msg)
{
    \TheTechTribeClient\HealthStatus::get_instance()->verifyChecked([
        'action' => 'u',
        'value' => $code . ' : ' . $msg
    ]);
}

function tttRemoveInDbOptions()
{
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->prefix}options where option_name like 'ttt_%' ");
}

function tttInitCronJob()
{
    if ( ! wp_next_scheduled( 'ttt_user_cron_hook' ) ) {
		wp_schedule_event( time(), 'daily', 'ttt_user_cron_exec' );
	}
}

function tttRemoveCronJob()
{
    $timestamp = wp_next_scheduled( 'ttt_user_cron_hook' );
    wp_unschedule_event( $timestamp, 'ttt_user_cron_exec' );

	wp_clear_scheduled_hook( 'ttt_user_cron_exec' );
}

function tttThrowTimeOutError($msg)
{
    if(ttt_str_contains($msg, 'cURL error 28')) {
        $ret = \TheTechTribeClient\StatusVerbage::get_instance()->get('general_error');
        return $ret['timeout'];
    }
    return false;
}

function tttThrowGeneralErrorMsg()
{
    $general = \TheTechTribeClient\StatusVerbage::get_instance()->get('general_error');
    return $general['error'];
}

function tttGetAPIVerbage()
{
    return \TheTechTribeClient\StatusVerbage::get_instance()->get('api');
}

function tttGetDomainVerbage()
{
    return \TheTechTribeClient\StatusVerbage::get_instance()->get('domain');
}

function tttGetACTagVerbage()
{
    return \TheTechTribeClient\StatusVerbage::get_instance()->get('ac_tag');
}

function tttCustomLogs($log) { 
    if(is_array($log) || is_object($log)) { 
        $log = json_encode($log);
    }
    $upload_dir = wp_upload_dir();

    $file = $upload_dir['basedir'] . '/ttt-logs.log'; 

    if(is_writable($upload_dir['basedir'])){
        $file = fopen($file,"a"); 
        fwrite($file, "\n" . date_i18n('Y-m-d h:i:s') . " :: " . $log); 
        fclose($file); 
    }
}