<?php
/**
 * Import behaviour (cron and manual imports share ImportPost::import()).
 *
 * @package The_Tribal_Plugin
 */

class Test_Import extends TTT_TestCase {

	/* ---------------------------------------------------------------- success */

	public function test_imports_a_new_post_as_draft_when_publishing_is_manual() {
		$result = $this->runImport();

		$this->assertTrue( $result['success'] );
		$this->assertSame( 1, $result['post_count_imported'] );
		$this->assertSame( $this->verbage( 'import', 'success' )['header'], $result['msg-header'] );

		$posts = $this->importedPosts();
		$this->assertCount( 1, $posts );
		$this->assertSame( 'Test Post 1', $posts[0]->post_title );
		$this->assertSame( 'draft', $posts[0]->post_status );
		$this->assertSame( '2099-01-15 12:00:00', $posts[0]->post_date );
		$this->assertSame( $this->adminId, (int) $posts[0]->post_author );
	}

	public function test_auto_publishing_schedules_future_dated_posts() {
		update_option( 'ttt_auto_manual_publish_post', 'auto' );

		$this->runImport();

		$this->assertSame( 'future', $this->importedPosts()[0]->post_status );
	}

	public function test_assigns_categories_creating_missing_ones() {
		self::factory()->category->create( [ 'name' => 'Cyber Security' ] );
		$this->portalPosts = [ 901 => $this->portalPost( 1, [ 'categories' => [ 'Cyber Security', 'Brand New Category' ] ] ) ];

		$this->runImport();

		$names = wp_get_post_categories( $this->importedPosts()[0]->ID, [ 'fields' => 'names' ] );
		sort( $names );
		$this->assertSame( [ 'Brand New Category', 'Cyber Security' ], $names );
	}

	public function test_downloads_inline_and_featured_images_and_rewrites_image_urls() {
		$this->runImport();

		$post = $this->importedPosts()[0];
		$this->assertStringNotContainsString( self::$PRESS . '/img-inline-1.png', $post->post_content );
		$this->assertStringContainsString( 'https://site.test/uploads/media/img-inline-1.png', $post->post_content );

		$thumbnailId = get_post_thumbnail_id( $post->ID );
		$this->assertGreaterThan( 0, $thumbnailId );
		$this->assertSame( 'Featured alt 1', get_post_meta( $thumbnailId, '_wp_attachment_image_alt', true ) );
	}

	public function test_keeps_the_original_wordpress_block_markup() {
		$this->runImport();

		$content = $this->importedPosts()[0]->post_content;
		$this->assertStringContainsString( '<!-- wp:paragraph -->', $content );
		$this->assertStringContainsString( 'class="aligncenter"', $content );
		$this->assertStringContainsString( '<strong>world</strong>', $content );
	}

	public function test_attribution_link_has_a_source_name_and_escaped_values() {
		$this->portalPosts = [ 901 => $this->portalPost( 1, [ 'title' => 'Say "Hello" & Goodbye' ] ) ];

		$this->runImport();

		$content = $this->importedPosts()[0]->post_content;
		$this->assertMatchesRegularExpression( '#Republished with Permission from <a [^>]*href="' . preg_quote( self::$PRESS, '#' ) . '/post-1/"#', $content );
		$this->assertStringNotContainsString( 'title="Say "Hello"', $content, 'Title attribute must be escaped' );

		preg_match( '#Republished with Permission from <a [^>]*>(.*?)</a>#', $content, $m );
		$this->assertNotSame( '', trim( rtrim( $m[1] ?? '', '.' ) ), 'Attribution link text (source name) must not be empty' );
	}

	public function test_updates_import_status_after_a_successful_import() {
		$this->runImport();

		$this->assertNotEmpty( get_option( 'ttt_client_last_checked' ) );
		$this->assertNotEmpty( get_option( 'ttt_client_last_download' ) );
	}

	/* ---------------------------------------------------------------- nothing new */

	public function test_skips_posts_whose_title_already_exists() {
		self::factory()->post->create( [ 'post_title' => 'Test Post 1' ] );

		$result = $this->runImport();

		$this->assertCount( 1, $this->importedPosts(), 'No duplicate created' );
		$this->assertTrue( $result['success'] );
		$this->assertSame( 0, $result['post_count_imported'] );
		$this->assertSame( $this->verbage( 'import', 'nothing' )['header'], $result['msg-header'] );
	}

	public function test_reports_success_when_some_posts_are_new_and_some_exist() {
		self::factory()->post->create( [ 'post_title' => 'Test Post 1' ] );
		$this->portalPosts = [ 901 => $this->portalPost( 1 ), 902 => $this->portalPost( 2 ) ];

		$result = $this->runImport();

		$this->assertSame( 1, $result['post_count_imported'] );
		$this->assertSame( $this->verbage( 'import', 'success' )['header'], $result['msg-header'] );
	}

	public function test_reports_nothing_to_import_when_the_portal_has_no_posts() {
		$this->scenario = 'empty';

		$result = $this->runImport();

		$this->assertTrue( $result['success'] );
		$this->assertSame( 0, $result['post_count_imported'] );
		$this->assertSame( $this->verbage( 'import', 'nothing' )['header'], $result['msg-header'] );
		$this->assertCount( 0, $this->importedPosts() );
	}

	/* ---------------------------------------------------------------- errors */

	public static function error_scenarios() {
		// scenario => [ expected header, expected message (null = header only), key still active ]
		return [
			'wrong api key'          => [ 'invalid', [ 'api', 'error' ], false ],
			'membership tag missing' => [ 'ac_tag', [ 'ac_tag', 'error' ], false ],
			'domain not approved'    => [ 'domain', [ 'domain', 'error' ], false ],
			'rate limited'           => [ 'rate_limited', 'UH-OH - TOO MANY ATTEMPTS', true ],
			'portal unavailable'     => [ 'unavailable', 'UH-OH - SERVICE UNAVAILABLE', true ],
			'blog source down'       => [ 'upstream_down', 'UH-OH - SERVICE UNAVAILABLE', true ],
			'request timeout'        => [ 'timeout', [ 'general_error', 'timeout' ], true ],
			'response not json'      => [ 'not_json', [ 'general_error', 'error' ], true ],
			'portal route missing'   => [ 'no_route', [ 'general_error', 'not_found' ], true ],
		];
	}

	/**
	 * @dataProvider error_scenarios
	 */
	public function test_portal_errors_show_the_right_message_and_key_state( $scenario, $expected, $keyStaysActive ) {
		$this->scenario = $scenario;

		$result = $this->runImport();

		$this->assertFalse( $result['success'] );
		$this->assertCount( 0, $this->importedPosts() );
		if ( is_array( $expected ) ) {
			$verbage = $this->verbage( $expected[0], $expected[1] );
			$this->assertSame( $verbage['header'], $result['msg-header'] );
			$this->assertSame( $verbage['msg'], $result['msg'] );
		} else {
			$this->assertSame( $expected, $result['msg-header'] );
		}
		$this->assertSame( $keyStaysActive, tttIsKeyActive(), $keyStaysActive ? 'Key must stay active' : 'Key must be marked inactive' );
		if ( ! $keyStaysActive ) {
			$this->assertSame( $result['msg'], tttLastVerifyError(), 'The reason must be stored for the "Inactive" status' );
		}
		$this->assertNotEmpty( get_option( 'ttt_client_last_checked' ), 'Last check must be recorded even on errors' );
	}

	public function test_without_an_api_key_no_request_is_sent() {
		delete_option( 'ttt_api_key_blog_post' );

		$result = $this->runImport();

		$this->assertFalse( $result['success'] );
		$this->assertCount( 0, $this->portalRequests );
	}

	/* ---------------------------------------------------------------- request contract */

	public function test_requests_posts_from_portal_v2_with_raw_content() {
		$this->runImport();

		$request = $this->lastPortalRequest();
		$this->assertSame( 'v2', $request['version'] );
		$this->assertSame( 'post/get', $request['endpoint'] );
		$this->assertSame( site_url(), $request['body']['user_domain'] );
		$this->assertSame( self::$API_KEY, $request['body']['user_api_key'] );
		$this->assertSame( 'raw', $request['body']['content_format'] ?? null );
		$this->assertSame( 0, $this->unexpectedRequests );
	}

	/* ---------------------------------------------------------------- content and media robustness */

	public function test_a_missing_image_is_not_saved_as_an_attachment_nor_blanked_in_content() {
		$this->portalPosts = [ 901 => $this->portalPost( 1, [
			'content' => '<p><img src="' . self::$PRESS . '/missing-1.png"></p>',
			'meta'    => [ 'images' => [ 'contents' => [ self::$PRESS . '/missing-1.png' ] ] ],
		] ) ];

		$this->runImport();

		$htmlAttachments = get_posts( [ 'post_type' => 'attachment', 'post_status' => 'any', 'post_mime_type' => 'text/html' ] );
		$this->assertCount( 0, $htmlAttachments, 'A 404 page must not become an attachment' );
		$this->assertStringNotContainsString( 'src=""', $this->importedPosts()[0]->post_content );
	}

	public function test_scheduled_date_is_stored_with_the_correct_gmt_time() {
		update_option( 'timezone_string', 'Australia/Sydney' );

		$this->runImport();

		$post = $this->importedPosts()[0];
		$this->assertSame( get_gmt_from_date( $post->post_date ), $post->post_date_gmt );
	}

	public function test_line_breaks_in_content_do_not_glue_words_together() {
		$this->portalPosts = [ 901 => $this->portalPost( 1, [ 'content' => "<p>first\nsecond</p>" ] ) ];

		$this->runImport();

		$this->assertStringNotContainsString( 'firstsecond', $this->importedPosts()[0]->post_content );
	}

	public function test_a_missing_featured_image_does_not_set_a_thumbnail() {
		$this->portalPosts = [ 901 => $this->portalPost( 1, [ 'meta' => [ 'images' => [ 'featured' => [ 'image' => self::$PRESS . '/missing-featured.png' ] ] ] ] ) ];

		$this->runImport();

		$this->assertSame( 0, (int) get_post_thumbnail_id( $this->importedPosts()[0]->ID ) );
		$this->assertCount( 0, get_posts( [ 'post_type' => 'attachment', 'post_status' => 'any', 'post_mime_type' => 'text/html' ] ) );
	}

	public function test_a_blank_category_name_does_not_stop_the_import() {
		$this->portalPosts = [ 901 => $this->portalPost( 1, [ 'categories' => [ '   ', 'Valid Category' ] ] ) ];

		$result = $this->runImport();

		$this->assertSame( 1, $result['post_count_imported'] );
		$this->assertSame( [ 'Valid Category' ], wp_get_post_categories( $this->importedPosts()[0]->ID, [ 'fields' => 'names' ] ) );
	}

	public function test_the_post_is_created_with_its_content_not_empty() {
		$createdWith = null;
		add_action( 'wp_insert_post', function ( $id, $post, $update ) use ( &$createdWith ) {
			if ( ! $update && 'post' === $post->post_type && null === $createdWith ) {
				$createdWith = $post->post_content;
			}
		}, 10, 3 );

		$this->runImport();

		$this->assertNotEmpty( $createdWith, 'If the run dies while images download, the post must not be left empty' );
		$this->assertStringContainsString( 'Hello <strong>world</strong>', $createdWith );
	}

	public function test_imports_the_same_html_whether_run_by_cron_or_by_an_admin() {
		$content = '<p>Video</p><iframe src="https://www.youtube.com/embed/abc123" width="560" height="315" allowfullscreen></iframe><script>alert(1)</script>';

		foreach ( [ 'cron' => 0, 'admin' => $this->adminId ] as $who => $userId ) {
			wp_set_current_user( $userId );
			$this->portalPosts = [ 901 => $this->portalPost( 1, [ 'title' => "Embed via $who", 'content' => $content ] ) ];

			$this->runImport();

			$saved = get_posts( [ 'title' => "Embed via $who", 'post_status' => 'any' ] )[0]->post_content;
			$this->assertStringContainsString( '<iframe src="https://www.youtube.com/embed/abc123"', $saved, "Embeds must be kept ($who)" );
			$this->assertStringNotContainsString( '<script', $saved, "Scripts must be removed ($who)" );
		}
	}

	public function test_posts_get_an_administrator_as_author_when_no_default_author_is_set() {
		delete_option( 'ttt_auto_post_author' );
		wp_set_current_user( 0 );

		$this->runImport();

		$author = (int) $this->importedPosts()[0]->post_author;
		$this->assertGreaterThan( 0, $author );
		$this->assertTrue( user_can( $author, 'manage_options' ) );
	}

	/* ---------------------------------------------------------------- overlapping runs */

	public function test_a_second_import_does_not_start_while_one_is_running() {
		add_option( 'ttt_import_lock', time(), '', 'no' );

		$result = $this->runImport();

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'UH-OH - IMPORT ALREADY RUNNING', $result['msg-header'] );
		$this->assertCount( 0, $this->portalRequests );
	}

	public function test_a_stale_lock_from_a_crashed_run_does_not_block_imports() {
		add_option( 'ttt_import_lock', time() - HOUR_IN_SECONDS, '', 'no' );

		$result = $this->runImport();

		$this->assertTrue( $result['success'] );
	}

	public function test_the_lock_is_released_after_success_and_after_errors() {
		$this->runImport();
		$this->assertFalse( get_option( 'ttt_import_lock' ) );

		$this->scenario = 'timeout';
		$this->runImport();
		$this->assertFalse( get_option( 'ttt_import_lock' ) );
	}

	public function test_titles_with_special_characters_are_not_duplicated_by_cron_and_admin_runs() {
		$this->portalPosts = [ 901 => $this->portalPost( 1, [ 'title' => 'Fish & Chips <3 "Quotes"' ] ) ];

		wp_set_current_user( 0 );
		$this->runImport();
		wp_set_current_user( $this->adminId );
		$this->runImport();
		wp_set_current_user( 0 );
		$this->runImport();

		$this->assertCount( 1, $this->importedPosts() );
	}
}
