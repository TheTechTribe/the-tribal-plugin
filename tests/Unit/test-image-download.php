<?php
/**
 * Image downloads during import: timeout and retry of transient failures.
 *
 * @package The_Tribal_Plugin
 */

class Test_Image_Download extends TTT_TestCase {

	private function requestsFor( $url ) {
		return count( array_filter( $this->imageRequests, function ( $r ) use ( $url ) { return $r['url'] === $url; } ) );
	}

	public function test_image_downloads_allow_slow_hosts_at_least_30_seconds() {
		$this->runImport();

		$this->assertNotEmpty( $this->imageRequests );
		foreach ( $this->imageRequests as $request ) {
			$this->assertGreaterThanOrEqual( 30, $request['timeout'], 'Image download timeout for ' . $request['url'] );
		}
	}

	public function test_a_featured_image_that_times_out_once_is_retried_and_set() {
		$featured = self::$PRESS . '/img-featured-1.png';
		$this->imageFailuresBeforeSuccess[ $featured ] = 1;

		$this->runImport();

		$this->assertSame( 2, $this->requestsFor( $featured ) );
		$this->assertGreaterThan( 0, get_post_thumbnail_id( $this->importedPosts()[0]->ID ) );
	}

	public function test_an_inline_image_that_times_out_once_is_retried_and_rewritten() {
		$inline = self::$PRESS . '/img-inline-1.png';
		$this->imageFailuresBeforeSuccess[ $inline ] = 1;

		$this->runImport();

		$this->assertSame( 2, $this->requestsFor( $inline ) );
		$this->assertStringContainsString( 'https://site.test/uploads/media/img-inline-1.png', $this->importedPosts()[0]->post_content );
	}

	public function test_a_server_error_is_retried_once_then_the_remote_url_is_kept() {
		$inline = self::$PRESS . '/server-error-1.png';
		$this->portalPosts = [ 901 => $this->portalPost( 1, [
			'content' => '<p><img src="' . $inline . '"></p>',
			'meta'    => [ 'images' => [ 'contents' => [ $inline ] ] ],
		] ) ];

		$this->runImport();

		$this->assertSame( 2, $this->requestsFor( $inline ) );
		$this->assertStringContainsString( $inline, $this->importedPosts()[0]->post_content );
	}

	public function test_an_image_that_keeps_failing_is_tried_twice_then_skipped() {
		$featured = self::$PRESS . '/img-featured-1.png';
		$this->imageFailuresBeforeSuccess[ $featured ] = 5;

		$result = $this->runImport();

		$this->assertSame( 2, $this->requestsFor( $featured ) );
		$this->assertSame( 1, $result['post_count_imported'], 'The post is still imported' );
		$this->assertSame( 0, (int) get_post_thumbnail_id( $this->importedPosts()[0]->ID ) );
	}

	public function test_a_missing_image_404_is_not_retried() {
		$missing = self::$PRESS . '/missing-1.png';
		$this->portalPosts = [ 901 => $this->portalPost( 1, [
			'content' => '<p><img src="' . $missing . '"></p>',
			'meta'    => [ 'images' => [ 'contents' => [ $missing ] ] ],
		] ) ];

		$this->runImport();

		$this->assertSame( 1, $this->requestsFor( $missing ) );
	}

	public function test_import_raises_a_short_php_time_limit_so_slow_images_can_finish() {
		ini_set( 'max_execution_time', '30' );
		try {
			$this->runImport();
			$limit = (int) ini_get( 'max_execution_time' );
		} finally {
			set_time_limit( 0 );
		}

		$this->assertGreaterThanOrEqual( 300, $limit );
	}

	public function test_import_never_shortens_an_unlimited_time_limit() {
		set_time_limit( 0 );

		$this->runImport();

		$this->assertSame( 0, (int) ini_get( 'max_execution_time' ) );
	}

	/* ---------------------------------------------------------------- attachment title / caption */

	private function featuredAttachment() {
		return get_post( get_post_thumbnail_id( $this->importedPosts()[0]->ID ) );
	}

	public function test_attachment_title_uses_the_site_name_instead_of_the_repository_name() {
		update_option( 'blogname', 'Acme IT' );
		$this->portalPosts = [ 901 => $this->portalPost( 1, [ 'meta' => [ 'images' => [ 'featured' => [ 'image' => self::$PRESS . '/img-The-Technology-Press-header.png' ] ] ] ] ) ];

		$this->runImport();

		$title = $this->featuredAttachment()->post_title;
		$this->assertStringContainsString( 'Acme IT', $title );
		$this->assertDoesNotMatchRegularExpression( '/technology[\s_-]*press/i', $title );
	}

	public function test_embedded_image_caption_title_credit_and_copyright_use_the_site_name() {
		update_option( 'blogname', 'Acme IT' );
		add_filter( 'wp_read_image_metadata', function ( $meta ) {
			return array_merge( (array) $meta, [
				'caption'   => 'Photo by The Technology Press',
				'title'     => 'TheTechnologyPress banner',
				'credit'    => 'the technology press',
				'copyright' => '(c) The Technology Press 2026',
			] );
		} );

		$this->runImport();

		$imageMeta = wp_get_attachment_metadata( $this->featuredAttachment()->ID )['image_meta'];
		$this->assertSame( 'Photo by Acme IT', $imageMeta['caption'] );
		$this->assertSame( 'Acme IT banner', $imageMeta['title'] );
		$this->assertSame( 'Acme IT', $imageMeta['credit'] );
		$this->assertSame( '(c) Acme IT 2026', $imageMeta['copyright'] );
	}

	public function test_attachment_titles_without_the_repository_name_are_unchanged() {
		$this->runImport();

		$this->assertSame( 'img-featured-1.png', $this->featuredAttachment()->post_title );
	}
}
