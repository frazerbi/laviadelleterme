<?php

declare(strict_types=1);

if (! \defined('PLUGIN_SKIANET_FILE')) {
    exit();
}

return static function (array $pars): void {
    /**
     * @var string[] $pars
     */
    if (empty($pars['id']) || empty($pars['location']) || empty($pars['dialog-id']) || empty($pars['event']['start'])) {
        return;
    }

    try {
        $dateTime = new DateTime($pars['event']['start']);
    } catch (Throwable) {
        return;
    }

    $date = $dateTime->format('d/m/Y');

    $hour = $dateTime->format('H:i');

    $title = \sprintf(__('Prenota a %s per il giorno %s alle ore %s', PLUGIN_SKIANET_TEXT_DOMAIN), $pars['location'], $date, $hour);

    // $post = get_page_by_path('skianet-form-disponibilita', OBJECT, 'elementor_library');
    $args = [
        'post_type' => 'elementor_library',
        'name' => 'skianet-form-disponibilita',
        'posts_per_page' => 1
    ];
    $query = new WP_Query($args);
    $post = $query->posts[0] ?? null;
    if (! $post instanceof WP_Post) {
        return;
    }

    if (($pars['from'] ?? '') === 'myaccount') {
        add_filter('woocommerce_is_account_page', '__return_true', \PHP_INT_MAX);
    }
   
    ?>
    <div class="dialog" id="<?php echo $pars['dialog-id']; ?> ">
        <?php 
            echo do_shortcode('[elementor-template id="'.$post->ID.'"]'); ?>
    </div>
    <?php
};
