<?php
/*
Plugin Name: WP Reset
Plugin URI: https://github.com/chrisberthe/wp-reset
Description: A plugin that allows you to reset the database to WordPress's initial state.
Version: 1.0
Author: Chris Berthe ☻
Author URI: https://github.com/chrisberthe
License: GNU General Public License
*/

if ( ! class_exists('WP_Reset') && is_admin() ) :

	class WP_Reset 
	{
		/**
		 * Nonce value
		 */
		private $_nonce = 'wp-reset-nonce';
		
		/**
		 * Loads default options
		 *
		 * @return void
		 */
		function __construct() 
		{
			add_action('init', array($this, 'init_language'));
			add_action('admin_init', array($this, 'wp_reset_init'));
			add_action('admin_footer', array($this, 'add_admin_javascript'));
			add_action('admin_menu', array($this, 'add_admin_menu'));
			add_filter('contextual_help', array($this, 'add_contextual_help'), 10, 2);
		}
		
		/**
		 * Handles the admin page functionality
		 *
		 * @uses wp_install Located in includes/upgrade.php (line 22)
		 */
		function wp_reset_init()
		{
			global $wpdb, $current_user, $pagenow;
			
			if ( isset($_POST['wp-random-value'], $_POST['wp-reset-input']) && $_POST['wp-random-value'] == $_POST['wp-reset-input'] 
				&& check_admin_referer('wp-nonce-submit', $this->_nonce) )
			{
				require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
				
				$blog_title = get_option('blogname');
				$public = get_option('blog_public');
				
				$admin_user = get_userdatabylogin('admin');				
				$user = ( ! $admin_user || $admin_user->wp_user_level < 10 ) ? $current_user : $admin_user;
				
				// Run through the database columns and drop all the tables
				$db_tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}%'");
				
				foreach ( $db_tables as $db_table )
				{
					$wpdb->query("DROP TABLE {$db_table}");
				}
				
				// Return user keys and import variables
				$keys = wp_install($blog_title, $user->user_login, $user->user_email, $public);
				extract($keys, EXTR_SKIP);
				
				// Recreate the user for this installation
				$query = $wpdb->prepare("UPDATE $wpdb->users SET user_pass = '%s', user_activation_key = '' WHERE ID = '%d'", $user->user_pass, $user_id);
				$wpdb->query($query);
				
				// Update the user with their old password
				if ( get_user_meta($user_id, 'default_password_nag') ) update_user_meta($user_id, 'default_password_nag', '');
				
				update_option('active_plugins', array(plugin_basename(__FILE__)));
				
				wp_clear_auth_cookie();
				wp_set_auth_cookie($user_id);
				
				wp_redirect(admin_url($pagenow) . '?page=wp-reset&reset=success'); exit();
			}
		}
		
		/**
		 * Displays the admin page
		 *
		 * @return void
		 */
		function show_admin_page()
		{
			global $current_user;
			
			// Return to see if admin object exists
			$admin_user = get_userdatabylogin('admin');
			
			// Generate a random value for the input box
			$random_string = $this->_rand_string();
?>
			<?php if ( isset($_POST['wp-random-value'], $_POST['wp-reset-input']) && $_POST['wp-random-value'] != $_POST['wp-reset-input'] ) : ?>
				<div class="error"><p><strong><?php _e('You entered the wrong value - please try again', 'wp-reset') ?>.</strong></p></div>
			<?php elseif ( isset($_GET['reset']) && $_GET['reset'] == 'success' ) : ?>
				<div class="updated"><p><strong><?php _e('The WordPress database has been reset successfully', 'wp-reset') ?>.</strong></p></div>
			<?php endif ?>

			<div class="wrap">
				<?php screen_icon() ?>
				<h2><?php _e('Database Reset', 'wp-reset') ?></h2>
				<p>Please type in (or copy/paste) the generated value into the text box:&nbsp;&nbsp;<strong><?php echo $random_string ?></strong></p>
				<form action="" method="POST" id="wp-reset-form">
					<?php wp_nonce_field('wp-nonce-submit', $this->_nonce) ?>
					<input type="hidden" name="wp-random-value" value="<?php echo $random_string ?>" id="wp-random-value" />
					<input type="text" name="wp-reset-input" value="" id="wp-reset-input" />
					<input type="submit" name="wp-reset-submit" value="<?php _e('Reset Database', 'wp-reset') ?>" id="wp-reset-submit" class="button-primary" />
				</form>
				
				<?php if ( ! $admin_user || $admin_user->wp_user_level < 10 ) : ?>
					<p style="margin-top: 25px"><?php printf(__('The default user <strong><u>admin</u></strong> was never created for this WordPress install. So <strong><u>%s</u></strong> will be recreated with its current password instead', 'wp-reset'), $current_user->user_login) ?>.</p>
				<?php else : ?>
					<p><?php _e('The default user <strong><u>admin</u></strong> will be recreated with its current password upon resetting', 'wp-reset') ?>.</p>
				<?php endif; ?>
				
				<p><?php _e('Note that once you reset the database, all users will be deleted except the initial admin user. The plugin will also reactivate itself after resetting', 'wp-reset') ?>.</p>
			</div>
<?php	}
		
		/**
		 * Add JavaScript to the bottom of the plugin page
		 *
		 * @return bool TRUE on reset confirmation
		 */
		function add_admin_javascript()
		{
?>
			<script type="text/javascript">
			/* <![CDATA[ */
				jQuery('#wp-reset-submit').click(function() {
					var message = "<?php _e('Clicking OK will result in your database being reset to its initial settings. Continue?', 'wp-reset') ?>";
					var reset = confirm(message);
					
					if ( reset ) {
						jQuery('#wp-reset-form').submit();
					} else {
						return false;
					}
				});
			/* ]]> */
			</script>
<?php			
		}
		
		/**
		 * Adds our submenu item to the Tools menu
		 *
		 * @return void
		 */
		function add_admin_menu()
		{
			global $current_user;
			
			if ( current_user_can('update_core') && $current_user->wp_user_level == 10)
			{
				$this->_hook = add_submenu_page('tools.php', 'Database Reset', 'Database Reset', 'update_core', 'wp-reset', array($this, 'show_admin_page'));
			}
		}
		
		/**
		 * Adds the contextual help for our plugin page
		 *
		 * @param $contextual_help Hook text to display
		 * @param $screen_id ID of the current admin screen
		 * @return $contextual_help String The help text
		 */
		function add_contextual_help($contextual_help, $screen_id)
		{			
			if ($screen_id == $this->_hook)
			{
				$contextual_help = '<p>' . __('Have any cool ideas for this plugin? Contact me either by <a href="http://twitter.com/#!/chrisberthe">Twitter</a> or by <a href="https://github.com/chrisberthe">GitHub</a>.', 'wp-reset') . '</p>';
				$contextual_help .= '<p>' . __('If this plugin becomes non-functional in any way due to WordPress upgrades, rest assured I will update it.', 'wp-reset') . '</p>';
				$contextual_help .= '<p>' . __('Goodbye for now.', 'wp-reset') . '</p>';
			}
			
			return $contextual_help;
		}
		
		/**
		 * Load language path
		 *
		 * @return void
		 */
		function init_language()
		{
			$language_dir = basename(dirname(__FILE__)) . '/languages';
			load_plugin_textdomain('wp-reset', FALSE, $language_dir);
		}
		
		/**
		 * Generates a random value for our input box
		 *
		 * @access private
		 * @param $length Length of the random string value
		 * @return $random_string
		 */
		function _rand_string($length = 5)
		{
			$random_string = '';
			$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
			$size = strlen($chars);
			
			for ($i = 0; $i < $length; $i++)
			{
				$random_string .= $chars[rand(0, $size-1)];
			}
			
			return $random_string;
		}
		
	}

	$wp_reset = new WP_Reset();

endif;