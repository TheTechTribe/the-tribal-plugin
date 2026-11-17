<?php
namespace TheTribalPlugin;

use DateInterval;

class ImportPost
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

	public function __construct(){}

    /** Option used as a lock so cron and manual imports never overlap. */
    const LOCK_OPTION = 'ttt_import_lock';

    /** A lock older than this (seconds) is from a crashed run and may be taken over. */
    const LOCK_TTL = 900;

    /** Minimum PHP time limit (seconds) an import asks for when the host allows it. */
    const MIN_TIME_LIMIT = 300;

    private $addedContentKses = false;

    public function import($args = [])
    {
        if( ! $this->acquireLock() ) {
            $running = \TheTribalPlugin\StatusVerbage::get_instance()->get('import')['running'];
            Logger::get_instance()->warning('Import skipped: another import is already running', ['locked_since' => get_option(self::LOCK_OPTION)]);

            return rest_ensure_response([
                'success'             => false,
                'code'                => 'error',
                'msg-header'          => $running['header'],
                'msg'                 => $running['msg'],
                'summary'             => [],
                'post_count_imported' => 0,
            ]);
        }

        $this->enableImportKses();
        try {
            return $this->doImport($args);
        } finally {
            $this->restoreKses();
            delete_option(self::LOCK_OPTION);
        }
    }

    private function acquireLock()
    {
        if( add_option(self::LOCK_OPTION, time(), '', 'no') ) {
            return true;
        }
        $lockedSince = (int) get_option(self::LOCK_OPTION);
        if( $lockedSince && ( time() - $lockedSince ) < self::LOCK_TTL ) {
            return false;
        }
        Logger::get_instance()->warning('Taking over a stale import lock', ['locked_since' => $lockedSince]);
        update_option(self::LOCK_OPTION, time(), 'no');
        return true;
    }

    /**
     * Imported content gets the same HTML filtering whoever runs the import (cron has no user,
     * an admin has unfiltered_html): the standard post whitelist plus embeds (iframes).
     * Titles are left to WordPress' normal handling so duplicate-title checks keep matching.
     */
    private function enableImportKses()
    {
        $this->addedContentKses = ! has_filter('content_save_pre', 'wp_filter_post_kses');
        if( $this->addedContentKses ) {
            add_filter('content_save_pre', 'wp_filter_post_kses');
        }
        add_filter('wp_kses_allowed_html', [$this, 'allowEmbeds'], 10, 2);
    }

    private function restoreKses()
    {
        if( $this->addedContentKses ) {
            remove_filter('content_save_pre', 'wp_filter_post_kses');
        }
        remove_filter('wp_kses_allowed_html', [$this, 'allowEmbeds'], 10);
    }

    public function allowEmbeds($tags, $context)
    {
        if( 'post' === $context ) {
            $tags['iframe'] = array_fill_keys(
                ['src', 'width', 'height', 'title', 'frameborder', 'allow', 'allowfullscreen', 'loading', 'referrerpolicy', 'class', 'style'],
                true
            );
        }
        return $tags;
    }

    private function doImport($args = [])
    {
        require_once ABSPATH . 'wp-admin/includes/post.php';

        $logger = Logger::get_instance();
        $logger->captureFatalErrors();
        $importStarted = microtime(true);
        $logger->info('Import started', $logger->environment());

        // Image downloads may take up to 2 x 30s each; give the run room on hosts with a short
        // PHP time limit. Never shorten it (0 means unlimited).
        $timeLimit = (int) ini_get('max_execution_time');
        if( $timeLimit > 0 && $timeLimit < self::MIN_TIME_LIMIT && function_exists('set_time_limit') ) {
            $raised = @set_time_limit(self::MIN_TIME_LIMIT);
            $logger->info('PHP time limit for this import', ['was' => $timeLimit, 'raised_to' => self::MIN_TIME_LIMIT, 'allowed_by_host' => (bool) $raised]);
        }

        $statusVerbage = \TheTribalPlugin\StatusVerbage::get_instance()->get('import');

        $ret = [
            'success' => false,
            'msg-header' => $statusVerbage['error']['header'],
            'msg' => $statusVerbage['error']['msg'],
            'code' => 'error',
            'summary' => []
        ];
        
        tttResetDownloadStatusStartEnd();
        
        tttStartImport();

        $countSuccess = 0;

        $currentUserId = WPOptions::get_instance()->defaultAuthor();
        if( empty($currentUserId) ) {
            // Settings never saved: cron has no current user, so posts would get author 0.
            $admins = get_users(['role' => 'administrator', 'orderby' => 'ID', 'order' => 'ASC', 'number' => 1, 'fields' => 'ID']);
            $currentUserId = $admins ? (int) $admins[0] : 0;
            $logger->warning('No default author set; using the first administrator', ['user_id' => $currentUserId]);
        }

        //insert the post
        $post = new Post;
        // ['ok', 'status', 'code', 'msg', 'data'], see APIPortal::post().
        $getPost = $post->get($args);
        $portalData = $getPost['data'];
        $portalPosts = isset($portalData['posts']) && is_array($portalData['posts']) ? $portalData['posts'] : [];

        $logger->info('Portal post/get result', [
            'ok'             => $getPost['ok'],
            'status'         => $getPost['status'],
            'code'           => $getPost['code'],
            'msg'            => $getPost['msg'],
            'total_post'     => $portalData['total_post'] ?? null,
            'posts_received' => count($portalPosts),
            'blog_name'      => $portalData['current_blog_name'] ?? null,
            'blog_url'       => $portalData['current_post_url'] ?? null,
        ]);

        if( ! $getPost['ok'] ) {
            $portalError = APIPortal::get_instance()->errorMessage($getPost);
            $ret['msg-header'] = $portalError['header'];
            $ret['msg'] = $portalError['msg'];
            $ret['post_count_imported'] = 0;

            if( $portalError['deactivate'] ) {
                tttSetKeyActive(0);
            }
            $logger->error('Import stopped: portal returned an error', ['status' => $getPost['status'], 'code' => $getPost['code'], 'key_deactivated' => $portalError['deactivate']]);
        } elseif( empty($portalPosts) ) {
            $ret['success'] = true;
            $ret['msg-header'] = $statusVerbage['nothing']['header'];
            $ret['msg'] = $statusVerbage['nothing']['msg'];
            $ret['code'] = 'success';
            $ret['post_count_imported'] = 0;
        } else {
            $dataPost = $portalPosts;
            // The portal does not send current_blog_name today; fall back to the source host.
            $blogName = ! empty($portalData['current_blog_name'])
                ? $portalData['current_blog_name']
                : (string) wp_parse_url($portalData['current_post_url'] ?? '', PHP_URL_HOST);

            $publishPostSetting = WPOptions::get_instance()->publishPosts();
            
            $postStatus = 'draft';
            
            $postData = [];
            $post_id = 0;

            foreach($dataPost as $post) {
                $postContent = '';
                $logger->info('Portal post', [
                    'title'            => $post['title'] ?? null,
                    'date_schedule'    => $post['meta']['date_schedule'] ?? null,
                    'categories'       => $post['categories'] ?? null,
                    'permalink'        => $post['get_the_permalink'] ?? null,
                    'content_bytes'    => isset($post['content']) ? strlen($post['content']) : null,
                    'inline_images'    => $post['meta']['images']['contents'] ?? [],
                    'featured_image'   => $post['meta']['images']['featured']['image'] ?? null,
                ]);
                $existingPostId = $this->findExistingPost($post['title']);
                if( ! $existingPostId ) {
                    //insert post
                    $post_date = date_i18n( 'Y-m-d H:i:s' );
                    $post_date_schedule = date_i18n( 'Y-m-d H:i:s', strtotime($post['meta']['date_schedule'] . ' 12:00:00'));
                    if( $post['meta']['date_schedule'] != '' ) {
                        $post_date_schedule = date_i18n('Y-m-d H:i:s', strtotime($post['meta']['date_schedule'] . ' 12:00:00'));
                        if( $post['meta']['date_schedule'] > $post_date) {
                            $postStatus = 'draft';
                            //$postStatus = 'future';
                        }
                    }

                    if( $publishPostSetting == 'auto' ) {
                        $postStatus = 'publish';
                    }
                    
                    // Created with its content (remote image URLs) so a run that dies while images
                    // download never leaves an empty post; local image URLs are swapped in below.
                    $remoteContent = $this->prepareContent($post['content'] ?? '', $post, $blogName);

                    $postData = [
                        'post_title'        => $post['title'],
                        'post_status'       => $postStatus,
                        'post_date'         => $post_date_schedule,
                        'post_date_gmt'     => get_gmt_from_date($post_date_schedule),
                        'post_author'       => $currentUserId,
                        'post_content'      => $remoteContent,
                    ];
                    
                    $post_id = wp_insert_post($postData, true);
                    //insert post

                    if( is_wp_error($post_id) ) {
                        $logger->error('wp_insert_post failed', ['post_data' => $postData, 'error' => $post_id]);
                        $post_id = 0;
                    } else {
                        $logger->info('Post created', [
                            'post_id'     => $post_id,
                            'post_data'   => $postData,
                            'final_status'=> get_post_status($post_id),
                        ]);
                    }

                    if($post_id) {

                        $ret['summary']['post'][$post_id]['id'] = $post_id;
                        $ret['summary']['post'][$post_id]['title'] = $post['title'];
                        
                        $countSuccess++;
                        
                        //insert category
                        $taxonomy = 'category';
                        $postCategory = $post['categories'];
                        if(!empty($postCategory)) {
                            $arrInsertTaxonomy = [
                                'categories'    => $postCategory,
                                'post_id'       => $post_id
                            ];
                            $category_id = $this->insertTaxonomy($arrInsertTaxonomy);
                            if($category_id != 0 || is_array($category_id)){
                                $setTerms = wp_set_object_terms( $post_id, $category_id, $taxonomy );
                                $logger->info('Categories set', ['post_id' => $post_id, 'categories' => $postCategory, 'term_ids' => $category_id, 'result' => $setTerms]);
                            }
                        }
                        //insert category
                        
                        $postImages = isset($post['meta']['images']['contents']) ? $post['meta']['images']['contents'] : [];
                        $searchImageToReplaces = [];
                        $actualImageToReplaces = [];
                        //download images, more of inline image in the content
                        if(is_array($postImages) && count($postImages) >= 1) {
                            foreach($postImages as $postImage) {
                                $downloadImages = \TheTribalPlugin\DownloadImage::get_instance()->download([
                                	'file_url' => $postImage,
                                	'parent_post_id' => $post_id
                                ]);
                                $wp_get_attachment_url = $downloadImages ? wp_get_attachment_url($downloadImages) : false;
                                if( ! $wp_get_attachment_url ) {
                                    $logger->warning('Inline image not downloaded; keeping its remote URL', ['post_id' => $post_id, 'image' => $postImage]);
                                    continue;
                                }
                                $searchImageToReplaces[] = $postImage;
                                $actualImageToReplaces[] = $wp_get_attachment_url;
                                $ret['summary']['post'][$post_id]['attach_image'][$downloadImages]['id'] = $downloadImages;
                                $ret['summary']['post'][$post_id]['attach_image'][$downloadImages]['old_file_name'] = $postImage;
                                $ret['summary']['post'][$post_id]['attach_image'][$downloadImages]['file_name'] = $wp_get_attachment_url;
                            }
                        }
                        //download images, more of inline image in the content

                        //download images, set as featured image
                        $postFeaturedImage = isset($post['meta']['images']['featured']['image']) ? $post['meta']['images']['featured']['image'] : '';
                        if(!empty($postFeaturedImage)) {
                            $featuredAttachmentId = \TheTribalPlugin\DownloadImage::get_instance()->download([
                            	'file_url' => $postFeaturedImage,
                            	'parent_post_id' => $post_id,
                                'alt_text' => $post['meta']['images']['featured']['alt_text'] ?? ''
                            ]);
                            if( $featuredAttachmentId ) {
                                $ret['summary']['post'][$post_id]['featured_image'][$featuredAttachmentId]['id'] = $featuredAttachmentId;
                                $ret['summary']['post'][$post_id]['featured_image'][$featuredAttachmentId]['file_name'] = wp_get_attachment_url($featuredAttachmentId);
                                $thumbnailSet = set_post_thumbnail( $post_id, $featuredAttachmentId );
                                $logger->info('Featured image', ['post_id' => $post_id, 'image' => $postFeaturedImage, 'attachment_id' => $featuredAttachmentId, 'thumbnail_set' => $thumbnailSet]);
                            } else {
                                $logger->warning('Featured image not downloaded; post has no thumbnail', ['post_id' => $post_id, 'image' => $postFeaturedImage]);
                            }
                        } else {
                            $logger->warning('Portal post has no featured image', ['post_id' => $post_id]);
                        }
                        //download images, set as featured image

                        //update the post with local image URLs
                        $postContent = $this->prepareContent(str_replace($searchImageToReplaces, $actualImageToReplaces, $post['content'] ?? ''), $post, $blogName);

                        $argUpdate = [
                            'ID'                => $post_id,
                            'post_modified'     => date_i18n( 'Y-m-d H:i' ),
                            'post_modified_gmt' => date_i18n( 'Y-m-d H:i' ),
                            'post_content'      => $postContent
                        ];
                        $updated = wp_update_post( $argUpdate, true );
                        if( is_wp_error($updated) ) {
                            $logger->error('wp_update_post (content) failed; post left without content', ['post_id' => $post_id, 'error' => $updated]);
                        } else {
                            $savedContent = get_post_field('post_content', $post_id);
                            $logger->info('Post content saved', [
                                'post_id'         => $post_id,
                                'content_bytes'   => strlen($postContent),
                                'saved_bytes'     => strlen((string) $savedContent),
                                'images_replaced' => count($searchImageToReplaces),
                            ]);
                            if( strlen((string) $savedContent) < strlen($postContent) ) {
                                $logger->warning('Saved content is shorter than imported content (HTML filtered by kses?)', ['post_id' => $post_id, 'unfiltered_html' => current_user_can('unfiltered_html')]);
                            }
                        }
                        //update the post
                    }
                } else {
                    //exists
                    $logger->info('Skipped: a post with this title already exists', ['title' => $post['title'], 'existing_post_id' => $existingPostId, 'existing_status' => get_post_status($existingPostId)]);
                    $ret['summary']['exists']['post'][]['title'] = $post['title'];
                }
            }
        }

        if( $countSuccess > 0 ) {
            $ret['success'] = true;
            $ret['msg-header'] = $statusVerbage['success']['header'];
            $ret['msg'] = $statusVerbage['success']['msg'];
            $ret['code'] = 'success';
            $ret['post_count_imported'] = $countSuccess;
            
            tttLastDownload();
        }

        // Every portal post already exists here.
        if( $countSuccess == 0 && ! empty($ret['summary']['exists']['post']) ) {
            $ret['success'] = true;
            $ret['msg-header'] = $statusVerbage['nothing']['header'];
            $ret['msg'] = $statusVerbage['nothing']['msg'];
            $ret['code'] = 'success';
            $ret['post_count_imported'] = 0;
        }
        
        tttLastChecked();
        
        tttLastCheckedStatus($ret['code'], $ret['msg']);

        tttEndImport();

        tttLogReturn($ret);

        $logger->info('Import finished', [
            'success'             => $ret['success'] ?? null,
            'code'                => $ret['code'] ?? null,
            'msg_header'          => $ret['msg-header'] ?? null,
            'msg'                 => $ret['msg'] ?? null,
            'post_count_imported' => $countSuccess,
            'posts_skipped'       => isset($ret['summary']['exists']['post']) ? count($ret['summary']['exists']['post']) : 0,
            'key_active'          => tttIsKeyActive(),
            'duration_s'          => round(microtime(true) - $importStarted, 2),
            'peak_memory_mb'      => round(memory_get_peak_usage(true) / 1048576, 1),
        ]);
        $logger->debug('Import result detail', $ret);

        return rest_ensure_response($ret);
    }

    /**
     * ID of a post (any type or status, like post_exists()) with this title, 0 if none.
     * Cron runs (no user) store titles HTML-filtered ("Fish &amp; Chips") while admin runs store
     * them as sent, so both forms are matched.
     */
    private function findExistingPost($title)
    {
        global $wpdb;

        $title = trim((string) $title);
        $candidates = array_values(array_unique([
            $title,
            trim(wp_unslash(wp_filter_kses(wp_slash($title)))),
        ]));
        $placeholders = implode(',', array_fill(0, count($candidates), '%s'));

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_title IN ($placeholders) ORDER BY ID ASC LIMIT 1",
            $candidates
        ));
    }

    /**
     * Content as stored: line breaks become spaces (never gluing words), plus the attribution line.
     */
    private function prepareContent($content, $post, $blogName)
    {
        $content = str_replace(["\r\n", "\n", "\r"], ' ', (string) $content);
        $content .= '<p>This Article has been Republished with Permission from <a rel="canonical" href="'.esc_url($post['get_the_permalink'] ?? '').'" title="'.esc_attr($post['title'] ?? '').'" target="_blank">'.esc_html($blogName).'.</a></p>';

        return $content;
    }

    /**
     * to insert category
	 *	- check if category exists
	 *	- if not, create
	 *	- if yes, get the ID
	 *	- insert it to post
     */
    private function insertTaxonomy( $args = [] )
    {
        $categoryFromPress = $args['categories'] ?? [];
        $taxonomy = $args['taxonomy'] ?? 'category';
        $postId = $args['post_id'] ?? false;
        $term_id = [];
        if(!empty($categoryFromPress) && $postId){
            foreach($categoryFromPress as $category) {
                $termName = $category;
                $category = get_term_by('name', $termName, $taxonomy);
                if(!$category){
                    //create the term/category
                	$retCat = wp_insert_term($termName, $taxonomy);
                    if( is_wp_error($retCat) ) {
                        // Skip this category; the post keeps the others.
                        Logger::get_instance()->error('wp_insert_term failed; category skipped', ['term' => $termName, 'taxonomy' => $taxonomy, 'error' => $retCat]);
                        continue;
                    }
                    Logger::get_instance()->info('Category created', ['term' => $termName, 'term_id' => $retCat['term_id']]);
                    $term_id[] = $retCat['term_id'];
                }else{
                    //get the term/category
                    $term_id[] = $category->term_id;
                }
            }
       }

       return $term_id;
    }
    
}