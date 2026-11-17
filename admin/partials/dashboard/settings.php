<form action="<?php echo admin_url('admin.php?page=the-tribal-plugin#settings');?>" method="post" class="dashboard-form">
    <div class="mb-3">
        <label class="form-label">Auto or manual publishing of posts?</label>
        <select class="form-select" aria-label="Default select example" name="ttt_publish_post">
            <option value="manual" <?php echo ($publishPosts=='manual') ? 'selected':'';?>>Manual</option>
            <option value="auto" <?php echo ($publishPosts=='auto') ? 'selected':'';?>>Auto</option>
        </select>
        <div id="apiHelp" class="ttt-form-text">
            <div class="container-ttt-content">
                <div class="row">
                    <div class="col-md-1">AUTO: </div>
                    <div class="col-md-11">
                        All posts will automatically go live on their scheduled dates. Nothing for you to do.
                    </div>
                </div>
                    <div class="row">
                    <div class="col-md-1">MANUAL: </div>
                    <div class="col-md-11">
                        All posts will be saved as drafts, ready for you to tweak and publish when you're happy with them.
                    </div>
                </div>
            </div>
        </div>
    </div>
    <hr>
    <div class="mb-3">
        <label class="form-label">Select default author</label>
        <select class="form-select" aria-label="Default select author" name="ttt_post_author">
            <?php foreach($users as $user) : ?>
                <option value="<?php echo esc_attr($user->ID);?>" <?php echo ($defaultAuthor == $user->ID) ? 'selected':'';?>>
                    <?php esc_html_e($user->display_name);?>
                </option>
            <?php endforeach; ?>
        </select>
        <div id="apiHelp" class="ttt-form-text">
            <div class="container-ttt-content">
                Choose the author you want all imported posts to be assigned to.
            </div>
        </div>
    </div>
    <hr>
    <input type="hidden" name="action" value="ttt_update_dashboard_user">
    <?php wp_nonce_field( 'ttt_client_update_plugin' ); ?>
    <button type="submit" class="btn btn-primary">SAVE SETTINGS</button>
</form>