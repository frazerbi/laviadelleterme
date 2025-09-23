<?php

declare(strict_types=1);

use TermeGest\Type\Disponibilita;

if (! \defined('PLUGIN_SKIANET_FILE')) {
    exit();
}

add_shortcode(SKIANET_DISPONIBILITA_ELEMENT_ID, 'skianet_termegest_calendar_disponibilita');

function skianet_termegest_calendar_disponibilita(): string
{
    ob_start();

    echo '<div id="'.SKIANET_DISPONIBILITA_CONTAIN_ID.'">';
    echo '<div id="'.SKIANET_DISPONIBILITA_ELEMENT_ID.'"></div>';
    echo '<div class="calendar-loader text-center">';
    echo '<i class="fa fa-spin fa-spinner fa-5x" aria-hidden="true"></i>';
    echo '</div>';
    echo '</div>';

    return (string) ob_get_clean();
}

/**
 * @return array{start: string, end: string, location: string}
 */
function skianet_termegest_calendar_get_form_parameters(): array
{
    $pars = array_map('sanitize_text_field', wp_unslash($_POST));
    if (empty($pars)) {
        $pars = array_map('sanitize_text_field', wp_unslash($_GET));
    }

    foreach (array_keys(SKIANET_CALENDAR_AJAX_EVENT_PARS) as $key) {
        if (empty($pars[$key])) {
            return [];
        }
    }

    return array_filter(array_map('trim', wc_clean($pars)));
}

add_action('wp_ajax_'.SKIANET_ACTION_GET_DISPONIBILITA, 'skianet_termegest_calendar_get_disponibilita', \PHP_INT_MAX);
add_action('wp_ajax_nopriv_'.SKIANET_ACTION_GET_DISPONIBILITA, 'skianet_termegest_calendar_get_disponibilita', \PHP_INT_MAX);
function skianet_termegest_calendar_get_disponibilita(): void
{
    $pars = skianet_termegest_calendar_get_form_parameters();

    if (empty($pars['start']) || empty($pars['end']) || empty($pars['location']) || empty($pars['qty'])) {
        wp_send_json([]);
    }

    $start = $pars['start'];
    $end = $pars['end'];
    $location = $pars['location'];
    $qty = (int) $pars['qty'];

    $monthStart = (int) date('m', strtotime($start));
    $yearStart = (int) date('Y', strtotime($start));
    $monthEnd = (int) date('m', strtotime($end));
    $yearEnd = (int) date('Y', strtotime($end));
    $thisMonth = (int) date('m');

    if ($monthStart < $thisMonth) {
        $monthStart = $thisMonth;
    }

    if ($yearEnd > $yearStart) {
        $monthEnd += 12;
    }

    $month = $monthStart;
    $year = $yearStart;

    $json = [];
    $saveMinPrices = [];
    $now = null;

    try {
        $now = new DateTime('now', new DateTimeZone(wp_timezone_string()));
    } catch (Throwable) {
        wp_send_json([]);
    }

    do {

        $dispArr = skianet_termegest_get_disponibilita($month, $year, 'p2', $location);

        foreach ($dispArr as $disp) {
            /* @var Disponibilita $disp */
            if (! $disp instanceof Disponibilita) {
                continue;
            }

            if ($now->diff($disp->getDateTime())->invert === 1) {
                continue;
            }

            $products = termegest_get_available_products_from_options($disp->getDateTime());

            $minPrice = array_reduce($products, static function (float $carry, int $productId) use (&$saveMinPrices): float {
                if (! empty($saveMinPrices[$productId])) {
                    return $saveMinPrices[$productId];
                }

                $product = wc_get_product($productId);
                if (! $product instanceof WC_Product) {
                    return $carry;
                }

                $price = (float) $product->get_price();

                $saveMinPrices[$productId] = $price;

                return min($price, $carry);
            }, \PHP_INT_MAX);

            if ($minPrice === \PHP_INT_MAX) {
                continue;
            }

            $json[] = [
                'id' => $disp->iddispo,
                'title' => \sprintf('<span class="price-prefix">%s</span> %s', __('Da', PLUGIN_SKIANET_TEXT_DOMAIN), wc_price($minPrice, array('decimals' => 0)) ),
                'start' => $disp->getDateTime()
                    ->format('c'),
                'end' => $disp->getDateTime()
                    ->add(new DateInterval('PT1H'))
                    ->format('c'),
                'allDay' => false,
                'interactive' => false,
                'editable' => false,
                'overlap' => false,
                'booked' => $disp->prenotati,
                'available' => $disp->getAvailable(),
            ];
        }

        $month++;
        if ($month > 12) {
            $month = 1;
            $year++;
            $monthEnd -= 12;
        }
    } while ($month <= $monthEnd && $year <= $yearEnd);

    wp_send_json($json);
}
