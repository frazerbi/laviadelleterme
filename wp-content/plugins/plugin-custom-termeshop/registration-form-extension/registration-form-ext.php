<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function save_registration_form_data($user_id) {
    // Check if the form was submitted.
    if (isset($_POST['form_fields']) && is_array($_POST['form_fields'])) {
        
        // Verifica che esistano i campi necessari
        if (isset($_POST['form_fields']['nome']) && isset($_POST['form_fields']['cognome'])) {
            $name = sanitize_text_field($_POST['form_fields']['nome']);
            $last_name = sanitize_text_field($_POST['form_fields']['cognome']);
            
            update_user_meta($user_id, 'first_name', $name);
            update_user_meta($user_id, 'last_name', $last_name);
        }
    }
}
add_action('user_register', 'save_registration_form_data', 10, 1);