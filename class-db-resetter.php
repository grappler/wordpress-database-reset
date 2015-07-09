<?php

if ( ! class_exists( 'DB_Resetter' ) ) :

  class DB_Resetter {

    private $backup;
    private $blog_data;
    private $theme_data;
    private $preserved;
    private $selected;
    private $user;
    private $wp_tables;

    public function __construct() {
      $this->backup = array();

      $this->set_wp_tables();
      $this->set_user();
    }

    public function reset( $tables = array(), $with_theme_data = true ) {
      $this->validate_selected( $tables );
      $this->set_backup( $with_theme_data );
      $this->reinstall();
      $this->restore_backup();
    }

    private function validate_selected( $tables = array() ) {
      if ( ! empty( $tables ) && is_array( $tables ) ) {
        $this->selected = array_flip( $tables );
        return;
      }

      throw new Exception( __( 'You did not select any database tables', 'wp-reset' ) );
    }

    private function set_backup( $with_theme_data = true ) {
      $this->set_tables_to_preserve( $this->selected );
      $this->back_up_tables( $this->preserved );
      $this->set_blog_data();

      if ( $with_theme_data ) {
        $this->set_theme_data();
      }
    }

    private function set_tables_to_preserve( $tables = array() ) {
      $this->preserved = array_diff_key( $this->wp_tables, $tables );
    }

    private function back_up_tables( $tables = array() ) {
      global $wpdb;

      foreach ( $tables as $table ) {
        $this->backup[ $table ] = $wpdb->get_results( "SELECT * FROM {$table}" );
      }

      return $this->backup;
    }

    private function set_blog_data() {
      $this->blog_data = array(
        'title' => get_option( 'blogname' ),
        'public' => get_option( 'blog_public' )
      );
    }

    private function set_theme_data() {
      $this->theme_data = array(
        'active-plugins' => get_option( 'active_plugins' ),
        'current-theme' => get_option( 'current_theme' ),
        'stylesheet' => get_option( 'stylesheet' ),
        'template' => get_option( 'template' )
      );
    }

    private function reinstall() {
      $this->drop_wp_tables();
      $keys = $this->install_wp();
      $this->update_user_settings( $this->user, $keys );
    }

    private function drop_wp_tables() {
      global $wpdb;

      foreach ( $this->wp_tables as $wp_table ) {
        $wpdb->query( "DROP TABLE {$wp_table}" );
      }
    }

    private function install_wp() {
      return db_reset_install(
        $this->blog_data[ 'title' ],
        $this->user->user_login,
        $this->user->user_email,
        $this->blog_data[ 'public' ]
      );
    }

    private function update_user_settings( $user, $keys ) {
      global $wpdb;
      extract( $keys, EXTR_SKIP );

      $query = $wpdb->prepare( "UPDATE $wpdb->users
                                SET user_pass = '%s', user_activation_key = ''
                                WHERE ID = '%d'",
                                $user->user_pass, $user_id );

      $wpdb->query( $query );
      $this->remove_password_nag( $user_id );
    }

    private function remove_password_nag( $user_id ) {
      if ( get_user_meta( $user_id, 'default_password_nag' ) ) {
        delete_user_meta( $user_id, 'default_password_nag' );
      }
    }

    private function restore_backup() {
      $this->delete_backup_table_rows( $this->preserved );
      $this->restore_backup_tables( $this->backup );
      $this->remove_user_session_tokens();
      $this->reset_user_auth_cookie();
      $this->assert_theme_data_needs_reset();
    }

    private function delete_backup_table_rows( $tables = array() ) {
      global $wpdb;

      foreach ( $tables as $table ) {
        $wpdb->query( "DELETE FROM {$table}" );
      }
    }

    private function restore_backup_tables( $tables = array() ) {
      global $wpdb;

      foreach ( $tables as $table => $data ) {
        foreach ( $data as $row ) {
          $columns = $values = array();

          foreach ( $row as $column => $value ) {
            $columns[] = $column;
            $values[] = esc_sql( $value );
          }

          $wpdb->query( "INSERT INTO $table (" . implode( ', ', $columns ) . ") VALUES ('" . implode( "', '", $values ) . "')" );
        }
      }
    }

    private function remove_user_session_tokens() {
      if ( get_user_meta( $this->user->ID, 'session_tokens' ) ) {
        delete_user_meta( $this->user->ID, 'session_tokens' );
      }
    }

    private function reset_user_auth_cookie() {
      wp_clear_auth_cookie();
      wp_set_auth_cookie( $this->user->ID );
    }

    private function assert_theme_data_needs_reset() {
      if ( ! empty( $this->theme_data ) ) {
        $this->restore_theme_data();
      }
    }

    private function restore_theme_data() {
      update_option( 'active_plugins', $this->theme_data['active-plugins'] );
      update_option( 'template', $this->theme_data['template'] );
      update_option( 'stylesheet', $this->theme_data['stylesheet'] );

      if ( ! empty( $this->theme_data['current-theme'] ) ) {
        update_option( 'current_theme', $this->theme_data['current-theme'] );
      }
    }

    private function set_wp_tables() {
      global $wpdb;
      $this->wp_tables = $wpdb->tables();
    }

    public function get_wp_tables() {
      return $this->wp_tables;
    }

    private function set_user() {
      global $current_user;

      $this->user = ( ! empty( $current_user ) ) ?
                    wp_get_current_user() :
                    get_user_data( 1 );
    }

    public function get_user() {
      return $this->user;
    }

  }

endif;
