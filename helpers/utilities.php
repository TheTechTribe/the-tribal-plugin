<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
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