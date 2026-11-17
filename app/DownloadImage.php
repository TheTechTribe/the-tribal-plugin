<?php
namespace TheTribalPlugin;

use WP_Error;
//use DownloadRemoteImage;

class DownloadImage
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
    
    public function download( $args = [] )
    {
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        return $this->uploadRemoteImage($args);
    }

    public function uploadRemoteImage($args = [])
    {
        $image_url = $args['file_url'] ?? '';
        $parent_id = $args['parent_post_id'] ?? 0;
        $alt_text  = $args['alt_text'] ?? '';

        $image = $image_url;

        $logger = Logger::get_instance();
        $requestStarted = microtime(true);

        $get = wp_remote_get( $image );

        $type = wp_remote_retrieve_header( $get, 'content-type' );
        $httpCode = wp_remote_retrieve_response_code( $get );

        $logger->log(
            ( is_wp_error( $get ) || 200 != $httpCode ) ? 'error' : 'info',
            'Image download',
            [
                'url'          => $image,
                'parent_post'  => $parent_id,
                'duration_s'   => round(microtime(true) - $requestStarted, 2),
                'http_code'    => $httpCode,
                'content_type' => $type,
                'bytes'        => strlen( wp_remote_retrieve_body( $get ) ),
                'wp_error'     => is_wp_error( $get ) ? $get : null,
            ]
        );

        // Error pages (404 HTML etc.) must never become attachments; the caller keeps the remote URL.
        if ( is_wp_error( $get ) || 200 != $httpCode || 0 !== strpos( (string) $type, 'image/' ) ) {
            $logger->error('Image skipped: download failed or response is not an image', ['url' => $image, 'http_code' => $httpCode, 'content_type' => $type]);
            return false;
        }

        $mirror = wp_upload_bits( basename( (string) wp_parse_url( $image, PHP_URL_PATH ) ), '', wp_remote_retrieve_body( $get ) );

        if ( ! empty( $mirror['error'] ) ) {
            $logger->error('wp_upload_bits failed', ['url' => $image, 'error' => $mirror['error']]);
            return false;
        }

        $attachment = array(
            'post_title'=> basename( $image ),
            'post_mime_type' => $type
        );

        $attach_id = wp_insert_attachment( $attachment, $mirror['file'], $parent_id );

        $logger->log(
            $attach_id ? 'info' : 'error',
            $attach_id ? 'Attachment created' : 'wp_insert_attachment failed',
            ['url' => $image, 'attachment_id' => $attach_id, 'file' => $mirror['file'], 'parent_post' => $parent_id]
        );

        if($alt_text != ''){
            update_post_meta($attach_id, '_wp_attachment_image_alt', $alt_text);
        }

        $attach_data = wp_generate_attachment_metadata( $attach_id, $mirror['file'] );

        wp_update_attachment_metadata( $attach_id, $attach_data );

        return $attach_id;
    }


    //https://developer.wordpress.org/reference/functions/media_sideload_image/
    public function mediaSideLoadImage($args = [])
    {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $file_url = $args['file_url'] ?? '';
        $post_id = $args['post_id'] ?? 0;
        $desc = $args['desc'] ?? null;
        $return = $args['return'] ?? 'id';

        return media_sideload_image($file_url, $post_id, $desc, $return);
    }
    
}