<?php
namespace TheTribalPlugin;

class APIPortal
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

	/** Portal API version used for auth and post/get. */
	const VERSION = 'v2';

	public function __construct(){}

    public function url( $args = [] )
    {
		$apiUrl = 'https://portal.thetechtribe.com';
		if(defined('TTT_DEV_PORTAL_LOCAL')){
			$apiUrl = TTT_DEV_PORTAL_LOCAL;
		}
        $defaults = [
            'version'   => 'v1',
            'url'       => $apiUrl,
            'url_api'   => 'wp-json/ttt-portal'
        ];

        $args = wp_parse_args( $args, $defaults );

        return $args['url'] .'/'. $args['url_api'] .'/'. $args['version'] .'/';
    }

	/**
	 * POST to a portal v2 endpoint (v2 reports errors with real HTTP status codes).
	 *
	 * @return array {
	 *     @type bool   $ok     True only for HTTP 200 with success = true.
	 *     @type int    $status HTTP status, 0 when the request itself failed.
	 *     @type string $code   Portal code (success, invalid, ac_tag, domain_not_approved, rate_limited,
	 *                          upstream_error, ...) or a local one: timeout, network, bad_response.
	 *     @type string $msg    Portal or transport message (for logs, not for display).
	 *     @type array  $data   Response "data" (empty on errors).
	 * }
	 */
	public function post( $endpoint, $body = [] )
	{
		$logger = Logger::get_instance();
		$url = $this->url( ['version' => self::VERSION] ) . $endpoint;

		if ( ! empty( $body['user_api_key'] ) ) {
			$logger->addSecret( $body['user_api_key'] );
		}
		$logger->info( 'Portal request: ' . $endpoint, ['url' => $url, 'body' => $body] );
		$requestStarted = microtime( true );

		$response = wp_remote_post( $url, [
			'timeout' => 45,
			'body'    => $body,
		] );

		$status  = (int) wp_remote_retrieve_response_code( $response );
		$rawBody = wp_remote_retrieve_body( $response );
		$json    = json_decode( $rawBody, true );

		if ( is_wp_error( $response ) ) {
			$errorMsg = $response->get_error_message();
			$result = [
				'ok'     => false,
				'status' => 0,
				'code'   => ttt_str_contains( $errorMsg, 'cURL error 28' ) ? 'timeout' : 'network',
				'msg'    => $errorMsg,
				'data'   => [],
			];
		} elseif ( ! is_array( $json ) ) {
			$result = [
				'ok'     => false,
				'status' => $status,
				'code'   => 'bad_response',
				'msg'    => 'Response is not valid JSON',
				'data'   => [],
			];
		} else {
			$result = [
				'ok'     => 200 === $status && ! empty( $json['success'] ),
				'status' => $status,
				'code'   => isset( $json['code'] ) ? (string) $json['code'] : '',
				'msg'    => isset( $json['msg'] ) && is_scalar( $json['msg'] ) ? (string) $json['msg'] : '',
				'data'   => isset( $json['data'] ) && is_array( $json['data'] ) ? $json['data'] : [],
			];
		}

		$logger->log(
			$result['ok'] ? 'info' : 'error',
			'Portal response: ' . $endpoint,
			[
				'duration_s'   => round( microtime( true ) - $requestStarted, 2 ),
				'http_code'    => $status,
				'code'         => $result['code'],
				'msg'          => $result['msg'],
				'content_type' => wp_remote_retrieve_header( $response, 'content-type' ),
				'body_bytes'   => strlen( $rawBody ),
				'body'         => $rawBody,
			]
		);

		return $result;
	}

	/**
	 * User-facing message for a failed post() result, and whether the API key must be marked inactive.
	 *
	 * @return array { @type string $header, @type string $msg, @type bool $deactivate }
	 */
	public function errorMessage( $result )
	{
		$verbage = StatusVerbage::get_instance();
		$general = $verbage->get( 'general_error' );
		$status  = $result['status'] ?? 0;
		$code    = $result['code'] ?? '';

		if ( 401 === $status ) {
			$text = $verbage->get( 'api' )['error'];
			$deactivate = true;
		} elseif ( 403 === $status && 'ac_tag' === $code ) {
			$text = $verbage->get( 'ac_tag' )['error'];
			$deactivate = true;
		} elseif ( 403 === $status ) {
			// domain_not_approved
			$text = $verbage->get( 'domain' )['error'];
			$deactivate = true;
		} elseif ( 429 === $status ) {
			$text = $general['rate_limited'];
			$deactivate = false;
		} elseif ( 'timeout' === $code ) {
			$text = $general['timeout'];
			$deactivate = false;
		} elseif ( $status >= 500 || 'network' === $code ) {
			$text = $general['unavailable'];
			$deactivate = false;
		} else {
			// 400 (plugin sent a bad request), unexpected status or non-JSON body.
			$text = $general['error'];
			$deactivate = false;
		}

		return [
			'header'     => $text['header'],
			'msg'        => $text['msg'],
			'deactivate' => $deactivate,
		];
	}
}