<?php

declare(strict_types=1);

use Elementor\Widget_Base;

if (! \defined('PLUGIN_SKIANET_FILE')) {
    exit();
}

add_shortcode('skn-disponibilita-get-states-options', 'skianet_termegest_calendar_get_states_options');
function skianet_termegest_calendar_get_states_options(): string
{
    ob_start();

    echo __('Scegli provincia', PLUGIN_SKIANET_TEXT_DOMAIN).'|'.\PHP_EOL;

    foreach (WC()->countries->get_states('IT') as $key => $value) {
        echo $value.'|'.$key.\PHP_EOL;
    }

    return trim(ob_get_clean());
}

add_action('wp_ajax_'.SKIANET_ACTION_GET_BOOK_DIALOG, 'skianet_termegest_calendar_get_book_dialog', \PHP_INT_MAX);
add_action('wp_ajax_nopriv_'.SKIANET_ACTION_GET_BOOK_DIALOG, 'skianet_termegest_calendar_get_book_dialog', \PHP_INT_MAX);
function skianet_termegest_calendar_get_book_dialog(): void
{
    $pars = skianet_termegest_booking_get_form_parameters();

    if (empty($pars)) {
        wp_send_json(['error' => 'Missing parameters']);
    }

    try {
        $pars['event'] = json_decode(html_entity_decode($pars['event'], \ENT_QUOTES, 'UTF-8'), true, 512, \JSON_THROW_ON_ERROR);
    } catch (Throwable $throwable) {
        wp_send_json(['error' => 'Invalid event', 'message' => $throwable->getMessage()]);
    }

    $pars['dialog-id'] = SKIANET_ACTION_GET_BOOK_DIALOG.'-'.$pars['id'];

    ob_start();

    $modal = require PLUGIN_SKIANET_PATH.'/components/skianet-disponibilita-book-dialog.php';

    echo $modal($pars);

    $html = ob_get_clean();

    if (empty($html)) {
        wp_send_json(['error' => 'Empty modal']);
    }

    wp_send_json(['dialog' => trim($html)]);
}

/**
 * @return array{id: string, location: string, event: string}
 */
function skianet_termegest_booking_get_form_parameters(): array
{
    $pars = array_map('sanitize_text_field', wp_unslash($_POST));
    if (empty($pars)) {
        $pars = array_map('sanitize_text_field', wp_unslash($_GET));
    }

    foreach (SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM as $key => $item) {
        if (! empty($pars[$item['key']])) {
            $pars[$key] = $pars[$item['key']];
        }
    }

    foreach (array_keys(SKIANET_BOOKING_AJAX_EVENT_PARS) as $key) {
        if (empty($pars[$key])) {
            return [];
        }
    }

    return array_filter(array_map('trim', wc_clean($pars)));
}

add_filter('elementor/query/query_args', 'skianet_termegest_change_products_slider_query_elementor', \PHP_INT_MAX, 2);
function skianet_termegest_change_products_slider_query_elementor(array $query_args, Widget_Base $widget): array
{
    if ($widget->get_name() !== 'loop-carousel' || $widget->get_current_skin_id() !== 'product') {
        return $query_args;
    }

    $pars = skianet_termegest_booking_get_form_parameters();
    if (empty($pars['action']) || $pars['action'] !== SKIANET_ACTION_GET_BOOK_DIALOG) {
        return $query_args;
    }

    try {
        $pars['event'] = json_decode(html_entity_decode((string) $pars['event'], \ENT_QUOTES, 'UTF-8'), true, 512, \JSON_THROW_ON_ERROR);
        $dateTime = new DateTime($pars['event']['start']);
    } catch (Throwable) {
        return $query_args;
    }

    $products = termegest_get_available_products_from_options($dateTime);

    $query_args['post__in'] = $products;

    return $query_args;
}

/**
 * @return int[]
 */
function termegest_get_available_products_from_options(DateTimeInterface $day): array
{
    $products = get_field('prodotti', 'options');
    $actualDay = (int) $day->format('N');

    if (empty($products)) {
        return [];
    }

    return array_filter(
        array_map(
            'intval',
            array_map(static function (array $product) use ($day, $actualDay): int {
                $weekDaysOn = array_map('intval', $product['attivo_giorni_settimana'] ?? []);
                if (\in_array(0, $weekDaysOn, true)) {
                    $weekDaysOn = range(1, 7);
                }

                try {
                    $dateFrom = DateTime::createFromFormat('d/m/Y', $product['data_inizio_visualizzazione'] ?? '')
                        ->setTime(0, 0);
                    $dateTo = DateTime::createFromFormat('d/m/Y', $product['data_fine_visualizzazione'] ?? '')
                        ->setTime(23, 59, 59);
                } catch (Throwable) {
                    return 0;
                }

                if ($day < $dateFrom || $day > $dateTo || ! \in_array($actualDay, $weekDaysOn, true)) {
                    return 0;
                }

                return $product['prodotto'] instanceof WP_Post ? $product['prodotto']->ID : 0;
            }, $products)
        )
    );
}

add_action('woocommerce_before_add_to_cart_button', 'skianet_termegest_booking_add_to_cart_button', \PHP_INT_MAX);
function skianet_termegest_booking_add_to_cart_button(): void
{
    global $product;

    if (! $product instanceof WC_Product || $product->get_type() !== 'simple') {
        return;
    }

    $pars = skianet_termegest_booking_get_form_parameters();
    if (empty($pars['action']) || $pars['action'] !== SKIANET_ACTION_GET_BOOK_DIALOG) {
        return;
    }

    woocommerce_template_single_excerpt();
    woocommerce_template_single_price();
}
