<?php
/**
 * Base test case: isolated uploads/log folder, a configured plugin, and a fake portal.
 *
 * The fake portal answers in the real response shape of whichever API version the
 * plugin calls (v1 shapes from the portal's V1 classes, v2 shapes from its v2 spec),
 * so the same scenario-level assertions describe the required behaviour for both.
 *
 * @package The_Tribal_Plugin
 */

use TheTribalPlugin\Logger;
use TheTribalPlugin\StatusVerbage;

trait TTT_Fixtures {

	public static $API_KEY = 'TESTKEY-0123456789abcdef';
	public static $PRESS   = 'https://press.test';

	/** @var string Scenario the fake portal plays: posts, empty, invalid, ac_tag, domain, rate_limited, unavailable, upstream_down, timeout, not_json. */
	protected $scenario = 'posts';

	/** @var array Posts the fake portal returns for the "posts" scenario, keyed by upstream id. */
	protected $portalPosts = [];

	/** @var array Every request sent to the portal: [ 'url', 'version', 'endpoint', 'body' ]. */
	protected $portalRequests = [];

	/** @var int Requests to anything other than the portal or the fake image host. */
	protected $unexpectedRequests = 0;

	/** @var array Every image request: [ 'url', 'timeout' ]. */
	protected $imageRequests = [];

	/** @var array URL => number of transport failures (cURL 28) before the image host answers. */
	protected $imageFailuresBeforeSuccess = [];

	protected $uploadDir;
	protected $adminId;

	public function set_up() {
		parent::set_up();

		$this->uploadDir = get_temp_dir() . 'ttt-tests-' . wp_generate_password( 8, false );
		wp_mkdir_p( $this->uploadDir );
		add_filter( 'upload_dir', [ $this, 'filterUploadDir' ] );

		$this->resetSingleton( Logger::class );

		$this->adminId = self::factory()->user->create( [ 'role' => 'administrator' ] );
		update_option( 'ttt_api_key_blog_post', self::$API_KEY );
		update_option( 'ttt_is_active', 1 );
		update_option( 'ttt_auto_post_author', $this->adminId );
		update_option( 'ttt_auto_manual_publish_post', 'manual' );

		$this->scenario           = 'posts';
		$this->portalPosts        = [ 901 => $this->portalPost( 1 ) ];
		$this->portalRequests     = [];
		$this->unexpectedRequests = 0;
		$this->imageRequests      = [];
		$this->imageFailuresBeforeSuccess = [];
		add_filter( 'pre_http_request', [ $this, 'fakeHttp' ], 10, 3 );
	}

	public function tear_down() {
		$_POST = $_REQUEST = [];
		$this->removeDir( $this->uploadDir );
		parent::tear_down();
	}

	public function filterUploadDir( $dirs ) {
		return array_merge( $dirs, [
			'basedir' => $this->uploadDir,
			'baseurl' => 'https://site.test/uploads',
			'path'    => $this->uploadDir . '/media',
			'url'     => 'https://site.test/uploads/media',
			'subdir'  => '/media',
			'error'   => false,
		] );
	}

	/* ------------------------------------------------------------------ fake HTTP */

	public function fakeHttp( $pre, $args, $url ) {
		if ( 0 === strpos( $url, self::$PRESS . '/' ) ) {
			$this->imageRequests[] = [ 'url' => $url, 'timeout' => $args['timeout'] ?? null ];
		}
		if ( ! empty( $this->imageFailuresBeforeSuccess[ $url ] ) ) {
			$this->imageFailuresBeforeSuccess[ $url ]--;
			return new WP_Error( 'http_request_failed', 'cURL error 28: Resolving timed out after 5000 milliseconds' );
		}
		if ( 0 === strpos( $url, self::$PRESS . '/server-error' ) ) {
			return $this->response( 503, '<html>Busy</html>', 'text/html' );
		}
		if ( 0 === strpos( $url, self::$PRESS . '/img' ) ) {
			return $this->response( 200, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==' ), 'image/png' );
		}
		if ( 0 === strpos( $url, self::$PRESS . '/missing' ) ) {
			return $this->response( 404, '<html>Not found</html>', 'text/html' );
		}
		if ( ! preg_match( '#/wp-json/ttt-portal/(v\d+)/(auth|post/get)$#', $url, $m ) ) {
			$this->unexpectedRequests++;
			return new WP_Error( 'http_request_failed', 'Unexpected request in test: ' . $url );
		}

		$version  = $m[1];
		$endpoint = $m[2];
		$this->portalRequests[] = [ 'url' => $url, 'version' => $version, 'endpoint' => $endpoint, 'body' => $args['body'] ?? [] ];

		return 'v1' === $version ? $this->portalV1( $endpoint ) : $this->portalV2( $endpoint );
	}

	private function portalV1( $endpoint ) {
		$errors = [ 'invalid' => [ 'invalid', 'api error' ], 'ac_tag' => [ 'ac_tag', 'ac tag error' ], 'domain' => [ 'alreadyused', 'domain already used' ] ];

		switch ( $this->scenario ) {
			case 'timeout':
				return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 45001 milliseconds' );
			case 'not_json':
				return $this->response( 200, '<html>Proxy error</html>', 'text/html' );
			case 'rate_limited':
				return $this->json( 429, [ 'success' => false, 'code' => 'rate_limited', 'msg' => 'Too many requests.' ] );
			case 'unavailable':
				return $this->response( 503, '<html>Maintenance</html>', 'text/html' );
		}

		if ( 'auth' === $endpoint ) {
			if ( isset( $errors[ $this->scenario ] ) ) {
				list( $code, $msg ) = $errors[ $this->scenario ];
				return $this->json( 200, [ 'success' => false, 'code' => 'error', 'msg-header' => 'error', 'msg' => [ 'errors' => [ $code => [ $msg ] ], 'error_data' => [] ] ] );
			}
			return $this->json( 200, [ 'success' => true, 'code' => 'success', 'msg-header' => 'success', 'msg' => 'api success' ] );
		}

		// post/get: v1 relays the upstream body as-is; on upstream failure it relays the error string.
		if ( isset( $errors[ $this->scenario ] ) ) {
			return $this->json( 200, [ 'success' => false, 'code' => 'error', 'msg' => $errors[ $this->scenario ][1], 'posts' => [] ] );
		}
		if ( 'upstream_down' === $this->scenario ) {
			return $this->json( 200, 'cURL error 7: Failed to connect to thetechnologypress.com port 443' );
		}
		return $this->json( 200, [ 'success' => true, 'code' => 'success', 'msg' => 'Success', 'posts' => $this->upstreamPosts() ] );
	}

	private function portalV2( $endpoint ) {
		$errors = [
			'invalid'       => [ 401, 'invalid', 'api error' ],
			'ac_tag'        => [ 403, 'ac_tag', 'ac tag error' ],
			'domain'        => [ 403, 'domain_not_approved', 'This domain is not approved for this API key.' ],
			'rate_limited'  => [ 429, 'rate_limited', 'Too many requests. Please slow down and try again shortly.' ],
			'upstream_down' => [ 502, 'upstream_error', 'Error getting blog posts.' ],
		];

		switch ( $this->scenario ) {
			case 'timeout':
				return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 45001 milliseconds' );
			case 'not_json':
				return $this->response( 200, '<html>Proxy error</html>', 'text/html' );
			case 'unavailable':
				return $this->response( 503, '<html>Maintenance</html>', 'text/html' );
		}
		if ( isset( $errors[ $this->scenario ] ) ) {
			list( $status, $code, $msg ) = $errors[ $this->scenario ];
			return $this->json( $status, [ 'success' => false, 'code' => $code, 'msg' => $msg, 'data' => [] ] );
		}
		if ( 'auth' === $endpoint ) {
			return $this->json( 200, [ 'success' => true, 'code' => 'success', 'msg' => 'API key activated.', 'data' => [] ] );
		}
		return $this->json( 200, [ 'success' => true, 'code' => 'success', 'msg' => 'Success', 'data' => $this->upstreamPosts() ] );
	}

	/** Upstream (Technology Press) payload. It has no current_blog_name, like the real one. */
	private function upstreamPosts() {
		if ( 'empty' === $this->scenario ) {
			return [ 'current_post_url' => self::$PRESS, 'total_post' => 0 ];
		}
		return [ 'current_post_url' => self::$PRESS, 'total_post' => count( $this->portalPosts ), 'posts' => $this->portalPosts ];
	}

	protected function portalPost( $n, $overrides = [] ) {
		return array_replace_recursive( [
			'id'                => 900 + $n,
			'title'             => "Test Post $n",
			'content'           => '<!-- wp:paragraph --><p class="aligncenter">Hello <strong>world</strong></p><!-- /wp:paragraph --><p><img src="' . self::$PRESS . "/img-inline-$n.png\"></p>",
			'get_the_permalink' => self::$PRESS . "/post-$n/",
			'categories'        => [ 'Cyber Security' ],
			'meta'              => [
				'date_schedule' => '2099-01-15',
				'images'        => [
					'contents' => [ self::$PRESS . "/img-inline-$n.png" ],
					'featured' => [ 'image' => self::$PRESS . "/img-featured-$n.png", 'alt_text' => "Featured alt $n" ],
				],
			],
		], $overrides );
	}

	protected function json( $status, $body ) {
		return $this->response( $status, wp_json_encode( $body ), 'application/json' );
	}

	protected function response( $status, $body, $type ) {
		return [ 'headers' => [ 'content-type' => $type ], 'body' => $body, 'response' => [ 'code' => $status, 'message' => '' ], 'cookies' => [], 'filename' => null ];
	}

	/* ------------------------------------------------------------------ actions */

	/** @return array ImportPost result data. */
	protected function runImport() {
		return \TheTribalPlugin\ImportPost::get_instance()->import()->data;
	}

	/** Submit the "Activate API key" form as an administrator. @return array|null */
	protected function activateKey( $key = 'NEWKEY-abcdef0123456789' ) {
		wp_set_current_user( $this->adminId );
		$_POST = $_REQUEST = [
			'action'           => 'ttt_activate_key',
			'ttt_api_key'      => $key,
			'_wpnonce'         => wp_create_nonce( 'ttt_client_update_plugin' ),
			'_wp_http_referer' => '/wp-admin/admin.php?page=the-tribal-plugin',
		];
		return ( new \TheTribalPlugin\Dashboard() )->update();
	}

	/* ------------------------------------------------------------------ helpers */

	protected function verbage( $group, $key ) {
		return StatusVerbage::get_instance()->get( $group )[ $key ];
	}

	protected function lastPortalRequest() {
		return end( $this->portalRequests );
	}

	protected function importedPosts() {
		return get_posts( [ 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ] );
	}

	protected function logContents() {
		$files = glob( $this->uploadDir . '/ttt-logs/ttt-*.log' );
		return $files ? file_get_contents( $files[0] ) : '';
	}

	protected function resetSingleton( $class ) {
		$prop = new ReflectionProperty( $class, 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}

	protected function removeDir( $dir ) {
		if ( ! $dir || ! is_dir( $dir ) ) {
			return;
		}
		$items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $items as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}
		rmdir( $dir );
	}
}

abstract class TTT_TestCase extends WP_UnitTestCase {
	use TTT_Fixtures;
}

abstract class TTT_Ajax_TestCase extends WP_Ajax_UnitTestCase {
	use TTT_Fixtures;
}
