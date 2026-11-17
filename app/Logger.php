<?php
namespace TheTribalPlugin;

/**
 * Verbose plugin logger.
 *
 * Writes to a randomly named file in uploads/ttt-logs/ (protected by .htaccess + index.php).
 * Before anything is written, values under secret-looking keys (API keys, tokens, passwords),
 * any registered secret strings and email addresses are masked.
 */
class Logger
{
	const DIR_NAME   = 'ttt-logs';
	const MAX_BYTES  = 2097152; // rotate at 2MB, one backup (.1) kept
	const MAX_STRING = 20000;   // long strings (e.g. response bodies) are truncated
	const SECRET_KEYS = '/(api_?key|password|passwd|secret|token|authorization|cookie|nonce)/i';

	/**
	 * instance of this class
	 *
	 * @access protected
	 * @var	null
	 * */
	protected static $instance = null;

	/** Short id shared by every line written during one PHP request, to group a run. */
	private $requestId;

	/** Resolved log file path, false when no writable location, null when not resolved yet. */
	private $file = null;

	/** Extra strings to mask wherever they appear (e.g. an API key being validated). */
	private $secrets = [];

	private $fatalHandlerRegistered = false;

	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function __construct()
	{
		$this->requestId = substr( md5( uniqid( '', true ) ), 0, 8 );
	}

	public function init()
	{
		add_action( 'admin_post_ttt_download_log', [$this, 'download'] );
	}

	public function debug( $message, $context = [] )   { $this->log( 'debug', $message, $context ); }
	public function info( $message, $context = [] )    { $this->log( 'info', $message, $context ); }
	public function warning( $message, $context = [] ) { $this->log( 'warning', $message, $context ); }
	public function error( $message, $context = [] )   { $this->log( 'error', $message, $context ); }

	public function log( $level, $message, $context = [] )
	{
		$line = sprintf(
			'%s UTC | %s local | %-7s | %s | %s',
			gmdate( 'Y-m-d H:i:s' ),
			date_i18n( 'Y-m-d H:i:s' ),
			strtoupper( $level ),
			$this->requestId,
			$message
		);

		if ( ! empty( $context ) ) {
			$line .= ' | ' . wp_json_encode(
				$this->redact( $context ),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
					| ( defined( 'JSON_INVALID_UTF8_SUBSTITUTE' ) ? JSON_INVALID_UTF8_SUBSTITUTE : 0 )
			);
		}

		$line = $this->maskStrings( $line );

		$file = $this->file();
		// Unwritable when e.g. created by the web server user and written from WP-CLI as another user.
		if ( ! $file || ( file_exists( $file ) && ! is_writable( $file ) ) ) {
			error_log( 'The-Tribal-Plugin: ' . $line );
			return;
		}

		$this->rotate( $file );
		file_put_contents( $file, $line . "\n", FILE_APPEND | LOCK_EX );
	}

	/**
	 * Mask this string wherever it appears in later log lines.
	 */
	public function addSecret( $secret )
	{
		$secret = (string) $secret;
		if ( strlen( $secret ) >= 6 ) {
			$this->secrets[] = $secret;
		}
	}

	/**
	 * Log PHP fatal errors (uncaught exceptions, timeouts, memory) that end the current request.
	 */
	public function captureFatalErrors()
	{
		if ( $this->fatalHandlerRegistered ) {
			return;
		}
		$this->fatalHandlerRegistered = true;

		// WordPress' own fatal error handler runs first and wp_die()s, which stops later
		// shutdown functions, so log from inside it via this filter...
		add_filter( 'wp_php_error_message', function ( $message, $error ) {
			$this->logFatal( $error );
			return $message;
		}, 10, 2 );

		// ...and fall back to a shutdown function when that handler is disabled.
		register_shutdown_function( function () {
			$this->logFatal( error_get_last() );
		} );
	}

	private $fatalLogged = false;

	private function logFatal( $error )
	{
		if ( $this->fatalLogged || ! $error || ! in_array( $error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true ) ) {
			return;
		}
		$this->fatalLogged = true;
		$this->error( 'PHP fatal error, request aborted', $error );
	}

	/**
	 * Site and plugin state useful for diagnosing an import.
	 */
	public function environment()
	{
		global $wp_version;

		$nextCron = wp_next_scheduled( 'ttt_user_cron_hook' );

		return [
			'plugin_version'     => defined( 'THE_TRIBAL_PLUGIN_VERSION' ) ? THE_TRIBAL_PLUGIN_VERSION : '',
			'wp_version'         => $wp_version,
			'php_version'        => PHP_VERSION,
			'site_url'           => site_url(),
			'timezone'           => wp_timezone_string(),
			'max_execution_time' => ini_get( 'max_execution_time' ),
			'memory_limit'       => ini_get( 'memory_limit' ),
			'wp_cron_disabled'   => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'doing_cron'         => wp_doing_cron(),
			'doing_ajax'         => wp_doing_ajax(),
			'current_user_id'    => get_current_user_id(),
			'next_cron_utc'      => $nextCron ? gmdate( 'Y-m-d H:i:s', $nextCron ) : 'not scheduled',
			'key_active'         => get_option( 'ttt_is_active' ),
			'publish_setting'    => WPOptions::get_instance()->publishPosts(),
			'default_author'     => WPOptions::get_instance()->defaultAuthor(),
			'date_import_blog'   => WPOptions::get_instance()->dateImportBlog(),
		];
	}

	/**
	 * Path of the current log file, creating the protected folder if needed.
	 *
	 * @return string|false
	 */
	public function file()
	{
		if ( null !== $this->file ) {
			return $this->file;
		}
		$this->file = false;

		$dir = $this->dir();
		if ( ! $dir || ! wp_mkdir_p( $dir ) || ! is_writable( $dir ) ) {
			return false;
		}
		$this->protect( $dir );

		$existing = glob( $dir . '/ttt-*.log' );
		if ( $existing ) {
			$this->file = $existing[0];
		} else {
			$this->file = $dir . '/ttt-' . wp_generate_password( 32, false ) . '.log';
			$this->deleteLegacyFiles();
		}

		return $this->file;
	}

	/**
	 * Last $lines lines, newest first.
	 */
	public function tail( $lines = 300 )
	{
		$file = $this->file();
		if ( ! $file || ! file_exists( $file ) ) {
			return '';
		}

		$all = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

		// Strip invalid UTF-8 so esc_html() doesn't blank the whole view.
		return wp_check_invalid_utf8( implode( "\n", array_reverse( array_slice( (array) $all, -$lines ) ) ), true );
	}

	public function clear()
	{
		$file = $this->file();
		if ( ! $file ) {
			return;
		}
		if ( file_exists( $file . '.1' ) ) {
			unlink( $file . '.1' );
		}
		file_put_contents( $file, '' );
	}

	/**
	 * Remove the log folder and any legacy log files (used on deactivation).
	 */
	public function deleteAll()
	{
		$dir = $this->dir();
		if ( $dir && is_dir( $dir ) ) {
			foreach ( array_merge( (array) glob( $dir . '/*' ), [ $dir . '/.htaccess' ] ) as $path ) {
				if ( is_file( $path ) ) {
					unlink( $path );
				}
			}
			rmdir( $dir );
		}
		$this->file = null;

		$this->deleteLegacyFiles();
	}

	/**
	 * admin-post.php?action=ttt_download_log
	 */
	public function download()
	{
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Only a site administrator can download the activity log. Please ask an administrator to do this for you.', 'the-tech-tribe' ), 403 );
		}
		check_admin_referer( 'ttt_download_log' );

		$content = $this->downloadContent();

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="the-tribal-plugin-' . gmdate( 'Ymd-His' ) . '.log"' );
		echo $content;
		exit;
	}

	/**
	 * Full log history: the rotated backup (older) followed by the current file.
	 */
	public function downloadContent()
	{
		$file    = $this->file();
		$content = '';
		if ( $file && file_exists( $file . '.1' ) ) {
			$content .= file_get_contents( $file . '.1' );
		}
		if ( $file && file_exists( $file ) ) {
			$content .= file_get_contents( $file );
		}

		return $content;
	}

	private function dir()
	{
		$upload = wp_upload_dir( null, false );
		if ( ! empty( $upload['error'] ) || empty( $upload['basedir'] ) ) {
			return false;
		}

		return $upload['basedir'] . '/' . self::DIR_NAME;
	}

	private function protect( $dir )
	{
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents(
				$dir . '/.htaccess',
				"<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n"
			);
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		}
	}

	private function rotate( $file )
	{
		clearstatcache( true, $file );
		if ( file_exists( $file ) && filesize( $file ) > self::MAX_BYTES ) {
			if ( file_exists( $file . '.1' ) ) {
				unlink( $file . '.1' );
			}
			rename( $file, $file . '.1' );
		}
	}

	/**
	 * Log files written by plugin versions before 1.4.0. They could contain the API key
	 * and IP addresses and may sit in a publicly readable folder, so they are removed.
	 */
	private function deleteLegacyFiles()
	{
		$upload  = wp_upload_dir( null, false );
		$legacy  = [ ABSPATH . '../logs/' . TTT_LOG_FILE_NAME ];
		if ( ! empty( $upload['basedir'] ) ) {
			$legacy[] = $upload['basedir'] . '/' . TTT_LOG_FILE_NAME;
		}

		foreach ( $legacy as $path ) {
			if ( file_exists( $path ) && unlink( $path ) && $this->file ) {
				$this->info( 'Removed legacy log file', ['path' => $path] );
			}
		}
	}

	private function redact( $data, $depth = 0 )
	{
		if ( is_wp_error( $data ) ) {
			return ['wp_error_codes' => $data->get_error_codes(), 'wp_error_messages' => $data->get_error_messages()];
		}
		if ( is_object( $data ) ) {
			$data = json_decode( wp_json_encode( $data ), true );
		}
		if ( is_array( $data ) ) {
			if ( $depth > 10 ) {
				return '[max depth]';
			}
			foreach ( $data as $key => $value ) {
				$data[$key] = ( is_string( $key ) && preg_match( self::SECRET_KEYS, $key ) )
					? $this->mask( $value )
					: $this->redact( $value, $depth + 1 );
			}
			return $data;
		}
		if ( is_string( $data ) && strlen( $data ) > self::MAX_STRING ) {
			$cut = function_exists( 'mb_strcut' ) ? mb_strcut( $data, 0, self::MAX_STRING, 'UTF-8' ) : substr( $data, 0, self::MAX_STRING );
			return $cut . '...[truncated ' . ( strlen( $data ) - self::MAX_STRING ) . ' bytes]';
		}

		return $data;
	}

	private function mask( $value )
	{
		if ( '' === $value || null === $value ) {
			return $value;
		}
		if ( ! is_scalar( $value ) ) {
			return '[redacted]';
		}
		$value = (string) $value;

		return strlen( $value ) > 8 ? '****' . substr( $value, -4 ) : '****';
	}

	private function maskStrings( $line )
	{
		$secrets = $this->secrets;
		$storedKey = (string) WPOptions::get_instance()->apiKey();
		if ( strlen( $storedKey ) >= 6 ) {
			$secrets[] = $storedKey;
		}
		foreach ( array_unique( $secrets ) as $secret ) {
			$line = str_replace( $secret, $this->mask( $secret ), $line );
		}

		return preg_replace( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $line );
	}
}
