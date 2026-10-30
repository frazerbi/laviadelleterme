<?php
/**
 * Disabilita funzionalità inutili di WordPress per siti Elementor.
 */

if ( ! defined( 'ABSPATH' ) ) exit;


/* ----------------------------------------------------------
 * 1. DISABILITA GUTENBERG / BLOCK EDITOR
 * ---------------------------------------------------------- */
add_filter( 'use_block_editor_for_post',      '__return_false' );
add_filter( 'use_block_editor_for_post_type', '__return_false' );


/* ----------------------------------------------------------
 * 2. RIMUOVI ASSET DI GUTENBERG (CSS inutili sul frontend)
 * ---------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', function () {
    wp_dequeue_style( 'wp-block-library' );
    wp_dequeue_style( 'wp-block-library-theme' );
    wp_dequeue_style( 'global-styles' );
    wp_dequeue_style( 'classic-theme-styles' );
}, 100 );


/* ----------------------------------------------------------
 * 3. RIMUOVI SCRIPT E STILE EMOJI
 * ---------------------------------------------------------- */
remove_action( 'wp_head',             'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles',     'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles',  'print_emoji_styles' );
add_filter(    'emoji_svg_url',       '__return_false' );


/* ----------------------------------------------------------
 * 4. DISABILITA oEMBED
 * ---------------------------------------------------------- */
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
remove_action( 'wp_head', 'wp_oembed_add_host_js' );
add_filter( 'embed_oembed_discover', '__return_false' );


/* ----------------------------------------------------------
 * 5. PULIZIA <head>
 * ---------------------------------------------------------- */
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
remove_action( 'wp_head', 'feed_links_extra', 3 );
remove_action( 'wp_head', 'feed_links', 2 );


/* ----------------------------------------------------------
 * 6. DISABILITA XML-RPC
 * ---------------------------------------------------------- */
add_filter( 'xmlrpc_enabled', '__return_false' );


/* ----------------------------------------------------------
 * 7. DISABILITA COMMENTI
 * ---------------------------------------------------------- */
add_filter( 'comments_open',  '__return_false', 20, 2 );
add_filter( 'pings_open',     '__return_false', 20, 2 );
add_filter( 'comments_array', '__return_empty_array', 10, 2 );

add_action( 'wp_enqueue_scripts', function () {
    wp_dequeue_script( 'comment-reply' );
}, 100 );


/* ----------------------------------------------------------
 * 9. HEARTBEAT API — 1 richiesta al minuto, solo nell'editor
 * ---------------------------------------------------------- */
add_filter( 'heartbeat_settings', function ( $settings ) {
    $settings['interval'] = 60;
    return $settings;
} );

add_action( 'init', function () {
    global $pagenow;
    if ( $pagenow !== 'post.php' && $pagenow !== 'post-new.php' ) {
        wp_deregister_script( 'heartbeat' );
    }
} );


/* ----------------------------------------------------------
 * 10. DISABILITA SELF-PING
 * ---------------------------------------------------------- */
add_action( 'pre_ping', function ( &$links ) {
    $home = get_option( 'home' );
    foreach ( $links as $key => $link ) {
        if ( str_starts_with( $link, $home ) ) {
            unset( $links[ $key ] );
        }
    }
} );


/* ----------------------------------------------------------
 * 11. LIMITA LE REVISIONI DEI POST
 * ---------------------------------------------------------- */
add_filter( 'wp_revisions_to_keep', function () {
    return 3;
} );


/* ----------------------------------------------------------
 * 12. ELEMENTOR — rimuovi script raramente usati
 * ---------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', function () {
    wp_dequeue_style( 'jquery-ui' );
    wp_dequeue_script( 'sharing' );
}, 100 );