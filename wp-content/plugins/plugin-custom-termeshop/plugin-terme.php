<?php
/*
Plugin Name: Plugin Custom Termeshop
Plugin URI: 
Description: Custom plugin with functions for sending Coupons
Version: 1.00
Author: Francesco Zerbinato
Author URI: 
Text Domain: LVDT
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly for security
}

// Define the absolute path of the main plugin file
if ( ! defined( 'PLUGIN_TERME_FILE' ) ) {
    define( 'PLUGIN_TERME_FILE', __FILE__ );
}

if ( ! defined( 'PLUGIN_TERME_PATH' ) ) {
    define( 'PLUGIN_TERME_PATH', plugin_dir_path( PLUGIN_TERME_FILE ) );
}

require_once PLUGIN_TERME_PATH . 'config.php';

// Include other plugin components
require_once( PLUGIN_TERME_PATH . 'Order Management/add_status.php' );
// require_once( PLUGIN_TERME_PATH . 'Order Management/autocomplete-order.php' );
// require_once( PLUGIN_TERME_PATH . 'Order Management/codes-to-termegest.php' );
// require_once( PLUGIN_TERME_PATH . 'Order Management/send-emails.php' );
// require_once( PLUGIN_TERME_PATH . 'Order Management/send-emails-to-admin.php' );
// require_once( PLUGIN_TERME_PATH . 'Order Management/send-email-not-booked.php' );
// require_once( PLUGIN_TERME_PATH . 'Order Management/send-email-booked.php' );
// require_once( PLUGIN_TERME_PATH . 'Order Management/process-orders-custom.php' );

require_once( PLUGIN_TERME_PATH . 'registration-form-extension/registration-form-ext.php' );
