<div class="wrap">
    <div class="bootstrap-iso">
        <h1>Dashboard</h1>
        <div class="form form-dashboard-user col-md-8">
            <?php 
                if($retUpdate) {
                    \TheTechTribeClient\ShowAlert::get_instance()->show($alertArgs); 
                }
            ?>
                        
            <ul class="nav nav-tabs" id="tttUserDashboard" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="home-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab" aria-controls="home" aria-selected="true">Settings</button>
                </li>
                <?php if(tttIsKeyActive()) : ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="import-tab" data-bs-toggle="tab" data-bs-target="#import" type="button" role="tab" aria-controls="contact" aria-selected="false">Import</button>
                </li>
                <?php endif; ?>
            </ul>

            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fadex show active" id="settings" role="tabpanel" aria-labelledby="settings-tab">
                    <div class="wrap">
                        <form action="<?php echo admin_url('admin.php?page=the-tribal-plugin#settings');?>" method="post" class="dashboard-form">
                            <div class="mb-3">
                                <label class="form-label">API Key</label>
                                <input type="input" class="form-control" name="ttt_api_key" value="<?php echo $apiKey;?>">
                                <div id="apiHelp" class="form-text">
                                    <?php if(!tttIsKeyActive()) : ?>
                                        <p>STATUS: <span style="color:red;font-weight:bold;">Inactive</span> (Please grab your API key <a href="https://portal.thetechtribe.com/my-tribe-membership" target="_blank">from here</a>)</p>
                                    <?php else: ?>
                                        <p>STATUS: <span style="color:green;font-weight:bold;">Active</span> (You're good to go!)</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <hr>
                            <div class="mb-3">
                                <label class="form-label">Auto Or Manual publish of posts?</label>
                                <select class="form-select" aria-label="Default select example" name="ttt_publish_post">
                                    <option value="manual" <?php echo ($publishPosts=='manual') ? 'selected':'';?>>Manual</option>
                                    <option value="auto" <?php echo ($publishPosts=='auto') ? 'selected':'';?>>Auto</option>
                                </select>
                                <div id="apiHelp" class="form-text">
                                    <p>
                                        AUTO = all Posts will be automatically set to go LIVE on their Schedule dates. No user-interaction required. <br>
                                        MANUAL= all Posts will be marked as DRAFTS ready for you to tweak before marking LIVE. User-interaction required.
                                    </p>
                                </div>
                            </div>
                            <hr>
                            <div class="mb-3">
                                <label class="form-label">Select Default Author</label>
                                <select class="form-select" aria-label="Default select author" name="ttt_post_author">
                                    <?php foreach($users as $user) : ?>
                                        <option value="<?php echo $user->ID;?>" <?php echo ($defaultAuthor == $user->ID) ? 'selected':'';?>>
                                            <?php echo $user->display_name;?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="apiHelp" class="form-text">
                                    <p>Choose the default Author you want all the automatically imported posts to be assigned against.</p>
                                </div>
                            </div>
                            <hr>
                            <input type="hidden" name="action" value="ttt_update_dashboard_user">
                            <?php wp_nonce_field( 'ttt_client_update_plugin_' . get_current_user_id() ); ?>
                            <button type="submit" class="btn btn-primary">SAVE SETTINGS</button>
                        </form>
                    </div>
                </div>
                <?php if(tttIsKeyActive()) : ?>
                <div class="tab-pane fadex" id="import" role="tabpanel" aria-labelledby="import-tab">
                    
                    <div class="wrap">
                        <form action="<?php echo admin_url('admin.php?page=the-tribal-plugin#import');?>" method="post" class="dashboard-form-import">
                            
                                <div class="mb-3">
                                    <p>Manual Import</p>
                                    <p>Last Check : <?php echo $lastChecked;?></p>
                                    <p>Last Download : <?php echo $lastDownload;?></p>
                                </div>
                            
                            <input type="hidden" name="action" value="ttt_force_import">
                            <?php wp_nonce_field( 'ttt_client_update_plugin_' . get_current_user_id() ); ?>
                            <button type="submit" class="btn btn-primary">START MANUAL IMPORT</button>
                        </form> 
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>