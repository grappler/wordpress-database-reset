<div class="wrap card">
  <h2><?php _e( 'Database Reset', 'wp-reset' ) ?></h2>

  <?php include( 'partials/notice.php' ) ?>

  <form method="post" id="db-reset-form">
    <?php wp_nonce_field( $this->nonce ) ?>
    <?php include( 'partials/select-tables.php' ) ?>
    <?php include( 'partials/security-code.php' ) ?>
    <?php include( 'partials/submit-button.php' ) ?>
  </form>
</div>
