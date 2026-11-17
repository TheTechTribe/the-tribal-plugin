<form action="<?php echo admin_url('admin.php?page=the-tribal-plugin#api-key');?>" method="post" class="dashboard-form">
    <div class="mb-3">
        <label class="form-label">API Key</label>
        <input type="password" class="form-control" name="ttt_api_key" value="" placeholder="<?php echo esc_attr( $apiKey ? 'A key is saved. Paste a new key to re-activate.' : 'Paste your API key here' ); ?>">
        <div id="apiHelp" class="ttt-form-text">
            <div class="container-ttt-content">
                <div class="row">
                    <div class="col-md-1">STATUS: </div>
                    <div class="col-md-11">
                        <?php if(!tttIsKeyActive()) : ?>
                            <span style="color:red;font-weight:bold;">Inactive</span> (Please grab your API key <a href="https://portal.thetechtribe.com/my-tribe-membership" target="_blank">from here</a>)
                            <?php $tttLastVerifyError = tttLastVerifyError(); ?>
                            <?php if ( $tttLastVerifyError ) : ?>
                                <div class="ttt-inactive-reason"><b>Last check:</b> <?php echo esc_html( $tttLastVerifyError ); ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color:green;font-weight:bold;">Active</span> (You're good to go!)
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <input type="hidden" name="action" value="ttt_activate_key">
    <?php wp_nonce_field( 'ttt_client_update_plugin' ); ?>
    <button type="submit" class="btn btn-primary" data-loading-text="Checking your API key…">ACTIVATE API KEY</button>
</form>