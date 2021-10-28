<div class="alert alert-<?php echo $args['alert']; ?> <?php echo $args['close'] ?? 'alert-dismissible fade show';?>" role="alert">

<h4 class="alert-heading ttt-show-alert-error-code"><?php echo ($args['msg-header'] != '') ? $args['msg-header'] : $args['code']; ?></h4>
  <?php echo $args['msg']; ?>
  <div class="msg-content">
    <?php echo $args['msg-content']; ?>
  </div>
  
  <?php if($args['close']) : ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  <?php endif; ?>
</div>