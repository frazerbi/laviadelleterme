<?php

declare(strict_types=1);

use Elementor\Plugin;

add_action('init', 'skianet_termegest_add_custom_booking_endpoint');
function skianet_termegest_add_custom_booking_endpoint(): void
{
    add_rewrite_endpoint(SKIANET_CUSTOM_MYACC_BOOKING, EP_ALL);
    flush_rewrite_rules();
}

add_filter('elementor/widget/render_content', 'skianet_termegest_custom_booking_render_content', \PHP_INT_MAX);
function skianet_termegest_custom_booking_render_content(string $widget_content): string
{
    global $wp_query;
    if (!wp_doing_ajax() && is_account_page() && isset($wp_query->query_vars[SKIANET_CUSTOM_MYACC_BOOKING])) {

        return str_ireplace('e-my-account-tab__ "', 'e-my-account-tab__dashboard--custom "', $widget_content);
    }

    return $widget_content;
}

add_filter('woocommerce_account_menu_items', 'skianet_termegest_add_custom_booking_link_my_account', \PHP_INT_MAX);
/**
 * @param string[] $items
 * @return string[]
 */
function skianet_termegest_add_custom_booking_link_my_account(array $items): array
{
    $position = 2;
    $itemToAdd = [SKIANET_CUSTOM_MYACC_BOOKING => __('Prenotazioni', PLUGIN_SKIANET_TEXT_DOMAIN)];

    return \array_slice($items, 0, $position, true) + $itemToAdd + \array_slice($items, $position, null, true);
}

add_action('woocommerce_account_'.SKIANET_CUSTOM_MYACC_BOOKING.'_endpoint', 'skianet_termegest_custom_booking_content', \PHP_INT_MAX);
function skianet_termegest_custom_booking_content(): void
{
    // $post = get_page_by_path('disponibilita');

    // get_page_by_path è deprecata, utilizza get_posts() con i parametri appropriati
    $posts = get_posts([
        'name'      => 'form_disponibilita_calendario',
        'post_type' => 'elementor_library',
        'numberposts' => 1
    ]);
    
    if (empty($posts)) {
        return;
    }
    
    $post = $posts[0];

    echo '<div class="'.SKIANET_CUSTOM_MYACC_BOOKING.'">';
    echo Plugin::instance()->frontend->get_builder_content($post->ID, true);
    echo '</div>';
}
