<?php
/**
 * Theme functions and definitions
 *
 * @package HelloElementorChild
 */
/**
 * Load child theme css and optional scripts
 *
 * @return void
 */
function hello_elementor_child_enqueue_scripts() {
	wp_enqueue_style('hello-elementor-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		[
			'hello-elementor-theme-style',
		],
		'1.7.9'
	);

	// Carica un secondo foglio di stile personalizzato
	wp_enqueue_style( 'custom-style-menu', get_stylesheet_directory_uri() . '/mobile-menu-style.css', ['hello-elementor-child-style'], '1.0.2' );

	wp_enqueue_script( 'custom-js', get_stylesheet_directory_uri() . '/js/script.js', array( 'jquery' ),'1.0.1',true );

	wp_enqueue_style( 'controllo-codici-style', get_stylesheet_directory_uri() . '/controllo-codici/controllo-codici.css', [], '1.0.0' );

}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_scripts', 20 );

require_once  get_stylesheet_directory() . '/customize-my-account.php';
require_once get_stylesheet_directory() . '/controllo-codici/controllo_codici_DB.php';
require_once get_stylesheet_directory() . '/controllo-codici/controllo_codici_DB_promo.php';
