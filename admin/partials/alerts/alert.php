<div class="alert alert-<?php echo esc_attr($args['alert']); ?><?php echo $args['close'] ? ' alert-dismissible fade show' : ''; ?>" role="alert">

<?php if($args['msg-header'] != '') : ?>
<h4 class="alert-heading ttt-show-alert-error-code"><?php echo esc_html($args['msg-header']); ?></h4>
<?php endif; ?>
  <?php echo esc_html($args['msg']); ?>
  <div class="msg-content">
    <?php echo wp_kses_post($args['msg-content']); ?>
  </div>

  <?php if($args['close']) : ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  <?php endif; ?>
</div>
