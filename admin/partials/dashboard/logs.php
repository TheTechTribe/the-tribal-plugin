<?php
$tttLogger  = \TheTribalPlugin\Logger::get_instance();
$tttLogFile = $tttLogger->file();
?>
<p>Detailed activity log of API key checks, imports, images and errors. Share it with <b>help@thetechtribe.com</b> if something isn't working. API keys and email addresses are masked.</p>
<?php if ( ! $tttLogFile ) : ?>
    <div class="alert alert-warning" role="alert">
        We can't save the activity log on this website, so new entries won't appear here. Please ask your website host to allow WordPress to save files in the <b>wp-content/uploads</b> folder, or email <b>help@thetechtribe.com</b> for help.
    </div>
<?php endif; ?>
<p class="ttt-form-text">Newest entries first (last 300 lines). Download the log for the full history.</p>
<pre class="ttt-log-view"><?php echo esc_html( $tttLogger->tail( 300 ) ); ?></pre>

<a class="btn btn-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ttt_download_log' ), 'ttt_download_log' ) ); ?>">DOWNLOAD LOG</a>

<form action="<?php echo esc_url( admin_url( 'admin.php?page=the-tribal-plugin#logs' ) ); ?>" method="post" class="d-inline" onsubmit="return confirm('Clear the activity log? This can\'t be undone.');">
    <input type="hidden" name="action" value="ttt_clear_logs">
    <?php wp_nonce_field( 'ttt_client_update_plugin' ); ?>
    <button type="submit" class="btn btn-outline-secondary">CLEAR LOG</button>
</form>
