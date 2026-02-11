<?php

// Prevent direct access to the file
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Define the path to the logo image file (absolute path)
if ( ! defined( 'LOGO_TERME_PATH' ) ) {
    define( 'LOGO_TERME_PATH', plugin_dir_path( __FILE__ ) . 'assets/header-la-via-delle-terme.jpg' );
}
  
// Define the URL to the logo image file (URL for use in browser or email)
if ( ! defined( 'LOGO_TERME_URL' ) ) {
    define( 'LOGO_TERME_URL', plugin_dir_url( __FILE__ ) . 'assets/header-la-via-delle-terme.jpg' );
}

// Define a constant to use for sending HTML emails
if ( ! defined( 'HTML_EMAIL_HEADERS' ) ) {
    define( 'HTML_EMAIL_HEADERS', array( 'Content-Type: text/html; charset=UTF-8' ) );
}

// Define the path to the footer coupon image file (absolute path)
if ( ! defined( 'LOGO_TERME_FOOTER_PATH' ) ) {
    define( 'LOGO_TERME_FOOTER_PATH', plugin_dir_path( __FILE__ ) . 'assets/footer-coupon1.JPG' );
}