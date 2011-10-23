<?php
/*
Plugin Name: WordPress Database Reset
Plugin URI: https://github.com/chrisberthe/wordpress-database-reset
Description: A plugin that allows you to reset the database to WordPress's initial state.
Version: 1.4
Author: Chris Berthe ☻
Author URI: https://github.com/chrisberthe
License: GNU General Public License
*/

if ( ! class_exists('cb_wp_reset') && is_admin() ) :

	class cb_wp_reset
	{
		/**
		 * Nonce value
		 */
		private $_nonce = 'wp-reset-nonce';
		
		/**
		 * Plugins to reactivate
		 */
		private $_active_plugins;
		
		/**
		 * Tables to preserve
		 */
		private $_tables;
		
		/**
		 * WordPress database tables
		 */
		private $_wp_tables;
		
		/**
		 * Loads default options
		 *
		 * @return void
		 */
		function __construct() 
		{
			add_action('init', array($this, 'init_language'));
			add_action('admin_init', array($this, 'wp_reset_init'));
			add_action('admin_init', array($this, '_redirect_user'));
			add_action('admin_footer', array($this, 'add_admin_javascript'));
			add_action('admin_menu', array($this, 'add_admin_menu'));
			add_filter('contextual_help', array($this, 'add_contextual_help'), 10, 2);
			add_filter('wp_mail', array($this, '_fix_mail'));
		}
		
		/**
		 * Handles the admin page functionality
		 *
		 * @access public
		 * @uses wp_install Located in includes/upgrade.php (line 22)
		 */
		function wp_reset_init()
		{
			global $wpdb, $current_user, $pagenow;
			
			// Grab the WordPress database tables
			$this->_wp_tables = $wpdb->tables();
			
			// Check for valid input - goes ahead and drops / resets tables
			if ( isset($_POST['wp-random-value'], $_POST['wp-reset-input']) && $_POST['wp-random-value'] == $_POST['wp-reset-input'] 
				&& check_admin_referer('wp-nonce-submit', $this->_nonce) )
			{				
				require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
				
				// No tables were selected
				if ( ! isset($_POST['tables']) && empty($_POST['tables']) )
				{
					wp_redirect(admin_url($pagenow) . '?page=wp-reset&reset=no-select'); exit();
				}
				
				// Get current options
				$blog_title = get_option('blogname');
				$public = get_option('blog_public');

				$admin_user = get_userdatabylogin('admin');
				$user = ( ! $admin_user || ! user_can($admin_user->ID, 'update_core') ) ? $current_user : $admin_user;
				
				// Get the selected tables
				$tables = (isset($_POST['tables'])) ? array_flip($_POST['tables']) : array();

				// Compare the selected tables against the ones in the database
				$this->_tables = array_diff_key($this->_wp_tables, $tables);
				
				// Preserve the data from the tables that are unique
				if ( count($this->_tables) > 0 )
				{
					$backup_tables = $this->_backup_tables($this->_tables);
				}
				
				// Grab the currently active plugins
				if ( isset($_POST['wp-reset-check']) && $_POST['wp-reset-check'] == 'true' )
				{
					$this->_active_plugins = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM $wpdb->options WHERE option_name = %s", 'active_plugins'));
				}
				
				// Run through the database columns and drop all the tables
				if ( $db_tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}%'") )
				{
					foreach ($db_tables as $db_table)
					{
						$wpdb->query("DROP TABLE {$db_table}");
					}

					// Return user keys and import variables
					$keys = wp_install($blog_title, $user->user_login, $user->user_email, $public);
					$this->_wp_update_user($user, $keys);
					
					// Reactivate the plugins after reinstalling					
					$this->_reactivate_plugins();
					
					// Delete and replace tables with the backed up table data
					if ( count($this->_tables) > 0 )
					{
						foreach ($this->_tables as $table)
						{
							$wpdb->query("DELETE FROM " . $table);
						}
						
						$this->_backup_tables($backup_tables, 'reset');
					}
					
					wp_redirect(admin_url($pagenow) . '?page=wp-reset&reset=success'); exit();
				}
				
				// If the wp-reset-check isn't checked just redirect user to dashboard
				wp_redirect(admin_url()); exit();
			}
		}
		
		/**
		 * Displays the admin page
		 *
		 * @access public
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
			<?php elseif ( isset($_GET['reset']) && $_GET['reset'] == 'no-select') : ?>
				<div class="error"><p><strong><?php _e('You did not select any database tables', 'wp-reset') ?>.</strong></p></div>
			<?php elseif ( isset($_GET['reset']) && $_GET['reset'] == 'success' ) : ?>
				<div class="updated"><p><strong><?php _e('The WordPress database has been reset successfully', 'wp-reset') ?>.</strong></p></div>
			<?php endif ?>

			<div class="wrap">
				<?php screen_icon() ?>
				<h2><?php _e('Database Reset', 'wp-reset') ?></h2>
				<form action="" method="POST" id="wp-reset-form">
					<p><?php _e('Please choose from the following database tables the ones you would like to reset', 'wp-reset') ?>:</p>
					<div id="select-buttons">
						<span><a href='#' id="select-all"><?php _e('Select All', 'wp-reset') ?></a></span>
						<select id="wp-tables" multiple="multiple" name="tables[]">
							<?php foreach ($this->_wp_tables as $key => $value) : ?>
								<option><?php echo $key ?></option>
							<?php endforeach ?>
						</select>
					</div>
					<p><?php _e('Type in (or copy/paste) the generated value into the text box', 'wp-reset') ?>:&nbsp;&nbsp;<strong><?php echo $random_string ?></strong></p>
					<?php wp_nonce_field('wp-nonce-submit', $this->_nonce) ?>
					<input type="hidden" name="wp-random-value" value="<?php echo $random_string ?>" id="wp-random-value" />
					<input type="text" name="wp-reset-input" value="" id="wp-reset-input" />
					<input type="submit" name="wp-reset-submit" value="<?php _e('Reset Database', 'wp-reset') ?>" id="wp-reset-submit" class="button-primary" />
					<p>
						<label for="wp-reset-check">
							<input type="checkbox" name="wp-reset-check" id="wp-reset-check" checked="checked" value="true" />
						<?php _e('Reactivate current plugins after reset?', 'wp-reset') ?>
						</label>
					</p>
				</form>
				
				<?php if ( ! $admin_user || ! user_can($admin_user->ID, 'update_core') ) : ?>
					<p style="margin-top: 25px"><?php printf(__('The default user <strong><u>admin</u></strong> was never created for this WordPress install. So <strong><u>%s</u></strong> will be recreated with its current password instead', 'wp-reset'), $current_user->user_login) ?>.</p>
				<?php else : ?>
					<p><?php _e('The default user <strong><u>admin</u></strong> will be recreated with its current password upon resetting', 'wp-reset') ?>.</p>
				<?php endif; ?>
				
				<p><?php _e('Note that once you reset the database, all users will be deleted except the initial admin user.', 'wp-reset') ?></p>
			</div>
<?php	}
		
		/**
		 * Add JavaScript to the bottom of the plugin page
		 *
		 * @access public
		 * @return bool TRUE on reset confirmation
		 */
		function add_admin_javascript()
		{
?>
			<script type="text/javascript">
			/* <![CDATA[ */				
				jQuery(function($) {
					$('#wp-tables').bsmSelect({
						animate: true,
						title: "<?php _e('Select Table', 'wp-reset') ?>",
						plugins: [$.bsmSelect.plugins.compatibility()]
					});
					
					$("#select-all").click(function() {
						$("#wp-tables").children().attr("selected", "selected").end().change();
						return false;
					});
					
					$('#wp-reset-submit').click(function() {
						var message = "<?php _e('Clicking OK will result in your database being reset to its initial settings. Continue?', 'wp-reset') ?>";
						var reset = confirm(message);

						if (reset) {
							$('#wp-reset-form').submit();
						} else {
							return false;
						}
					});
				});
				
			/* ]]> */
			</script>
<?php			
		}
		
		/**
		 * Adds our submenu item to the Tools menu
		 *
		 * @access public
		 * @return void
		 */
		function add_admin_menu()
		{			
			if ( current_user_can('update_core') )
			{
				$this->_hook = add_submenu_page('tools.php', 'Database Reset', 'Database Reset', 'update_core', 'wp-reset', array($this, 'show_admin_page'));
			}
			
			add_action('admin_print_styles-' . $this->_hook, array($this, 'add_plugin_styles_and_scripts'));
		}
		
		/**
		 * Adds the contextual help for our plugin page
		 *
		 * @access public
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
		 * Adds any plugin styles to our page
		 *
		 * @access public
		 * @return void
		 */
		function add_plugin_styles_and_scripts()
		{
			wp_enqueue_style('wordpress-reset-css', plugins_url('css/wp-reset.css', __FILE__));
			wp_enqueue_style('bsmselect-css', plugins_url('css/jquery.bsmselect.css', __FILE__));
			
			wp_enqueue_script('bsmselect', plugins_url('js/jquery.bsmselect.js', __FILE__));
			wp_enqueue_script('bsmselect-compatibility', plugins_url('js/jquery.bsmselect.compatibility.js', __FILE__));
			wp_enqueue_script('bsmselect-sortable', plugins_url('js/jquery.bsmselect.sortable.js', __FILE__));
		}
		
		/**
		 * Load language path
		 *
		 * @access public
		 * @return void
		 */
		function init_language()
		{
			$language_dir = basename(dirname(__FILE__)) . '/languages';
			load_plugin_textdomain('wp-reset', FALSE, $language_dir);
		}
		
		/**
		 * For activation hook
		 *
		 * @access public
		 * @return void
		 */
		function plugin_activate()
		{
			add_option('wp-reset-activated', true);
		}
		
		/**
		 * Redirects the user after the plugin is activated
		 *
		 * @access private
		 * @return void
		 */
		function _redirect_user()
		{
			if ( get_option('wp-reset-activated', false) )
			{
				delete_option('wp-reset-activated');
				wp_redirect(admin_url('tools.php') . '?page=wp-reset');
			}
		}
		
		/**
		 * Changes the password to a sentence rather than
		 * an auto-generated password that is sent by email
		 * right after the installation is complete
		 *
		 * @access private
		 * @return $mail Version with password changed
		 */
		function _fix_mail($mail)
		{
			$subject = __('WordPress Database Reset', 'wp-reset');
			$message = __('The WordPress database has been successfully reset to its default settings:', 'wp-reset');
			$password = __('Password: The password you chose during the install.', 'wp-reset');
						
			if ( stristr($mail['message'], 'Your new WordPress site has been successfully set up at:') )
			{
				$mail['subject'] = preg_replace('/New WordPress Site/', $subject, $mail['subject']);
				$mail['message'] = preg_replace('/Your new WordPress site has been successfully set up at:+/', $message, $mail['message']);
				$mail['message'] = preg_replace('/Password:\s.+/', $password, $mail['message']);
			}
			
			return $mail;
		}
		
		/**
		 * Preserves all the results from the tables the user
		 * did not select from the drop-down. Also resets these
		 * results back after reinstalling WordPress.
		 *
		 * @access private
		 * @return array Backed up data if type backup, void if reset
		 */
		function _backup_tables($tables, $type = 'backup')
		{
			global $wpdb;
			
			if ( is_array($tables) )
			{
				switch ($type)
				{
					case 'backup':
					$backup_tables = array();
					
					foreach ($tables as $table)
					{
						$backup_tables[$table] = $wpdb->get_results("SELECT * FROM " . $table);
					}
					
					return $backup_tables;
					break;
					
					case 'reset':
					// Outer array of tables
					foreach ($tables as $table_name => $table_data)
					{
						// Array of table rows
						foreach ($table_data as $row)
						{
							$columns = $values = array();
							
							// Loop through current object row
							foreach ($row as $column => $value)
							{
								$columns[] = $column;
								$values[] = $wpdb->escape($value);
							}
							
							$wpdb->query("INSERT INTO $table_name (" . implode(', ', $columns) . ") VALUES ('" . implode("', '", $values) . "')");
						}
					}
					break;
				}
			}
			
			return;
		}
		
		/**
		 * Reactivates the plugins after reset
		 *
		 * @access private
		 * @return TRUE on plugin reactivation, FALSE otherwise
		 */
		function _reactivate_plugins()
		{
			global $wpdb;
			
			if ( ! empty($this->_active_plugins) )
			{
				// Replace the list of plugins with the 'old' list after the reset
				$query = $wpdb->prepare("UPDATE $wpdb->options SET option_value = %s WHERE option_name = %s", $this->_active_plugins, 'active_plugins');
				
				if ( $wpdb->query($query) )
				{
					return TRUE;
				}
			}
			
			return FALSE;
		}
		
		/**
		 * Updates the user password and clears / sets 
		 * the authentication cookie for the user
		 *
		 * @access private
		 * @param $user Current or admin user
		 * @param $keys Array returned by wp_install()
		 * @return TRUE on install success, FALSE otherwise
		 */
		function _wp_update_user($user, $keys)
		{
			global $wpdb;			
			extract($keys, EXTR_SKIP);

			// Set the old password back to the user
			$query = $wpdb->prepare("UPDATE $wpdb->users SET user_pass = '%s', user_activation_key = '' WHERE ID = '%d'", $user->user_pass, $user_id);
			
			if ( $wpdb->query($query) )
			{
				// Delete the default_password_nag 
				// so it doesn't pop up with the password reminder after installing
				if ( get_user_meta($user_id, 'default_password_nag') ) delete_user_meta($user_id, 'default_password_nag');

				wp_clear_auth_cookie();
				wp_set_auth_cookie($user_id);
				
				return TRUE;
			}
			
			return FALSE;
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

	$cb_wp_reset = new cb_wp_reset();
	
	register_activation_hook( __FILE__, array('cb_wp_reset', 'plugin_activate') );

endif;