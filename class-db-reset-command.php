<?php

/**
 * Reset the database tables.
 */
class DB_Reset_Command extends WP_CLI_Command {

  private $reporting;

  public function __construct() {
    $this->resetter = new DB_Resetter();
  }

  /**
   * Reset the database tables.
   *
   * ## OPTIONS
   *
   * <tables>
   * The list of tables to reset
   *
   * <reactivate>
   * Reactivate current theme and plugins after reset?
   *
   * ## EXAMPLES
   * wp reset database
   * wp reset database --tables='users,posts,comments'
   * wp reset database --no-reactivate
   */
  public function database( $args, $assoc_args ) {
    $this->handle_before_reset();
    // $this->reactivate_data( $assoc_args[ 'reactivate' ] );
    $this->reset( $this->sanitize_input( $assoc_args[ 'tables' ] ) );
    $this->handle_after_reset();
  }

  /**
   * Lists the database tables
   *
   * @subcommand list
   */
  public function _list() {
    $tables = $this->resetter->get_wp_tables();

    foreach( $tables as $key => $value ) {
      WP_CLI::line( $key );
    }
  }

  private function handle_before_reset() {
    $this->disable_error_reporting();
  }

  private function disable_error_reporting() {
    $this->reporting = error_reporting();
    error_reporting( 0 );
  }

  private function sanitize_input( $string = '' ) {
    if ( empty ( $string ) ) {
      return $this->get_wp_tables();
    }

    $sanitized = array();
    $array = array_flip( explode( ',', preg_replace( '/\s+/', '', $string ) ) );

    array_walk( $array , array( $this, 'update_array_values' ), &$sanitized );

    return $sanitized;
  }

  private function update_array_values( $key, $value, $array) {
    global $wpdb;

    $array[ $value ] = $wpdb->prefix . $value;
  }

  private function get_wp_tables() {
    return $this->resetter->get_wp_tables();
  }

  private function reactivate_data( $string = '' ) {
    if ( is_null( $string ) ) {
      $string = 'true';
    }

    $boolean = ( $string ) ? 'true' : 'false';

    $this->resetter->set_reactivate( $boolean );
  }

  private function reset( array $tables ) {
    foreach ( $tables as $key => $value ) {
      $key = array( $key );
      WP_CLI::success( sprintf( __( '%s', 'wp-reset' ), reset( $key ) ) );
    }

    $this->resetter->reset( $tables );
    WP_CLI::line( __( 'The selected tables were reset', 'wp-reset' ) );
  }

  private function handle_after_reset() {
    error_reporting( $this->reporting );
  }
}

WP_CLI::add_command( 'reset', 'DB_Reset_Command' );
