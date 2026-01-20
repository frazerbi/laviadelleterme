<?php

declare(strict_types=1);

if (! \defined('PLUGIN_SKIANET_FILE')) {
    exit();
}

add_shortcode('skn-termegest-get-location-options', 'skianet_termegest_get_location_options');
function skianet_termegest_get_location_options(): string
{
    ob_start();

    $locations = get_field('sedi', 'options');
    if (! empty($locations)) {
        $locations = array_map(static fn (array $location) => $location['sede'] ?? '', $locations);
    }
    
    $locations = array_filter(array_map('trim', wc_clean($locations)));

    echo __('Scegli sede', PLUGIN_SKIANET_TEXT_DOMAIN).'|'.\PHP_EOL;

    foreach ($locations as $location) {
        echo $location.'|'.skianet_termegest_encrypt($location).\PHP_EOL;
    }

    return trim(ob_get_clean());
}

function skianet_termegest_encrypt(string $location): string
{
    $key = 'konsb1351f7kk3x7rz2phunuje1h80kk';

    $iv = mb_substr(str_shuffle(md5(microtime())), 0, 16);

    $encrypted = openssl_encrypt($location, 'AES-256-CBC', $key, \OPENSSL_RAW_DATA, $iv);

    if ($encrypted === false) {
        return '';
    }
    
    return $iv.base64_encode($encrypted);
}
