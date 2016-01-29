<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Class To Send Tracking Information Back To My Website
 *
 * @since 1.3.0
 */
class WPHC_Tracking {

  /**
	 * Date To Send Home
	 *
	 * @var array
	 * @since 1.3.0
	 */
  private $data;

  /**
	  * Main Construct Function
	  *
	  * Call functions within class
	  *
	  * @since 1.3.0
	  * @uses WPHC_Tracking::add_hooks() Adds actions to hooks and filters
	  * @return void
	  */
  function __construct() {
    $this->add_hooks();
    $this->track_check();
  }

  /**
	  * Add Hooks
	  *
	  * Adds functions to relavent hooks and filters
	  *
	  * @since 1.3.0
	  * @return void
	  */
  private function add_hooks() {
    add_action( 'admin_notices', array( $this, 'admin_notice' ) );
    add_action( 'admin_init', array( $this, 'admin_notice_check' ) );
  }

  /**
   * Determines If Ready To Send Data Home
   *
   * Determines if the plugin has been authorized to send the data home in the settings page. Then checks if it has been at least a week since the last send.
   *
   * @since 1.3.0
   * @uses WPHC_Tracking::load_data()
   * @uses WPHC_Tracking::send_data()
   * @return void
   */
  private function track_check() {
    $settings = (array) get_option( 'wphc-settings' );
    $tracking_allowed = '0';
		if ( isset( $settings['tracking_allowed'] ) ) {
			$tracking_allowed = $settings['tracking_allowed'];
		}
    $last_time = get_option( 'wphc_tracker_last_time' );
    if ( '1' == $tracking_allowed && ( ( $last_time && $last_time < strtotime( '-1 week' ) ) || ! $last_time ) ) {
      $this->load_data();
      $this->send_data();
      update_option( 'wphc_tracker_last_time', time() );
    }
  }

  /**
   * Sends The Data Home
   *
   * @since 1.3.0
   * @return void
   */
  private function send_data() {
    $response = wp_remote_post( 'http://mylocalwebstop.com/?usage_track=confirmation', array(
			'method'      => 'POST',
			'timeout'     => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'body'        => $this->data,
			'user-agent'  => 'WPHC Usage Tracker'
		) );
    if ( is_wp_error( $response ) ) {
		   $error_message = $response->get_error_message();
		   echo "Something went wrong with WPHC Usage Tracker: $error_message";
		}
  }

  /**
   * Prepares The Data To Be Sent
   *
   * @since 1.3.0
   * @return void
   */
  private function load_data() {
    global $wpdb;
    $data = array();
    $data["plugin"] = "WPHC";

    $data['url']    = home_url();
    $data["wp_version"] = get_bloginfo( 'version' );
    $data["php_version"] = PHP_VERSION;
    $data["mysql_version"] = $wpdb->db_version();
    $data["server_app"] = $_SERVER['SERVER_SOFTWARE'];

    // Retrieve current plugin information
		if( ! function_exists( 'get_plugins' ) ) {
			include ABSPATH . '/wp-admin/includes/plugin.php';
		}
		$plugins        = array_keys( get_plugins() );
		$active_plugins = get_option( 'active_plugins', array() );
		foreach ( $plugins as $key => $plugin ) {
			if ( in_array( $plugin, $active_plugins ) ) {
				// Remove active plugins from list so we can show active and inactive separately
				unset( $plugins[ $key ] );
			}
		}
		$data['active_plugins']   = $active_plugins;
		$data['inactive_plugins'] = $plugins;

    $theme_data = wp_get_theme();
    $data['theme']  = $theme_data->Name;
    $data['theme_version'] = $theme_data->Version;

    $data['original_version'] = '1.3.0';
    $data['current_version'] = '1.3.0';

    $this->data = $data;
  }

  /**
   * Adds Admin Notice To Dashboard
   *
   * Adds an admin notice asking for authorization to send data home
   *
   * @since 1.3.0
   * @return void
   */
  public function admin_notice() {
    $show_notice = get_option( 'wphc-tracking-notice' );
    $settings = (array) get_option( 'wphc-settings' );

    if ( $show_notice ) {
      return;
    }

    if ( isset( $settings['tracking_allowed'] ) && '1' == $settings['tracking_allowed'] ) {
      return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
      return;
    }

    if( false !== stristr( network_site_url( '/' ), 'dev' ) || false !== stristr( network_site_url( '/' ), 'localhost' ) || false !== stristr( network_site_url( '/' ), ':8888' ) ) {
			update_option( 'wphc-tracking-notice', '1' );
		} else {
      $optin_url  = esc_url( add_query_arg( 'wphc_track_check', 'opt_into_tracking' ) );
  		$optout_url = esc_url( add_query_arg( 'wphc_track_check', 'opt_out_of_tracking' ) );
  		echo '<div class="updated"><p>';
  			echo __( "Allow My WordPress Health Check to anonymously track this plugin's usage and help us make this plugin better? No sensitive data is tracked.", 'my-wp-health-check' );
  			echo '&nbsp;<a href="' . esc_url( $optin_url ) . '" class="button-secondary">' . __( 'Allow', 'my-wp-health-check' ) . '</a>';
  			echo '&nbsp;<a href="' . esc_url( $optout_url ) . '" class="button-secondary">' . __( 'Do not allow', 'my-wp-health-check' ) . '</a>';
  		echo '</p></div>';
    }
  }

  /**
   * Checks If User Has Clicked On Notice
   *
   * @since 1.3.0
   * @return void
   */
  public function admin_notice_check() {
    if ( isset( $_GET["wphc_track_check"] ) ) {
      if ( 'opt_into_tracking' == $_GET["wphc_track_check"] ) {
        $settings = (array) get_option( 'wphc-settings' );
        $settings['tracking_allowed'] = '1';
        update_option( 'wphc-settings', $settings );
      } else {
        $settings = (array) get_option( 'wphc-settings' );
        $settings['tracking_allowed'] = '0';
        update_option( 'wphc-settings', $settings );
      }
      update_option( 'wphc-tracking-notice', '1' );
    }
  }
}
$wphc_tracking = new WPHC_Tracking();

?>