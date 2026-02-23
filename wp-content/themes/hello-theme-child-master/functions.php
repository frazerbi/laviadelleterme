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

}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_scripts', 20 );

require_once  get_stylesheet_directory() . '/customize-my-account.php';
require_once get_stylesheet_directory() . '/controllo-codici/controllo_codici_DB.php';
require_once get_stylesheet_directory() . '/controllo-codici/controllo_codici_DB_promo.php';


add_filter( 'wc_google_analytics_pro_do_not_track_completed_purchase', '__return_false' );

//hide shipping rate
function my_hide_shipping_when_free_is_available( $rates ) {
	$free = array();

	foreach ( $rates as $rate_id => $rate ) {
		if ( 'free_shipping' === $rate->method_id ) {
			$free[ $rate_id ] = $rate;
			break;
		}
	}

	return ! empty( $free ) ? $free : $rates;
}
add_filter( 'woocommerce_package_rates', 'my_hide_shipping_when_free_is_available', 50 );

//Change the 'Billing details' checkout label to 'Contact Information'
function wc_billing_field_strings( $translated_text, $text, $domain ) {
    switch ( $translated_text ) {
        case 'Dettagli di fatturazione' :
        $translated_text = __( 'Acquirente Coupon', 'woocommerce' );
        break;
    }
    return $translated_text;
}
add_filter( 'gettext', 'wc_billing_field_strings', 20, 3 );

//disable admin notfication for password change
if ( ! function_exists( 'wp_password_change_notification' ) ) :
    function wp_password_change_notification( $user ) {
        return;
    }
endif;
remove_action( 'after_password_reset', 'wp_password_change_notification' );
add_filter( 'woocommerce_disable_password_change_notification', '__return_true' );


/**
 * Mostra i codici di licenza nell'email di riepilogo dell'ordine
 */
add_action('wc_ld_license_instructions', 'wc_ld_show_license_codes_in_email', 10, 1);
function wc_ld_show_license_codes_in_email($order) {

    // Verifica che la classe esista
    if (!class_exists('WC_LD_Code_Assignment')) {
        error_log('WC_LD: Class WC_LD_Code_Assignment not found');
        return;
    }
    
    // Verifica che $order sia un oggetto valido
    if (!is_a($order, 'WC_Order')) {
        error_log('WC_LD: Invalid order object');
        return;
    }

    // Controlla lo stato dell'ordine
    $delivery_order_status = get_option('wc_ld_delivery_order_status');
    $expected_status = empty($delivery_order_status) ? 'processing' : $delivery_order_status;
    $current_status = $order->get_status();
    
    // Log per debug
    error_log("WC_LD: delivery_order_status option raw: '" . var_export($delivery_order_status, true) . "'");
    error_log("WC_LD: Order ID: " . $order->get_id());
    error_log("WC_LD: Current status: '{$current_status}', Expected: '{$expected_status}'");
    
    // Mostra i codici solo se l'ordine è nello stato corretto
    if ( $current_status !== 'completed') {
        error_log("WC_LD: Order status mismatch, skipping license display");
        return;
    }

    // Ottieni l'istanza della classe
    $license_code_assignment = new WC_LD_Code_Assignment();
    
    // Ottieni tutti gli elementi dell'ordine
    $items = $order->get_items();
    
    // Flag per tenere traccia se abbiamo trovato codici da mostrare
    $codes_found = false;
    $license_content = '';

    // Itera attraverso tutti gli elementi dell'ordine per raccogliere i codici
    foreach ($items as $item) {
        // Verifica se l'item ha codici di licenza
        $product_id = $item->get_product_id();
        $is_license_code = get_post_meta($product_id, '_wc_ld_license_code', true);
        
        if ($is_license_code === 'yes') {
            // Cattura l'output del metodo display_license_codes
            ob_start();
            $license_code_assignment->display_license_codes($item);
            $license_output = ob_get_clean();
            
            if (!empty(trim($license_output))) {
                // Aggiungi il nome del prodotto e i codici al contenuto
                $license_content .= '<h4 style="margin: 10px 0 5px; color: #666;">' . esc_html($item->get_name()) . '</h4>';
                $license_content .= $license_output;
                $codes_found = true;
            }
        }
    }

    // Mostra il contenitore solo se abbiamo trovato dei codici
    if ($codes_found) {
        echo '<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: \'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif; margin: 0 0 16px;" border="1">';
        
        // Header con stile WooCommerce
        echo '<thead>';
        echo '<tr>';
        echo '<th class="td" scope="col" style="text-align: left;">Dettagli codice assegnato</th>';
        echo '</tr>';
        echo '</thead>';
        
        // Corpo della tabella
        echo '<tbody>';
        echo '<tr>';
        echo '<td class="td" style="text-align: left; vertical-align: middle; font-family: \'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif; word-wrap: break-word;">';
        echo $license_content;
        echo '</td>';
        echo '</tr>';
        echo '</tbody>';
        
        echo '</table>';
        
        // Log per debug
        error_log("WC_LD: Displayed license codes for order: " . $order->get_id() . ", codes found: yes");
    } else {
        error_log("WC_LD: No license codes found for order: " . $order->get_id());
    }
}