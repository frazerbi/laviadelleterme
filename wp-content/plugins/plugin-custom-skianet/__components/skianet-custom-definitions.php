<?php

declare(strict_types=1);

if (! \defined('PLUGIN_SKIANET_FILE')) {
    exit();
}

const SKIANET_CUSTOM_BOOKING_BASE = 'skn-custom';
const SKIANET_CUSTOM_MYACC_BOOKING = 'skn-booking';

\define('SKIANET_CALENDAR_AJAX_EVENT_PARS', [
    'start' => __('Inizio', PLUGIN_SKIANET_TEXT_DOMAIN),
    'end' => __('Fine', PLUGIN_SKIANET_TEXT_DOMAIN),
    'location' => __('Sede', PLUGIN_SKIANET_TEXT_DOMAIN),
    'qty' => __('Quantità', PLUGIN_SKIANET_TEXT_DOMAIN),
]);

\define('SKIANET_BOOKING_AJAX_EVENT_PARS', [
    'id' => __('ID', PLUGIN_SKIANET_TEXT_DOMAIN),
    'location' => __('Sede', PLUGIN_SKIANET_TEXT_DOMAIN),
    'event' => __('Evento', PLUGIN_SKIANET_TEXT_DOMAIN),
    'qty' => __('Quantità', PLUGIN_SKIANET_TEXT_DOMAIN),
]);

\define('SKIANET_CUSTOM_BOOKING_PARAMS', [
    'male' => SKIANET_CUSTOM_BOOKING_BASE.'-sex-male',
    'female' => SKIANET_CUSTOM_BOOKING_BASE.'-sex-female',
    'qty' => SKIANET_CUSTOM_BOOKING_BASE.'-qty',
    'fromCalendar' => SKIANET_CUSTOM_BOOKING_BASE.'-from-calendar',
]);

\define('SKIANET_CUSTOM_BOOKING_PARAMS_NAMES', [
    SKIANET_CUSTOM_BOOKING_PARAMS['male'] => __('Numero uomini', PLUGIN_SKIANET_TEXT_DOMAIN),
    SKIANET_CUSTOM_BOOKING_PARAMS['female'] => __('Numero donne', PLUGIN_SKIANET_TEXT_DOMAIN),
]);

\define('SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM', [
    'id' => [
        'key' => SKIANET_CUSTOM_BOOKING_BASE.'-id',
        'value' => SKIANET_BOOKING_AJAX_EVENT_PARS['id'],
    ],
    'location' => [
        'key' => SKIANET_CUSTOM_BOOKING_BASE.'-location',
        'value' => SKIANET_BOOKING_AJAX_EVENT_PARS['location'],
    ],
    'event' => [
        'key' => SKIANET_CUSTOM_BOOKING_BASE.'-event',
        'value' => SKIANET_BOOKING_AJAX_EVENT_PARS['event'],
    ],
    'qty' => [
        'key' => SKIANET_CUSTOM_BOOKING_BASE.'-qty',
        'value' => SKIANET_BOOKING_AJAX_EVENT_PARS['qty'],
    ],
]);
