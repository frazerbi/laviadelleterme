<?php

declare(strict_types=1);

use Automattic\WooCommerce\Admin\Overrides\Order;

add_action('woocommerce_after_add_to_cart_quantity', 'skianet_termegest_booking_after_add_to_cart_quantity', \PHP_INT_MAX);
function skianet_termegest_booking_after_add_to_cart_quantity(): void
{
    global $product;

    if (
        ! $product instanceof WC_Product
        || (! doing_filter('wp_ajax_'.SKIANET_ACTION_GET_BOOK_DIALOG)
            && ! doing_filter('wp_ajax_nopriv_'.SKIANET_ACTION_GET_BOOK_DIALOG))
    ) {
        return;
    }

    $pars = skianet_termegest_booking_get_form_parameters();

    if (empty($pars)) {
        return;
    }

    foreach (SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM as $baseKey => $item) {
        $key = $item['key'];
        echo '<input type="hidden" name="'.$key.'" id="'.$key.'" value="'.esc_attr($pars[$baseKey]).'">';
    }
}

add_filter('woocommerce_add_to_cart_validation', 'skianet_termegest_booking_add_to_cart_validation', \PHP_INT_MAX);
function skianet_termegest_booking_add_to_cart_validation(bool $passed): bool
{
    if (
        ! doing_action('wp_ajax_'.SKIANET_ACTION_BOOKING_ADD_CART)
        && ! doing_action('wp_ajax_nopriv_'.SKIANET_ACTION_BOOKING_ADD_CART)
    ) {
        return $passed;
    }

    $pars = skianet_termegest_booking_get_form_parameters();

    foreach (SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM as $item) {
        if (empty($pars[$item['key']])) {
            wc_add_notice(\sprintf(__('Campo %s mancante', PLUGIN_SKIANET_TEXT_DOMAIN), $item['value']), 'error');

            return false;
        }
    }

    return $passed;
}

add_filter('woocommerce_add_cart_item_data', 'skianet_termegest_booking_add_cart_item_data', \PHP_INT_MAX);

function skianet_termegest_booking_add_cart_item_data(array $cart_item_data): array
{
    if (
        ! doing_action('wp_ajax_'.SKIANET_ACTION_BOOKING_ADD_CART)
        && ! doing_action('wp_ajax_nopriv_'.SKIANET_ACTION_BOOKING_ADD_CART)
    ) {
        return $cart_item_data;
    }

    $pars = skianet_termegest_booking_get_form_parameters();

    foreach (SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM as $baseKey => $item) {
        $key = $item['key'];
        if ($baseKey === 'event') {
            try {
                $pars[$key] = json_decode(html_entity_decode((string) $pars[$key], \ENT_QUOTES, 'UTF-8'), true, 512, \JSON_THROW_ON_ERROR);
                if (! empty($pars[$key]['start'])) {
                    $pars[$key] = (new DateTimeImmutable($pars[$key]['start']))->format('d/m/Y H:i');
                }
            } catch (Throwable) {
                error_log("JSON decode error: " . $e->getMessage());
                wc_add_notice(__('Errore nel formato dei dati', PLUGIN_SKIANET_TEXT_DOMAIN), 'error');
                continue;
            }
        }

        $cart_item_data[$key] = $pars[$key];
    }

    return $cart_item_data;
}

add_filter('woocommerce_get_item_data', 'skianet_termegest_booking_get_item_data', \PHP_INT_MAX, 2);
function skianet_termegest_booking_get_item_data(array $item_data, array $cart_item): array
{
    foreach (SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM as $baseKey => $item) {
        $key = $item['key'];
        if (\in_array($baseKey, ['id', 'qty'], true)) {
            continue;
        }

        if (empty($cart_item[$key])) {
            continue;
        }

        $item_data[] = [
            'key' => $item['value'],
            'value' => $cart_item[$key],
        ];
    }

    return $item_data;
}

add_action('woocommerce_checkout_create_order_line_item', 'skianet_termegest_booking_checkout_create_order_line_item', \PHP_INT_MAX, 4);
/**
 * @param string[] $values
 */
function skianet_termegest_booking_checkout_create_order_line_item(WC_Order_Item_Product $wcOrderItemProduct, string $cart_item_key, array $values, WC_Order $wcOrder): void
{       

    foreach (SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM as $item) {
        $key = $item['key'];

         // CONTROLLO: Salva solo se il valore esiste e non è vuoto
        if (isset($values[$key]) && !empty($values[$key])) {
            $wcOrder->add_meta_data($key, $values[$key], true);
            $wcOrderItemProduct->add_meta_data($key, $values[$key], true);
            error_log("Saved to order and item: {$key} = " . $values[$key]);
        }   
    }
    $idKey = SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM['id']['key'] ?? '';

    if (!empty($values[$idKey])) {
        // Recupera i dati gender da $_POST
        $maleKey = SKIANET_CUSTOM_BOOKING_PARAMS['male'] ?? '';
        $femaleKey = SKIANET_CUSTOM_BOOKING_PARAMS['female'] ?? '';
        
        if (!empty($_POST[$maleKey])) {
            $wcOrderItemProduct->add_meta_data($maleKey, (int)$_POST[$maleKey], true);
        }
        if (!empty($_POST[$femaleKey])) {
            $wcOrderItemProduct->add_meta_data($femaleKey, (int)$_POST[$femaleKey], true);
        }
    }
    
}

add_filter('woocommerce_order_item_display_meta_key', 'skianet_termegest_booking_order_item_display_meta_key', \PHP_INT_MAX, 2);
function skianet_termegest_booking_order_item_display_meta_key(string $display_key, WC_Meta_Data $wcMetaData): string
{
    foreach (SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM as $item) {
        if (empty($wcMetaData->key)) {
            continue;
        }

        if ($wcMetaData->key !== $item['key']) {
            continue;
        }

        return $item['value'];
    }

    foreach (SKIANET_CUSTOM_BOOKING_PARAMS_NAMES as $key => $value) {
        if (empty($wcMetaData->key)) {
            continue;
        }

        if ($wcMetaData->key !== $key) {
            continue;
        }

        return $value;
    }

    return $display_key;
}

add_filter('woocommerce_order_item_get_formatted_meta_data', 'skianet_termegest_booking_order_item_get_formatted_meta_data', \PHP_INT_MAX);
function skianet_termegest_booking_order_item_get_formatted_meta_data(array $formatted_meta): array
{
    if (! is_admin()) {
        foreach ($formatted_meta as $metaKey => $metaValue) {
            foreach (SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM as $baseKey => $item) {
                if (! \in_array($baseKey, ['id', 'qty'], true)) {
                    continue;
                }

                if (empty($metaValue->key)) {
                    continue;
                }

                if ($metaValue->key !== $item['value']) {
                    continue;
                }

                unset($formatted_meta[$metaKey]);
            }
        }
    }

    return $formatted_meta;
}

add_action('woocommerce_admin_order_data_after_billing_address', 'skianet_termegest_custom_fields_order_admin', \PHP_INT_MAX);
function skianet_termegest_custom_fields_order_admin(Order $order): void
{
    $order_id = $order->get_id();

    foreach (SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM as $item) {
        $meta = get_post_meta($order_id, $item['key'], true);

        echo '<p><strong>'.$item['value'].':</strong> '.$meta.'</p>';
    }

    foreach (SKIANET_CUSTOM_BOOKING_PARAMS_NAMES as $key => $value) {
        $meta = get_post_meta($order_id, $key, true);

        echo '<p><strong>'.$value.':</strong> '.$meta.'</p>';
    }
}

add_action('woocommerce_after_checkout_billing_form', 'skianet_termegest_custom_fields_billing_form', \PHP_INT_MAX);
function skianet_termegest_custom_fields_billing_form(WC_Checkout $wcCheckout): void
{
    echo '<div class="woocommerce-skn-custom-fields__field-wrapper">';
    $fields = $wcCheckout->get_checkout_fields(SKIANET_CUSTOM_BOOKING_BASE);
    foreach ($fields as $key => $field) {
        woocommerce_form_field($key, $field, $wcCheckout->get_value($key));
    }

    echo '</div>';
}

add_filter('woocommerce_checkout_fields', 'skianet_termegest_custom_fields_checkout', \PHP_INT_MAX);
function skianet_termegest_custom_fields_checkout(array $fields): array
{
    if (!function_exists('WC') || !WC()->cart) {
        return $fields;
    }

    $hasCalendarProducts = false;
    $hasNonCalendarProducts = false;

    foreach (WC()->cart->get_cart_contents() as $cartItem) {
        $idValue = $cartItem[SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM['id']['key']] ?? '';

        if (empty($idValue)) {
            $hasNonCalendarProducts = true;
            continue;
        }

        $hasCalendarProducts = true;

        // Crea i campi nascosti per questo prodotto calendario
        foreach (SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM as $baseKey => $item) {
            $key = SKIANET_CUSTOM_BOOKING_BASE.'-'.$idValue.'-'.$baseKey;
            $itemValue = $cartItem[$item['key']] ?? '';
            
            $fields[SKIANET_CUSTOM_BOOKING_BASE][$key] = [
                'type' => 'hidden',
                'default' => $itemValue,
                'class' => ['ast-hidden'],
            ];
        }
    }

    // Determina se mostrare i campi calendario
    // MOSTRA i campi se c'è ALMENO UN prodotto con calendario
    $showCalendarFields = $hasCalendarProducts;

    $fields[SKIANET_CUSTOM_BOOKING_BASE][SKIANET_CUSTOM_BOOKING_PARAMS['fromCalendar']] = [
        'type' => 'hidden',
        'default' => (int) $showCalendarFields,
        'class' => ['ast-hidden'],
    ];

    $default = [
        'label' => '',
        'type' => 'number',
        'required' => true,
        'class' => ['skn-qty'],
        'default' => 0,
        'custom_attributes' => [
            'required' => true,
            'min' => 0,
            'max' => 10,
            'step' => 1,
            'pattern' => '\d*',
        ],
    ];

    // Se non ci sono prodotti calendario, nascondi i campi
    if (!$showCalendarFields) {
        $default['type'] = 'hidden';
        $default['required'] = false;
        $default['class'] = ['ast-hidden'];
        $default['custom_attributes']['required'] = false;
    }

    $fields[SKIANET_CUSTOM_BOOKING_BASE][SKIANET_CUSTOM_BOOKING_PARAMS['male']] = array_merge($default, [
        'label' => $showCalendarFields ? __('Numero uomini', PLUGIN_SKIANET_TEXT_DOMAIN) : '',
        'class' => array_merge($default['class'], ['form-row-first']),
    ]);

    $fields[SKIANET_CUSTOM_BOOKING_BASE][SKIANET_CUSTOM_BOOKING_PARAMS['female']] = array_merge($default, [
        'label' => $showCalendarFields ? __('Numero donne', PLUGIN_SKIANET_TEXT_DOMAIN) : '',
        'class' => array_merge($default['class'], ['form-row-last']),
    ]);

    return $fields;
}

add_action('woocommerce_after_checkout_validation', 'skianet_termegest_custom_fields_validation', \PHP_INT_MAX, 2);
function skianet_termegest_custom_fields_validation(array $data, WP_Error $wpError): void
{   
    if (!defined('SKIANET_CUSTOM_BOOKING_PARAMS')) {
        error_log("ERROR: SKIANET_CUSTOM_BOOKING_PARAMS constant not defined");
        $wpError->add('config_error', __('Configurazione plugin mancante', PLUGIN_SKIANET_TEXT_DOMAIN));
        return;
    }

    if (!defined('SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM')) {
        error_log("ERROR: SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM constant not defined");
        $wpError->add('config_error', __('Configurazione booking mancante', PLUGIN_SKIANET_TEXT_DOMAIN));
        return;
    }

    $fromCalendarKey = SKIANET_CUSTOM_BOOKING_PARAMS['fromCalendar'];
    $fromCalendarValue = $data[$fromCalendarKey] ?? null;

    // Poi fai una validazione più specifica
    if ($fromCalendarValue === null) {
        error_log("fromCalendar value is missing");
        $wpError->add('validation_error', __('Dati prenotazione mancanti', PLUGIN_SKIANET_TEXT_DOMAIN));
        return;
    }

    if (!is_numeric($fromCalendarValue)) {
        error_log("fromCalendar value is not numeric: {$fromCalendarValue}");
        $wpError->add('validation_error', __('Dati prenotazione non validi', PLUGIN_SKIANET_TEXT_DOMAIN));
        return;
    }

    $fromCalendarInt = (int) $fromCalendarValue;
    if ($fromCalendarInt === 0) {
        error_log("Skipping validation: fromCalendar is 0 (not from calendar booking)");
        return;
    }

    $cartContents = WC()->cart->get_cart_contents();

    // Mappatura nome prodotto -> categoria (usando il formato normalizzato dal log)
    $productCategoryMap = [
        'ingresso lunedì-venerdì-mezza giornata' => 'p1',   // Nota: spazi intorno a "mezza giornata"  
        'ingresso lunedì-domenica-mezza giornata' => 'p2',  // Nota: spazi intorno a "mezza giornata"
        'ingresso lunedì-venerdì-giornaliero' => 'p3',
        'ingresso lunedì-domenica-giornaliero' => 'p4'
    ];

    $categoriesData = []; // [categoria => quantità]
    $calendarProductsQty = 0; // NUOVO: conta solo prodotti con calendario

    // Log each cart key specifically
    foreach ($cartContents as $cartKey => $item) {

        $itemQty = (int) ($item['qty'] ?? $item['quantity'] ?? 0);
        $productName = $item['data']->get_name();

        // Validazione: $productName non può essere vuoto
        if (empty($productName)) {
            // error_log("ERROR: Product name is empty for cart key: {$cartKey}");
            $wpError->add('product_error', __('Errore nel prodotto nel carrello', PLUGIN_SKIANET_TEXT_DOMAIN));
            return;
        }

        // Controlla se questo prodotto ha dati calendario
        // $hasCalendarData = isset($item['skn-custom-id']) && !empty($item['skn-custom-id']);

        $calendarIdKey = SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM['id']['key'] ?? 'skn-custom-id';
        $hasCalendarData = isset($item[$calendarIdKey]) && !empty($item[$calendarIdKey]);

        // Se questo prodotto non ha calendario, saltalo per il conteggio
        if (!$hasCalendarData) {
            // error_log("Skipping product '{$productName}' - no calendar data");
            continue;
        }

        // Aggiungi alla quantità solo se ha calendario
        $calendarProductsQty += $itemQty;

        // Normalizza il nome del prodotto per la ricerca
        $normalizedProductName = normalize_product_name($productName);

        // Mappatura alla categoria
        if (!array_key_exists($normalizedProductName, $productCategoryMap)) {
            error_log("ERROR: Unknown product name '{$productName}' (normalized: '{$normalizedProductName}') - no category mapping found");
            error_log("Available normalized keys: " . implode(', ', array_keys($productCategoryMap)));
            $wpError->add('product_error', __('Prodotto non supportato nel carrello', PLUGIN_SKIANET_TEXT_DOMAIN));
            return;
        }

        $currentCategoria = $productCategoryMap[$normalizedProductName];
        
        // Popola categoriesData (era mancante)
        if (!isset($categoriesData[$currentCategoria])) {
            $categoriesData[$currentCategoria] = 0;
        }
        $categoriesData[$currentCategoria] += $itemQty;

        // Crea dati filtrati solo per questo prodotto
        $filteredData = [];

        foreach ($data as $key => $value) {
            if (!str_starts_with($key, SKIANET_CUSTOM_BOOKING_BASE)) {
                $filteredData[$key] = $value;
            }
        }

        $customId = $item[$calendarIdKey] ?? null;

        if ($customId) {
            $filteredData["skn-custom-{$customId}-id"] = $data["skn-custom-{$customId}-id"] ?? null;
            $filteredData["skn-custom-{$customId}-location"] = $data["skn-custom-{$customId}-location"] ?? null;
            $filteredData["skn-custom-{$customId}-event"] = $data["skn-custom-{$customId}-event"] ?? null;
            $filteredData["skn-custom-{$customId}-qty"] = $data["skn-custom-{$customId}-qty"] ?? null;
        }
            
        // Aggiungi i campi comuni
        $filteredData[SKIANET_CUSTOM_BOOKING_PARAMS['fromCalendar']] = $data[SKIANET_CUSTOM_BOOKING_PARAMS['fromCalendar']] ?? null;
        $filteredData[SKIANET_CUSTOM_BOOKING_PARAMS['male']] = $data[SKIANET_CUSTOM_BOOKING_PARAMS['male']] ?? null;
        $filteredData[SKIANET_CUSTOM_BOOKING_PARAMS['female']] = $data[SKIANET_CUSTOM_BOOKING_PARAMS['female']] ?? null;
        
        error_log("Product: '{$productName}' -> Category: '{$currentCategoria}' (Qty: {$itemQty})");
        error_log("Filtered data for this product: " . print_r($filteredData, true));
        
        skianet_termegest_custom_check_availability_for_each_item($filteredData, $wpError, $currentCategoria);
    
        if ($wpError->has_errors()) {
            return;
        }

    }  

    if (empty($categoriesData) || $calendarProductsQty === 0) {
        $wpError->add('product_error', __('Nessun prodotto con calendario trovato nel carrello', PLUGIN_SKIANET_TEXT_DOMAIN));
        return;
    }

    if (
        (! is_numeric($data[SKIANET_CUSTOM_BOOKING_PARAMS['male']])
            || (int) $data[SKIANET_CUSTOM_BOOKING_PARAMS['male']] < 0
            || (int) $data[SKIANET_CUSTOM_BOOKING_PARAMS['male']] > 10)
        && ! $wpError->has_errors()
    ) {
        $wpError->add(SKIANET_CUSTOM_BOOKING_PARAMS['male'], __('Inserisci un numero valido di uomini', PLUGIN_SKIANET_TEXT_DOMAIN));

        return;
    }

    if (
        (! is_numeric($data[SKIANET_CUSTOM_BOOKING_PARAMS['female']])
            || (int) $data[SKIANET_CUSTOM_BOOKING_PARAMS['female']] < 0
            || (int) $data[SKIANET_CUSTOM_BOOKING_PARAMS['female']] > 10)
        && ! $wpError->has_errors()
    ) {
        $wpError->add(SKIANET_CUSTOM_BOOKING_PARAMS['female'], __('Inserisci un numero valido di donne', PLUGIN_SKIANET_TEXT_DOMAIN));

        return;
    }

    $total = (int) ($data[SKIANET_CUSTOM_BOOKING_PARAMS['male']] ?? 0) + (int) ($data[SKIANET_CUSTOM_BOOKING_PARAMS['female']] ?? 0);
    
    // Confronta con calendar products quantity
    if (! $wpError->has_errors() && $total !== $calendarProductsQty) {
        error_log("ERROR: Total persons ({$total}) != Calendar products quantity ({$calendarProductsQty})");
        $wpError->add(
            SKIANET_CUSTOM_BOOKING_PARAMS['qty'], 
            __('Il numero di uomini e donne non corrisponde al numero di ingressi con prenotazione nel carrello', PLUGIN_SKIANET_TEXT_DOMAIN)
        );
        return; 
    }

}

function skianet_termegest_custom_check_availability_for_each_item(array $data, WP_Error $wpError, string $categoria): void
{       

    // Validazione: categoria non può essere vuota
    if (empty($categoria)) {
        $wpError->add(
            SKIANET_CUSTOM_BOOKING_PARAMS['qty'],
            __('Errore: categoria prodotto non definita', PLUGIN_SKIANET_TEXT_DOMAIN)
        );
        return;
    }


    $fields = [];
    foreach ($data as $key => $value) {
        if (! str_starts_with($key, SKIANET_CUSTOM_BOOKING_BASE)) {
            continue;
        }

        $parts = explode('-', $key);
        if (\count($parts) !== 4) {
            continue;
        }

        $parts = \array_slice($parts, 2);
        if (! \array_key_exists($parts[1], SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM)) {
            continue;
        }

        $fields[$parts[0]][$parts[1]] = $value;
    }
    error_log('Final fields structure: ' . print_r($fields, true));


    $cartUrl = \sprintf('<a href="%s">%s</a>', wc_get_cart_url(), __('carrello', PLUGIN_SKIANET_TEXT_DOMAIN));
    $bookingUrl = \sprintf('<a href="%s">%s</a>', get_permalink(get_page_by_path('disponibilita')), __('disponibilità', PLUGIN_SKIANET_TEXT_DOMAIN));

    foreach ($fields as $id => $value) {
        
        // Salta gli ID vuoti
        if (empty($id) || $id === '') {
            error_log("Skipping empty ID");
            continue;
        }

        // Controlla che tutti i campi richiesti abbiano valori
        if (empty($value['id']) || empty($value['location']) || empty($value['event']) || empty($value['qty'])) {
            error_log("Skipping ID {$id} - missing required data");
            error_log("  id: " . ($value['id'] ?? 'empty'));
            error_log("  location: " . ($value['location'] ?? 'empty'));
            error_log("  event: " . ($value['event'] ?? 'empty'));
            error_log("  qty: " . ($value['qty'] ?? 'empty'));
            continue;
        }

        if (! $wpError->has_errors() && \count($value) !== 4) {
            $wpError->add(
                SKIANET_CUSTOM_BOOKING_PARAMS['qty'],
                __('Errore durante la verifica della disponibilità', PLUGIN_SKIANET_TEXT_DOMAIN)
            );

            return;
        }

        $location = skianet_termegest_encrypt($value['location']);
        
        error_log(sprintf(
            "Checking availability - ID: %s, Location: %s (encrypted: %s), Categoria: %s, Event: %s, Requested qty: %s",
            $id,
            $value['location'], // location originale
            $location,          // location criptata
            $categoria,
            $value['event'],
            $value['qty']
        ));
        $availability = skianet_termegest_get_disponibilitaById( (int) $id, $location, $categoria );

        // Log del risultato
        error_log(sprintf(
            "Availability result for ID %s: %s (requested: %s)",
            $id,
            $availability,
            $value['qty']
        ));

        if (! $wpError->has_errors() && $availability < (int) $value['qty']) {
            error_log("ERROR: Insufficient availability for ID {$id}. Available: {$availability}, Requested: " . ($value['qty'] ?? 0));
            $wpError->add(
                SKIANET_CUSTOM_BOOKING_PARAMS['qty'],
                \sprintf(
                    __(
                        'Disponibilità non sufficiente per la sede <strong>%s</strong> per la data <strong>%s</strong>.<br>Si prega di tornare al %s, togliere il prodotto dal carrello e selezionare un altro orario alla pagina %s',
                        PLUGIN_SKIANET_TEXT_DOMAIN
                    ),
                    $value['location'],
                    $value['event'],
                    $cartUrl,
                    $bookingUrl
                )
            );

            return;
        }
    }
}


add_action('woocommerce_checkout_create_order', 'skianet_termegest_custom_fields_update_order', \PHP_INT_MAX, 1);
function skianet_termegest_custom_fields_update_order(WC_Order $wcOrder): void
{       
    if (defined('SKIANET_CUSTOM_BOOKING_PARAMS')) {
        error_log("SKIANET_CUSTOM_BOOKING_PARAMS: " . print_r(SKIANET_CUSTOM_BOOKING_PARAMS, true));
    } else {
        error_log("ERROR: SKIANET_CUSTOM_BOOKING_PARAMS not defined in create_order function");
        return;
    }
    
    $data = $_POST;
    error_log("Available POST data keys: " . implode(', ', array_keys($data)));
    error_log("Debug SKIANET_CUSTOM_BOOKING_PARAMS: " . print_r(SKIANET_CUSTOM_BOOKING_PARAMS, true));

    // Validazione input - controlla se le chiavi esistono
    $fromCalendarKey = SKIANET_CUSTOM_BOOKING_PARAMS['fromCalendar'] ?? '';
    $maleKey = SKIANET_CUSTOM_BOOKING_PARAMS['male'] ?? '';
    $femaleKey = SKIANET_CUSTOM_BOOKING_PARAMS['female'] ?? '';
    
    if (empty($fromCalendarKey) || !isset($data[$fromCalendarKey])) {
        return;
    }

    $fromCalendarValue = (int) $data[$fromCalendarKey];
    $wcOrder->add_meta_data($fromCalendarKey, $fromCalendarValue, true);

    if ((int) $data[$fromCalendarKey] === 0) {
        return;
    }

    // Verifica presenza dati male/female prima di usarli
    if (!isset($data[$maleKey]) || !isset($data[$femaleKey])) {
        error_log("Missing male/female data in checkout - male key: '{$maleKey}', female key: '{$femaleKey}'");
        return;
    }    

    $maleValue = (int) $data[$maleKey];
    $femaleValue = (int) $data[$femaleKey];
    error_log("Male value: {$maleValue}, Female value: {$femaleValue}");
    
    // CORREZIONE: Aggiungi i meta gender all'ordine
    $wcOrder->add_meta_data($maleKey, $maleValue, true);
    $wcOrder->add_meta_data($femaleKey, $femaleValue, true);
    error_log("Added gender meta to order");

    $cartContents = WC()->cart->get_cart_contents();

    $idKey = SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM['id']['key'] ?? '';

    foreach ($wcOrder->get_items() as $itemId => $item) {
        // Trova il cart item corrispondente
        $cartKey = $item->get_meta('_cart_item_key');
        
        if ($cartKey && isset($cartContents[$cartKey])) {
            $cartItem = $cartContents[$cartKey];
            
            // Controlla se ha ID calendario
            $hasCalendarData = !empty($idKey) && isset($cartItem[$idKey]) && !empty($cartItem[$idKey]);
            
            if ($hasCalendarData) {
                $item->add_meta_data($maleKey, $maleValue, true);
                $item->add_meta_data($femaleKey, $femaleValue, true);
                error_log("Added male/female to calendar item: " . $item->get_name());
            } 

        }
    }

}

function normalize_product_name($name) {
    // Converti in lowercase
    $normalized = strtolower($name);
    
    // Rimuovi spazi extra
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    
    // Normalizza i dash (sostituisce vari tipi di dash con un dash standard)
    $normalized = preg_replace('/[\-\–\—\―]/', '-', $normalized);
    
    // Rimuovi spazi intorno ai dash
    $normalized = preg_replace('/\s*-\s*/', '-', $normalized);
    
    // Trim generale
    $normalized = trim($normalized);
    
    return $normalized;
}

// ==============================================
// EXPRESS PAYMENT VALIDATION VIA JAVASCRIPT
// Aggiungi questo DOPO le tue funzioni esistenti
// ==============================================

// Blocca i bottoni express finché i campi gender non sono compilati
add_action('wp_footer', 'skianet_termegest_block_express_payments');
function skianet_termegest_block_express_payments(): void
{
    if (!is_checkout()) {
        return;
    }
    
    // Verifica se ci sono prodotti calendario
    if (!skianet_termegest_has_calendar_products()) {
        return;
    }
    
    $maleKey = SKIANET_CUSTOM_BOOKING_PARAMS['male'] ?? '';
    $femaleKey = SKIANET_CUSTOM_BOOKING_PARAMS['female'] ?? '';
    
    ?>
    <style>
    /* CSS per bloccare i bottoni express Stripe */
    .skn-express-blocked,
    .skn-express-blocked *,
    #wc-stripe-express-checkout-element-applePay.skn-express-blocked,
    #wc-stripe-express-checkout-element-googlePay.skn-express-blocked,
    .wc-stripe-express-checkout-element-applePay.skn-express-blocked,
    .wc-stripe-express-checkout-element-googlePay.skn-express-blocked {
        opacity: 0.4 !important;
        pointer-events: none !important;
        filter: grayscale(80%) !important;
        cursor: not-allowed !important;
    }
    
    /* Blocca anche i contenuti interni degli elementi Stripe */
    #wc-stripe-express-checkout-element-applePay.skn-express-blocked > *,
    #wc-stripe-express-checkout-element-googlePay.skn-express-blocked > * {
        pointer-events: none !important;
    }
    
    .express-payment-error {
        background: #dc3232 !important;
        color: white !important;
        padding: 10px !important;
        margin: 10px 0 !important;
        border-radius: 4px !important;
        font-size: 14px !important;
        z-index: 9999 !important;
        position: relative !important;
    }
    </style>
    <script>
    jQuery(function($) {
        console.log('Express payment blocker initialized');
        
        // Selettori per tutti i tipi di express payment
        const expressSelectors = [
            // I TUOI BOTTONI STRIPE SPECIFICI
            '#wc-stripe-express-checkout-element-applePay',
            '#wc-stripe-express-checkout-element-googlePay',
            '.wc-stripe-express-checkout-element-applePay',
            '.wc-stripe-express-checkout-element-googlePay',
            '[id*="wc-stripe-express-checkout-element"]',
            
            // Stripe generici
            '.stripe-apple-pay-button',
            '.stripe-google-pay-button', 
            '[data-payment-method-type="stripe_applepay"]',
            '[data-payment-method-type="stripe_googlepay"]',
            '.payment-request-button',
            '.stripe-payment-request-button',
            
            // PayPal
            '.ppcp-apple-pay-button',
            '.ppcp-google-pay-button',
            '[data-funding-source="applepay"]',
            '[data-funding-source="googlepay"]',
            '.paypal-button-container [aria-label*="Apple"]',
            '.paypal-button-container [aria-label*="Google"]',
            
            // Square
            '.square-apple-pay-button',
            '.square-google-pay-button',
            
            // Generici
            '.apple-pay-button',
            '.google-pay-button',
            '[class*="apple-pay"]',
            '[class*="google-pay"]',
            '[class*="applepay"]',
            '[class*="googlepay"]',
            '[id*="apple-pay"]',
            '[id*="google-pay"]'
        ];
        
        function validateGenderFields() {
            const maleField = $('[name="<?php echo esc_js($maleKey); ?>"]');
            const femaleField = $('[name="<?php echo esc_js($femaleKey); ?>"]');
            
            if (maleField.length === 0 || femaleField.length === 0) {
                console.warn('Gender fields not found');
                return false;
            }
            
            const maleValue = parseInt(maleField.val()) || 0;
            const femaleValue = parseInt(femaleField.val()) || 0;
            const total = maleValue + femaleValue;
            
            console.log('Gender validation - Male:', maleValue, 'Female:', femaleValue, 'Total:', total);
            
            return total > 0;
        }
        
        function showGenderError() {
            // Rimuovi errori precedenti
            $('.express-payment-error').remove();
            
            // Aggiungi messaggio di errore
            const errorMsg = $('<div class="express-payment-error" style="background: #dc3232; color: white; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 14px;">' +
                '⚠️ Compila prima i campi "Numero uomini" e "Numero donne" per utilizzare il pagamento rapido.' +
                '</div>');
            
            // Trova il miglior posto per mostrare l'errore
            if ($('.woocommerce-checkout-payment').length) {
                $('.woocommerce-checkout-payment').prepend(errorMsg);
            } else if ($('.checkout-payment').length) {
                $('.checkout-payment').prepend(errorMsg);
            } else {
                $('body').prepend(errorMsg);
            }
            
            // Scorri verso l'errore
            $('html, body').animate({
                scrollTop: errorMsg.offset().top - 100
            }, 500);
            
            // Rimuovi l'errore dopo 5 secondi
            setTimeout(() => errorMsg.fadeOut(), 5000);
        }
        
        function blockExpressButton(button) {
            const $button = $(button);
            
            console.log('Blocking button:', button);
            
            // Salva il gestore originale se non già fatto
            if (!$button.data('original-handlers-saved')) {
                const originalHandlers = $._data(button, 'events') || {};
                $button.data('original-handlers', originalHandlers);
                $button.data('original-handlers-saved', true);
            }
            
            // Rimuovi tutti i gestori eventi
            $button.off();
            
            // Aggiungi stile "disabilitato" con !important per sovrascrivere stili esistenti
            $button.css({
                'opacity': '0.4 !important',
                'pointer-events': 'none !important',
                'filter': 'grayscale(80%) !important',
                'cursor': 'not-allowed !important'
            });
            
            // Aggiungi classe per CSS personalizzato
            $button.addClass('skn-express-blocked');
            
            // Per elementi Stripe, blocca anche eventuali child elements
            if ($button.attr('id') && $button.attr('id').includes('wc-stripe-express-checkout-element')) {
                $button.find('*').addClass('skn-express-blocked');
            }
            
            // Aggiungi tooltip
            $button.attr('title', 'Compila prima i campi "Numero uomini" e "Numero donne"');
        }
        
        function enableExpressButton(button) {
            const $button = $(button);
            
            console.log('Enabling button:', button);
            
            // Ripristina stile
            $button.css({
                'opacity': '',
                'pointer-events': '',
                'filter': '',
                'cursor': ''
            });
            
            // Rimuovi classe
            $button.removeClass('skn-express-blocked');
            
            // Per elementi Stripe, rimuovi classe anche dai child elements
            if ($button.attr('id') && $button.attr('id').includes('wc-stripe-express-checkout-element')) {
                $button.find('*').removeClass('skn-express-blocked');
            }
            
            // Rimuovi tooltip
            $button.removeAttr('title');
            
            // Ripristina gestori originali
            const originalHandlers = $button.data('original-handlers');
            if (originalHandlers) {
                console.log('Button enabled, handlers should be restored by gateway');
            }
        }
        
        function updateExpressButtons() {
            const isValid = validateGenderFields();
            console.log('Updating express buttons - Valid:', isValid);
            
            let foundButtons = 0;
            
            expressSelectors.forEach(selector => {
                const buttons = $(selector);
                if (buttons.length > 0) {
                    console.log(`Found ${buttons.length} buttons with selector: ${selector}`);
                    foundButtons += buttons.length;
                    
                    buttons.each(function() {
                        console.log('Button element:', this);
                        console.log('Button classes:', this.className);
                        console.log('Button ID:', this.id);
                        
                        if (isValid) {
                            enableExpressButton(this);
                        } else {
                            blockExpressButton(this);
                        }
                    });
                }
            });
            
            console.log(`Total express buttons found: ${foundButtons}`);
            
            // Se non troviamo bottoni con i selettori, cerchiamo tutti gli elementi che potrebbero essere bottoni express
            if (foundButtons === 0) {
                console.log('No buttons found with standard selectors, searching for all possible express buttons...');
                
                // Cerca per testo/contenuto
                const possibleButtons = $('*').filter(function() {
                    const text = $(this).text().toLowerCase();
                    const id = (this.id || '').toLowerCase();
                    const classes = (this.className || '').toLowerCase();
                    
                    return text.includes('apple pay') || 
                           text.includes('google pay') || 
                           text.includes('pay with') ||
                           id.includes('apple') || 
                           id.includes('google') ||
                           classes.includes('apple') || 
                           classes.includes('google') ||
                           classes.includes('express') ||
                           classes.includes('payment-request');
                });
                
                console.log(`Found ${possibleButtons.length} possible express buttons:`, possibleButtons.toArray());
                
                possibleButtons.each(function() {
                    console.log('Possible button:', {
                        element: this,
                        text: $(this).text().trim(),
                        id: this.id,
                        classes: this.className
                    });
                    
                    if (!isValid) {
                        blockExpressButton(this);
                    }
                });
            }
        }
        
        // Intercetta click sui bottoni express
        function interceptExpressClicks() {
            // Intercetta con event delegation per catturare tutti i click
            $(document).on('click', '*', function(e) {
                const $element = $(this);
                const text = $element.text().toLowerCase();
                const id = (this.id || '').toLowerCase();
                const classes = (this.className || '').toLowerCase();
                
                // Controlla se è un bottone express
                const isExpressButton = 
                    text.includes('apple pay') || 
                    text.includes('google pay') || 
                    text.includes('pay with apple') || 
                    text.includes('pay with google') ||
                    id.includes('apple') || 
                    id.includes('google') ||
                    classes.includes('apple') || 
                    classes.includes('google') ||
                    classes.includes('express') ||
                    classes.includes('payment-request') ||
                    expressSelectors.some(selector => {
                        try {
                            return $element.is(selector);
                        } catch (e) {
                            return false;
                        }
                    });
                
                if (isExpressButton) {
                    console.log('Express button clicked:', {
                        element: this,
                        text: text,
                        id: id,
                        classes: classes
                    });
                    
                    if (!validateGenderFields()) {
                        console.log('Blocking express payment - gender fields not valid');
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        
                        showGenderError();
                        return false;
                    } else {
                        console.log('Express payment allowed - gender fields valid');
                    }
                }
            });
        }
        
        // Osserva cambiamenti nei campi gender
        function watchGenderFields() {
            $('[name="<?php echo esc_js($maleKey); ?>"], [name="<?php echo esc_js($femaleKey); ?>"]')
                .on('input change keyup', function() {
                    console.log('Gender field changed');
                    
                    // Salva automaticamente in sessione
                    saveGenderToSession();
                    
                    // Rimuovi errori esistenti
                    $('.express-payment-error').fadeOut();
                    // Aggiorna stato bottoni
                    setTimeout(updateExpressButtons, 100);
                });
        }
        
        // Funzione per salvare i dati gender in sessione via AJAX
        function saveGenderToSession() {
            const maleValue = $('[name="<?php echo esc_js($maleKey); ?>"]').val();
            const femaleValue = $('[name="<?php echo esc_js($femaleKey); ?>"]').val();
            
            if (maleValue || femaleValue) {
                console.log('Saving gender to session - Male:', maleValue, 'Female:', femaleValue);
                
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'save_gender_data',
                    '<?php echo esc_js($maleKey); ?>': maleValue,
                    '<?php echo esc_js($femaleKey); ?>': femaleValue
                }).done(function(response) {
                    console.log('Gender data saved to session:', response);
                }).fail(function() {
                    console.warn('Failed to save gender data to session');
                });
            }
        }
        
        // Observer per bottoni aggiunti dinamicamente
        function setupMutationObserver() {
            const observer = new MutationObserver(function(mutations) {
                let shouldUpdate = false;
                
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === Node.ELEMENT_NODE) {
                                expressSelectors.forEach(selector => {
                                    try {
                                        if (node.matches && node.matches(selector)) {
                                            shouldUpdate = true;
                                        } else if (node.querySelector && node.querySelector(selector)) {
                                            shouldUpdate = true;
                                        }
                                    } catch (e) {
                                        // Ignora errori di selettore
                                    }
                                });
                            }
                        });
                    }
                });
                
                if (shouldUpdate) {
                    console.log('New express buttons detected');
                    setTimeout(updateExpressButtons, 200);
                }
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
        
        // Inizializzazione
        function initialize() {
            console.log('Initializing express payment blocker');
            
            // Setup immediato
            updateExpressButtons();
            interceptExpressClicks();
            watchGenderFields();
            setupMutationObserver();
            
            // Salva immediatamente se i campi hanno già valori
            saveGenderToSession();
            
            // Re-check periodico per sicurezza
            setInterval(updateExpressButtons, 2000);
        }
        
        // Avvia quando il DOM è pronto
        if (document.readyState === 'loading') {
            $(document).ready(initialize);
        } else {
            initialize();
        }
        
        // Re-inizializza su eventi WooCommerce
        $(document.body).on('updated_checkout payment_method_selected', function() {
            console.log('Checkout updated, re-checking express buttons');
            setTimeout(updateExpressButtons, 500);
        });
    });
    </script>
    <?php
}

// Helper per verificare prodotti calendario
function skianet_termegest_has_calendar_products(): bool
{
    if (!function_exists('WC') || !WC()->cart) {
        return false;
    }
    
    foreach (WC()->cart->get_cart_contents() as $cartItem) {
        $idKey = SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM['id']['key'] ?? 'skn-custom-id';
        if (!empty($cartItem[$idKey])) {
            return true;
        }
    }
    
    return false;
}

// AJAX per salvare dati gender in sessione quando vengono compilati
add_action('wp_ajax_save_gender_data', 'skianet_termegest_save_gender_session');
add_action('wp_ajax_nopriv_save_gender_data', 'skianet_termegest_save_gender_session');
function skianet_termegest_save_gender_session(): void
{
    error_log("AJAX save_gender_session called");
    
    $maleKey = SKIANET_CUSTOM_BOOKING_PARAMS['male'] ?? '';
    $femaleKey = SKIANET_CUSTOM_BOOKING_PARAMS['female'] ?? '';
    
    if (!empty($_POST[$maleKey])) {
        $maleValue = (int) $_POST[$maleKey];
        WC()->session->set($maleKey, $maleValue);
        error_log("Saved male to session: {$maleValue}");
    }
    
    if (!empty($_POST[$femaleKey])) {
        $femaleValue = (int) $_POST[$femaleKey];
        WC()->session->set($femaleKey, $femaleValue);
        error_log("Saved female to session: {$femaleValue}");
    }
    
    wp_send_json_success(['message' => 'Gender data saved']);
}
    
// Modifica la tua funzione esistente per gestire express payments
add_action('woocommerce_checkout_create_order_line_item', 'skianet_termegest_express_gender_backup', PHP_INT_MAX + 1, 4);
function skianet_termegest_express_gender_backup(WC_Order_Item_Product $wcOrderItemProduct, string $cart_item_key, array $values, WC_Order $wcOrder): void
{
    error_log("=== EXPRESS GENDER BACKUP HOOK ===");
    
    // Verifica se ci sono prodotti calendario
    $idKey = SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM['id']['key'] ?? '';
    if (empty($values[$idKey])) {
        error_log("No calendar product ID found - skipping express gender backup");
        return;
    }
    
    $maleKey = SKIANET_CUSTOM_BOOKING_PARAMS['male'] ?? '';
    $femaleKey = SKIANET_CUSTOM_BOOKING_PARAMS['female'] ?? '';
    
    error_log("Calendar product found with ID: " . $values[$idKey]);
    error_log("Gender keys - Male: '{$maleKey}', Female: '{$femaleKey}'");
    error_log("POST data - Male: " . ($_POST[$maleKey] ?? 'empty') . ", Female: " . ($_POST[$femaleKey] ?? 'empty'));
    
    // Se i dati gender non sono in $_POST, recuperali dalla sessione
    $maleValue = 0;
    $femaleValue = 0;
    
    if (!empty($_POST[$maleKey])) {
        $maleValue = (int) $_POST[$maleKey];
        error_log("Male found in POST: {$maleValue}");
    } else {
        // Recupera dalla sessione
        $sessionMale = WC()->session->get($maleKey);
        if ($sessionMale) {
            $maleValue = (int) $sessionMale;
            error_log("Male retrieved from session: {$maleValue}");
        } else {
            error_log("No male data in session");
        }
    }
    
    if (!empty($_POST[$femaleKey])) {
        $femaleValue = (int) $_POST[$femaleKey];
        error_log("Female found in POST: {$femaleValue}");
    } else {
        // Recupera dalla sessione
        $sessionFemale = WC()->session->get($femaleKey);
        if ($sessionFemale) {
            $femaleValue = (int) $sessionFemale;
            error_log("Female retrieved from session: {$femaleValue}");
        } else {
            error_log("No female data in session");
        }
    }
    
    // Salva i dati gender se esistono
    if ($maleValue > 0) {
        $wcOrder->add_meta_data($maleKey, $maleValue, true);
        $wcOrderItemProduct->add_meta_data($maleKey, $maleValue, true);
        error_log("EXPRESS: Added male to order and item: {$maleValue}");
    }
    
    if ($femaleValue > 0) {
        $wcOrder->add_meta_data($femaleKey, $femaleValue, true);
        $wcOrderItemProduct->add_meta_data($femaleKey, $femaleValue, true);
        error_log("EXPRESS: Added female to order and item: {$femaleValue}");
    }
    
    error_log("=== END EXPRESS GENDER BACKUP ===");
}