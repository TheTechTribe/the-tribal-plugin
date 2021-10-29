<div class="wrap">
    <div class="bootstrap-iso">
        <h1>The Tribal Plugin</h1>
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
                    <button class="nav-link" id="import-tab" data-bs-toggle="tab" data-bs-target="#import" type="button" role="tab" aria-controls="contact" aria-selected="false">Import Status</button>
                </li>
                <?php endif; ?>
            </ul>

            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fadex show active" id="settings" role="tabpanel" aria-labelledby="settings-tab">
                    <div class="wrap">
                        <form action="<?php echo admin_url('admin.php?page=the-tribal-plugin#settings');?>" method="post" class="dashboard-form">
                            <div class="mb-3">
                                <label class="form-label">API Key</label>
                                <input type="password" class="form-control" name="ttt_api_key" value="<?php echo $apiKey;?>">
                                <div id="apiHelp" class="ttt-form-text">
                                    <div class="container-ttt-content">
                                        <div class="row">
                                            <div class="col-md-1">STATUS: </div>
                                            <div class="col-md-11">
                                                <?php if(!tttIsKeyActive()) : ?>
                                                    <span style="color:red;font-weight:bold;">Inactive</span> (Please grab your API key <a href="https://portal.thetechtribe.com/my-tribe-membership" target="_blank">from here</a>)
                                                <?php else: ?>
                                                    <span style="color:green;font-weight:bold;">Active</span> (You're good to go!)
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="mb-3">
                                <label class="form-label">Auto Or Manual publish of posts?</label>
                                <select class="form-select" aria-label="Default select example" name="ttt_publish_post">
                                    <option value="manual" <?php echo ($publishPosts=='manual') ? 'selected':'';?>>Manual</option>
                                    <option value="auto" <?php echo ($publishPosts=='auto') ? 'selected':'';?>>Auto</option>
                                </select>
                                <div id="apiHelp" class="ttt-form-text">
                                    <div class="container-ttt-content">
                                        <div class="row">
                                            <div class="col-md-1">AUTO: </div>
                                            <div class="col-md-11">
                                                All Posts will be automatically set to go LIVE on their Schedule dates. No user-interaction required
                                            </div>
                                        </div>
                                         <div class="row">
                                            <div class="col-md-1">MANUAL: </div>
                                            <div class="col-md-11">
                                                All Posts will be marked as DRAFTS ready for you to tweak before marking LIVE. User-interaction required.
                                            </div>
                                        </div>
                                    </div>
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
                                <div id="apiHelp" class="ttt-form-text">
                                    <div class="container-ttt-content">
                                        Choose the default Author you want all the automatically imported posts to be assigned against.
                                    </div>
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

                            <p>Your server will automatically check for new Blog Posts approximately every 24 hours. If you want to run a Manual Import, simply smash the <b>START MANUAL IMPORT</b> button below.</p>
                            <?php
								//date_default_timezone_set(get_option('timezone_string'));
                            	//echo date_i18n('F d, Y h:i A', 1635495073).'<br>';
                                $nextSchedule = tttGetNextCronTime('ttt_user_cron_exec');
                            ?>
                            <div id="apiHelp" class="ttt-form-text">
                                <div class="container-ttt-content">
                                    <div class="row">
                                        <div class="col-md-3">Last Check: </div>
                                        <div class="col-md-8"><?php echo ($lastChecked && !empty($lastChecked)) ? date('d F Y h:i A', strtotime($lastChecked)) : '';?> </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">Next Schedule Check: </div>
                                        <div class="col-md-8"><?php echo ($nextSchedule) ? date('d F Y h:i A', $nextSchedule) : '';?> </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">Last Successfull Import: </div>
                                        <div class="col-md-8"><?php echo ($lastDownload && !empty($lastDownload)) ? date('d F Y h:i A', strtotime($lastDownload)) : '';?> </div>
                                    </div>
                                </div>
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