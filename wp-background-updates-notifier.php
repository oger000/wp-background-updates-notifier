<?php
/*
Plugin Name: WP Background Updates Notifier
#Plugin URI: https://github.com/oger000/wp-background-updates-notifier
Description: Sends email to notify you if there are any updates for your WordPress sites as background job.
Author: Gerhard Oettl
Version: 0.0.1
Author URI: http://www.ogersoft.at/
#Text Domain: wp-updates-notifier
#Domain Path: /languages
*/

/*  Copyright 2014  Gerhard Oettl  (email : gerhard.oettl@ogersoft.at)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation version 3.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Only load class if it hasn't already been loaded
if ( !class_exists( 'sc_WPBgUpdNotifier' ) ) {

	// WP Background Updates Notifier class defintion.
	class sc_WPBgUpdNotifier {
		// config happens here
		static $OPT_EMAIL_TO            = "gerhard.oettl@ogersoft.at";
		static $OPT_NOTIFY_CORE         = false;
		static $OPT_NOTIFY_PLUGINS      = true;
		static $OPT_NOTIFY_THEMES       = true;
		static $OPT_PLUGINS_ALL         = true;  // false = check only active
		static $OPT_THEMES_ALL          = true;  // false = check only active


		/**
		 * Init and start notifier.
		 *
		 * @return void
		 */
		public static function start() {
			// Internationalization
			//load_plugin_textdomain( 'wp-background-updates-notifier', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
			static::do_update_check();
		}  // eo init



		/**
		 * This is run by the cron. If updates required, then email notification is sent.
		 *
		 * @return void
		 */
		public static function do_update_check() {

			$message = "";
			// check the WP core for updates
			if ( static::$OPT_NOTIFY_CORE ) {
				$core_updated = static::core_update_check( $message );
			}
			// check for plugin updates
			if ( static::$OPT_NOTIFY_PLUGINS ) {
				$plugins_updated = static::plugins_update_check( $message, static::$OPT_PLUGINS_ALL );
			}
			// check for theme updates
			if ( static::$OPT_NOTIFY_THEMES ) {
				$themes_updated = static::themes_update_check( $message, static::$OPT_THEMES_ALL );
			}
			// Send email if anything needs to be updated
			if ( $core_updated || $plugins_updated || $themes_updated ) {
				$message = "There are updates available for your WordPress site:\n{$message}\n";
				#$message .= sprintf( "Please visit %s to update.", admin_url( 'update-core.php' ) );
				$message .= sprintf( "Please visit %s to update.", admin_url() );
				static::send_notification_email( $message );
			}
			else {
				$message = "Everything is up to date for your WordPress site:\n{$message}\n";
				#$message .= sprintf( "Please visit %s to update.", admin_url( 'update-core.php' ) );
				#$message .= sprintf( "Please visit %s to update.", admin_url() );
				static::send_notification_email( $message );
			}

		}  // eo do update check


		/**
		 * Checks to see if any WP core updates
		 *
		 * @param string $message holds message to be sent via notification
		 *
		 * @return bool
		 */
		private static function core_update_check( &$message ) {

			global $wp_version;
			do_action( "wp_version_check" ); // force WP to check its core for updates
			$update_core = get_site_transient( "update_core" ); // get information of updates

			if ( 'upgrade' == $update_core->updates[0]->response ) { // is WP core update available?
				require_once( ABSPATH . WPINC . '/version.php' ); // Including this because some plugins can mess with the real version stored in the DB.
				$new_core_ver = $update_core->updates[0]->current; // The new WP core version
				$old_core_ver = $wp_version; // the old WP core version
				$message .= "\n" . sprintf( "WP-Core: WordPress is out of date. Please update from version %s to %s", $old_core_ver, $new_core_ver ) . "\n";
				return true; // we have updates, so return true
			}

			$message .= "\n" . sprintf( "WP-Core: WordPress is up to date." ) . "\n";
			return false; // no updates return false

		}  // eo check core


		/**
		 * Check to see if any plugin updates.
		 *
		 * @param string $message     holds message to be sent via notification
		 * @param int    $checkAll    should we look for all plugins or just active ones
		 *
		 * @return bool
		 */
		private static function plugins_update_check( &$message, $checkAll ) {

			$message .= "\n\n" . sprintf( "WP-Plugins: Check %s plugins.", ($checkAll ? "all" : "active") ) . "\n";
			global $wp_version;
			$cur_wp_version = preg_replace( '/-.*$/', '', $wp_version );
			do_action( "wp_update_plugins" ); // force WP to check plugins for updates
			$update_plugins= get_site_transient( 'update_plugins' ); // get information of updates

			if ( !empty( $update_plugins->response ) ) { // any plugin updates available?
				$plugins_need_update = $update_plugins->response; // plugins that need updating
				if ( ! $checkAll ) { // are we to check just active plugins?
					$active_plugins      = array_flip( get_option( 'active_plugins' ) ); // find which plugins are active
					$plugins_need_update = array_intersect_key( $plugins_need_update, $active_plugins ); // only keep plugins that are active
				}
				if ( count( $plugins_need_update ) >= 1 ) { // any plugins need updating after all the filtering gone on above?
					require_once( ABSPATH . 'wp-admin/includes/plugin-install.php' ); // Required for plugin API
					require_once( ABSPATH . WPINC . '/version.php' ); // Required for WP core version
					foreach ( $plugins_need_update as $key => $data ) { // loop through the plugins that need updating
						$plugin_info = get_plugin_data( WP_PLUGIN_DIR . "/" . $key ); // get local plugin info
						$info        = plugins_api( 'plugin_information', array( 'slug' => $data->slug ) ); // get repository plugin info
						$message .= "\n" . sprintf( "Plugin: %s is out of date. Please update from version %s to %s", $plugin_info['Name'], $plugin_info['Version'], $data->new_version ) . "\n";
						$message .= "\t" . sprintf( "Details: %s", $data->url ) . "\n";
						$message .= "\t" . sprintf( "Changelog: %s%s", $data->url, "changelog/" ) . "\n";
						if ( isset( $info->tested ) && version_compare( $info->tested, $wp_version, '>=' ) ) {
							$compat = sprintf( __( 'Compatibility with WordPress %1$s: 100%% (according to its author)' ), $cur_wp_version );
						}
						elseif ( isset( $info->compatibility[$wp_version][$data->new_version] ) ) {
							$compat = $info->compatibility[$wp_version][$data->new_version];
							$compat = sprintf( 'Compatibility with WordPress %1$s: %2$d%% (%3$d "works" votes out of %4$d total)' , $wp_version, $compat[0], $compat[2], $compat[1] );
						}
						else {
							$compat = sprintf( __( 'Compatibility with WordPress %1$s: Unknown' ), $wp_version );
						}
						$message .= "\t" . sprintf( __( "Compatibility: %s" ), $compat ) . "\n";
					}
					return true; // we have plugin updates return true
				}
			}

			$message .= "\n" . sprintf( "Checked plugins are up to date." ) . "\n";
			return false; // No plugin updates so return false

		}  // eo check plugins


		/**
		 * Check to see if any theme updates.
		 *
		 * @param string $message     holds message to be sent via notification
		 * @param int    $checkAll    should we look for all themes or just active ones
		 *
		 * @return bool
		 */
		private static function themes_update_check( &$message, $checkAll ) {

			$message .= "\n\n" . sprintf( "WP-Themes: Check %s themes.", ($checkAll ? "all" : "active") ) . "\n";
			do_action( "wp_update_themes" ); // force WP to check for theme updates
			$update_themes = get_site_transient( 'update_themes' ); // get information of updates

			if ( !empty( $update_themes->response ) ) { // any theme updates available?
				$themes_need_update = $update_themes->response; // themes that need updating
				if ( ! $checkAll ) { // are we to check just active themes?
					$active_theme       = array( get_option( 'template' ) => array() ); // find current theme that is active
					$themes_need_update = array_intersect_key( $themes_need_update, $active_theme ); // only keep theme that is active
				}
				if ( count( $themes_need_update ) >= 1 ) { // any themes need updating after all the filtering gone on above?
					foreach ( $themes_need_update as $key => $data ) { // loop through the themes that need updating
						$theme_info = wp_get_theme( $key ); // get theme info
						$message .= "\n" . sprintf( __( "Theme: %s is out of date. Please update from version %s to %s" ), $theme_info['Name'], $theme_info['Version'], $data['new_version'] ) . "\n";
					}
					return true; // we have theme updates return true
				}
			}

			$message .= "\n" . sprintf( "Checked themes are up to date." ) . "\n";
			return false; // No theme updates so return false

		}  // eo check themes



		/**
		 * Sends email notification.
		 *
		 * @param string $message holds message to be sent in body of email
		 *
		 * @return void
		 */
		public function send_notification_email( $message ) {

//var_export($argv);
//echo "\nmail-to: " . static::$OPT_EMAIL_TO . "\n";
//echo "\n{$message}\n";
//exit;

			$subject  = sprintf( __( "WP Updates Notifier: Updates Available @ %s" ), home_url() );
			wp_mail( static::$OPT_EMAIL_TO, $subject, $message ); // send email

		}  // eo send email


		/**
		 * Sends test email.
		 *
		 * @param string $message holds message to be sent in body of email
		 *
		 * @return void
		 */
		public static function send_test_email( $settings_errors ) {

			if ( isset( $settings_errors[0]['type'] ) && $settings_errors[0]['type'] == "updated" ) {
				static::send_notification_email( __( "This is a test message from WP Background Updates Notifier." ) );
			}

		}  // eo send testmail


	}  // eo class
}


//$_SERVER['HTTP_HOST'] = "www.gruene-burgschleinitz-kuehnring.at";
//if ($argv[1]) {
//	$_SERVER['HTTP_HOST'] = $argv[1];
//}
require_once("/usr/share/wordpress/wp-load.php");
//echo ABSPATH . "\n";

//var_export($argv);
sc_WPBgUpdNotifier::start();

?>
