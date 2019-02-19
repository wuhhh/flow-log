<?php
/*
Plugin Name: Flow: Log system events
Description: Log system events with message and optional post ID reference
Version: 0.1
Author: Huw Roberts
Author URI: http://www.rootsy.co.uk
Copyright: Huw Roberts
Text Domain: flow-log
*/

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class FlowLog {

  // Log message severity -- Warning conditions
  const FLOWLOG_WARNING = 4;

  // Log message severity -- Normal but significant conditions.
  const FLOWLOG_NOTICE = 5;

  // Log message severity -- Informational messages.
  const FLOWLOG_INFO = 6;

  // Log message severity -- Debug-level messages.
  const FLOWLOG_DEBUG = 7;


  /**
	 * Version of database tables
	 * @var int
	 */
	private $db_version = 1;


	/**
	 * Instance of WPDB Class
	 * @var object
	 */
	protected $wpdb;


  /**
	 * Instance of WP User
	 * @var object
	 */
	protected $user;


  /**
	 * Instance of WP
	 * @var object
	 */
  protected $wp;
  

  /**
   * Returns the instance.
   *
   * @access public
   * @return object
   */
  public static function get_instance()
  {
      
      static $instance = null;

      if (is_null($instance)) {
          $instance = new self;
          $instance->setup();
      }

      return $instance;
  }

  /**
   * Constructor method.
   *
   * @access private
   * @return void
   */
  private function __construct()
  {}


  /**
	 * Class constructor
	 */
	public function setup() {

    global $wpdb;
    global $user;
    global $wp;

    // Global defaults
		$this->path = trailingslashit( plugin_dir_path( __FILE__ ) );
		$this->url  = trailingslashit( plugin_dir_url( __FILE__ ) );

    $this->wp            = $wp;
    $this->user          = $user;
		$this->wpdb          = $wpdb;
		$this->flowlog_table = $wpdb->prefix . 'flowlog';

    // Install database tables
  	add_filter( 'plugins_loaded', array( $this, 'install' ), 20 );

  }


  /**
	 * Install tables
	 * @return boolean result
	 */
	public function install() {

		if ( ! $this->check_if_update() ) {
			return false;
		}

		$charset_collate = '';

		if ( ! empty( $this->wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET {$this->wpdb->charset}";
		}

		if ( ! empty( $this->wpdb->collate ) ) {
			$charset_collate .= " COLLATE {$this->wpdb->collate}";
		}

		$sql = "
		CREATE TABLE {$this->flowlog_table} (
			ID bigint(20) NOT NULL AUTO_INCREMENT,
      user_id bigint(20) NOT NULL DEFAULT '0',
      post_id bigint(20) NOT NULL DEFAULT '0',
      type varchar(64) NOT NULL DEFAULT '',
      message longtext NOT NULL,
      variables longtext NOT NULL,
      severity tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
      link varchar(255) NOT NULL DEFAULT '',
      location text NOT NULL,
      referer text,
      timestamp bigint(20) NOT NULL DEFAULT '0',
			UNIQUE KEY ID (ID)
		) $charset_collate;
		";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		dbDelta( $sql );

		update_option( 'flowlog_db_version', $this->db_version );

		return true;

	}


  /**
   * Check if update database
   * @return boolean true if update needed
   */
  public function check_if_update() {

    $current_version = get_option( 'flowlog_db_version' );

    return $current_version === false || $current_version < $this->db_version;

  }


  /**
   * Logs a system message - based on Drupal's watchdog function
   *
   * @param $type
   *   The category to which this message belongs. Can be any string, but the
   *   general practice is to use the name of the plugin calling log().
   * @param $message
   *   The message to store in the log. Keep $message translatable
   *   by not concatenating dynamic values into it! Variables in the
   *   message should be added by using placeholder strings alongside
   *   the variables argument to declare the value of the placeholders.
   * @param $variables
   *   Array of variables to replace in the message on display or
   *   NULL if message is already translated or not possible to
   *   translate.
   * @param $post_id
   *   A post ID to associate with the message.
   * @param $severity
   *   The severity of the message; one of the following values as defined in
   *   @link http://www.faqs.org/rfcs/rfc3164.html RFC 3164: @endlink
   *   - FLOWLOG_WARNING: Warning conditions.
   *   - FLOWLOG_NOTICE: (default) Normal but significant conditions.
   *   - FLOWLOG_INFO: Informational messages.
   *   - FLOWLOG_DEBUG: Debug-level messages.
   * @param $link
   *   A link to associate with the message.
   *
   */
  function log($type, $message, $variables = array(), $post_id = 0, $severity = FLOWLOG_INFO, $link = '') {

    // User object may not exist, 0 is substituted if needed
    $user_id = isset($user->ID) ? $user->ID : 0;

    // Prepare the fields to be logged
    $log_entry = array(

      'user_id'   => $user_id,
      'post_id'   => $post_id,
      'type'      => substr( $type, 64 ),
      'message'   => $message,
      'variables' => serialize( $variables ),
      'severity'  => constant('self::' . $severity),
      'link'      => substr( $link, 255 ),
      'location'  => home_url( add_query_arg( array(), $this->wp->request ) ),
      'referer'   => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
      'timestamp' => time(),

    );

    $this->db_write_log( $log_entry );

  }


  /**
   * Write the log to the database
   *
   * @param $log_entry
   * @return int||bool The number of rows inserted, or false on error.
   */
  function db_write_log( $log_entry ) {

    return $this->wpdb->insert(
			$this->flowlog_table,
      $log_entry,
      array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d' )
		);

  }


  /**
   * Fetch logs by post ID
   */
  function fetch_post_logs( $post_id ) {

    // Get everything for the specified post_id
    $logs = $this->wpdb->get_results(
    	"
    	SELECT *
    	FROM $this->flowlog_table
    	WHERE post_id = $post_id
      ORDER BY timestamp DESC
    	"
    );

    if( $logs ) return $logs;

    return false;

  }


  /**
   * TODO: Fetch logs by date range, limit, offset etc.
   */
  function fetch_logs() {



  }


  /**
   * Render post logs HTML
   */
  function render_post_logs( $logs ) {

    if( !$logs ) return;

    ?>

    <div class="flow-log">

      <?php foreach( $logs as $log ) : ?>

        <?php

          $datetime = date( 'd/m/Y H:i:s', $log->timestamp );
          $message  = vsprintf( $log->message, unserialize( $log->variables ) );
          $class    = !empty( $log->type ) ? 'fl--log--type-' . $log->type : '';

        ?>

        <div class="fl--log <?php print $class; ?>">

          <div class="fl--log--timestamp"><?php print $datetime; ?></div>
          <div class="fl--log--message"><?php print $message; ?></div>

        </div>

      <?php endforeach; ?>

    </div>

    <?php

  }


}



/**
 * Gets the instance of the `Flow` class.
 *
 * @access public
 * @return object
 */
function flow_log()
{
    return FlowLog::get_instance();
}

// Let's roll!
flow_log();