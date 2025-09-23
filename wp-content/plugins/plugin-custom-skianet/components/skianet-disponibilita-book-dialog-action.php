<?php

declare(strict_types=1);

use ElementorPro\Modules\Forms\Classes\Action_Base;
use ElementorPro\Modules\Forms\Classes\Ajax_Handler;
use ElementorPro\Modules\Forms\Classes\Form_Record;
use ElementorPro\Modules\Forms\Widgets\Form;
use TermeGest\Type\TermeGestLogger;

class SkianetBookCustomAction extends Action_Base
{
    /**
     * Get action label.
     *
     * @since 1.0.0
     */
    public function get_label(): string
    {
        return __('Skianet Custom - Prenota Termegest', PLUGIN_SKIANET_TEXT_DOMAIN);
    }

    /**
     * Get action name.
     *
     * @since 1.0.0
     */
    public function get_name(): string
    {
        return 'skn_custom_book_termegest';
    }

    /**
     * On export.
     * This action has no fields to clear when exporting.
     *
     * @param mixed $element
     *
     * @since 1.0.0
     */
    public function on_export($element): void {}

    /**
     * Register action controls.
     * This action has no input fields to the form widget.
     *
     * @param Form $form
     *
     * @since 1.0.0
     */
    public function register_settings_section($form): void {}

    /**
     * Run action.
     *
     * @param Form_Record $record
     * @param Ajax_Handler $ajax_handler
     *
     * @since 1.0.0
     */
    public function run($record, $ajax_handler): void
    {
        $data = array_filter(array_map('trim', wc_clean($record->get('sent_data'))));

        $termeGestLogger = TermeGestLogger::getInstance();

        $pars = [];

        foreach (SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM as $key => $item) {
            if (empty($data[$key])) {
                $msg = \sprintf(__('Errore durante la prenotazione: campo %s non presente', PLUGIN_SKIANET_TEXT_DOMAIN), $item['value']);
                $termeGestLogger->send($msg);
                $termeGestLogger->flushLog();
                $ajax_handler->add_error_message($msg)
                    ->send();

                return;
            }

            $pars[$key] = $data[$key];

            if ($key === 'event') {
                try {
                    $pars[$key] = json_decode(html_entity_decode($pars[$key], \ENT_QUOTES, 'UTF-8'), true, 512, \JSON_THROW_ON_ERROR);
                    if (! empty($pars[$key]['start'])) {
                        $pars[$key] = (new DateTimeImmutable($pars[$key]['start']))->format('d/m/Y H:i');
                    } else {
                        throw new Exception('Evento non valido');
                    }
                } catch (Throwable) {
                    $msg = __('Errore durante la prenotazione: evento non valido', PLUGIN_SKIANET_TEXT_DOMAIN);
                    $termeGestLogger->send($msg);
                    $termeGestLogger->flushLog();
                    $ajax_handler->add_error_message($msg)
                        ->send();

                    return;
                }
            }
        }

        $categoria = skianet_termegest_get_category_from_ticket($data['code']);
        if (empty($categoria)) {
            $msg = __('Errore durante la prenotazione: categoria non presente', PLUGIN_SKIANET_TEXT_DOMAIN);
            $termeGestLogger->send($msg);
            $termeGestLogger->flushLog();
            $ajax_handler->add_error_message($msg)
                ->send();

            return;
        }

        $location = skianet_termegest_encrypt($pars['location'] ?? '');

        if (skianet_termegest_get_disponibilitaById((int) $pars['id'], $location, $categoria) < (int) $pars['qty']) {

            $cartUrl = \sprintf('<a href="%s">%s</a>', wc_get_cart_url(), __('carrello', PLUGIN_SKIANET_TEXT_DOMAIN));
            $bookingUrl = \sprintf('<a href="%s">%s</a>', get_permalink(get_page_by_path('disponibilita')), __('disponibilità', PLUGIN_SKIANET_TEXT_DOMAIN));

            $msg = \sprintf(
                __(
                    'Disponibilità non sufficiente per la sede <strong>%s</strong> per la data <strong>%s</strong>.<br>Si prega di tornare al %s, togliere il prodotto dal carrello e selezionare un altro orario alla pagina %s',
                    PLUGIN_SKIANET_TEXT_DOMAIN
                ),
                $pars['location'],
                $pars['event'],
                $cartUrl,
                $bookingUrl
            );
            $termeGestLogger->send($msg);
            $termeGestLogger->flushLog();
            $ajax_handler->add_error_message($msg)
                ->send();

            return;
        }

        try {
            $response = skianet_termegest_set_prenotazione(
                (int) $pars['id'],
                $data['code'],
                $data['lastname'],
                $data['name'],
                $data['phone'],
                (string) $data['message'],
                $data['city'],
                ((int) $data['sex']) === 1,
                $data['email'],
                $categoria,
                $location,
            );
        } catch (Throwable $throwable) {
            $msg = __('Errore durante la prenotazione', PLUGIN_SKIANET_TEXT_DOMAIN).': '.$throwable->getMessage();
            $termeGestLogger->send($msg);
            $termeGestLogger->flushLog();
            $ajax_handler->add_error_message($msg)
                ->send();

            return;
        }

        if (! isset($response['status']) || $response['status'] === false) {
            $msg = __('Errore durante la prenotazione', PLUGIN_SKIANET_TEXT_DOMAIN);
            if (isset($response['message'])) {
                $msg .= ': '.$response['message'];
            }

            $termeGestLogger->send($msg);
            $termeGestLogger->flushLog();
            $ajax_handler->add_error_message($msg)
                ->send();

            return;
        }

        try {
            $this->manageTicket($data['code'], $data['name'], $data['email']);
        } catch (Throwable $throwable) {
            $msg = __('Errore durante la prenotazione', PLUGIN_SKIANET_TEXT_DOMAIN).': '.$throwable->getMessage();
            $termeGestLogger->send($msg);
            $termeGestLogger->flushLog();
            $ajax_handler->add_error_message($msg)
                ->send();
        }

        $ajax_handler->add_success_message(__('Prenotazione effettuata con successo', PLUGIN_SKIANET_TEXT_DOMAIN))
            ->send();
    }

    /**
     * @throws Exception
     */
    protected function manageTicket(string $code, string $name, string $email): void
    {
        global $wpdb;

        $tickets = $wpdb->get_results($wpdb->prepare('SELECT * FROM `'.$wpdb->wc_ld_license_codes.'` WHERE `license_code1` = %s;', $code));
        if (\count($tickets) !== 1) {
            throw new Exception(__('Ticket non trovato', PLUGIN_SKIANET_TEXT_DOMAIN));
        }

        $ticket = array_shift($tickets);

        $product = wc_get_product($ticket->product_id);
        if (! $product instanceof WC_Product) {
            throw new Exception(__('Prodotto non trovato', PLUGIN_SKIANET_TEXT_DOMAIN));
        }

        $price = (float) $product->get_price();

        $response = skianet_termegest_set_venduto($code, $price, $name, $email);
        if (empty($response)) {
            throw new Exception(__('Errore durante l\'attivazione del ticket', PLUGIN_SKIANET_TEXT_DOMAIN));
        }

        $termeGestLogger = TermeGestLogger::getInstance();
        $termeGestLogger->send('Ticket venduto: '.$response);
        $termeGestLogger->flushLog();

        $result = $wpdb->update(
            $wpdb->wc_ld_license_codes,
            ['license_status' => 1, 'sold_date' => current_time('mysql')],
            ['id' => $ticket->id]
        );

        if (empty($result)) {
            throw new Exception(__('Errore durante l\'aggiornamento del ticket', PLUGIN_SKIANET_TEXT_DOMAIN));
        }
    }
}
