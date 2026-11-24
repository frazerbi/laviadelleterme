<?php
/**
 * Verifica e salva disponibilità da TermeGest in JSON
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

class Availability_Checker {

    /**
     * Istanza singleton
     */
    private static $instance = null;

    /**
     * Path completo directory JSON
     */
    private $json_path;

    /**
     * Ottieni l'istanza della classe
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Costruttore
     */
    private function __construct() {

        $this->json_path = plugin_dir_path(PLUGIN_SKIANET_FILE) . 'assets/data';

        // La directory viene creata da save_json_file(), l'unico punto che ci
        // scrive: qui costava uno stat del filesystem su ogni richiesta.

        $this->init_hooks();
    }

    /**
     * Inizializza gli hooks
     */
    private function init_hooks() {
        // Cron giornaliero per aggiornare disponibilità
        add_action('termegest_check_availability', array($this, 'check_all_locations'));
        
        // Rete di sicurezza per l'evento cron: register_activation_hook non
        // scatta quando il plugin viene aggiornato via FTP senza riattivarlo.
        // Solo in backoffice, non su ogni page load del front-end.
        add_action('admin_init', array($this, 'maybe_schedule_cron'));
    }

    /**
     * Registra l'evento cron giornaliero se manca
     */
    public function maybe_schedule_cron() {
        if (!wp_next_scheduled('termegest_check_availability')) {
            wp_schedule_event(time(), 'daily', 'termegest_check_availability');
        }
    }

    /**
     * Controlla disponibilità per tutte le location
     */
    public function check_all_locations() {
                
        // Verifica che Booking_Handler sia caricata
        if (!class_exists('Booking_Handler')) {
            return;
        }
        
        // Verifica che il metodo esista
        if (!method_exists('Booking_Handler', 'get_locations_to_encrypt')) {
            return;
        }

        // Prendi le location da Booking_Handler (unica fonte)
        $locations = Booking_Handler::get_locations_to_encrypt();
        
        if (empty($locations)) {
            return;
        }

        foreach ($locations as $slug => $location_name) {
            $this->check_location_availability($location_name);
        }

    }

    /**
     * Controlla disponibilità per una location (mese corrente + successivo)
     */
    private function check_location_availability($location) {

        if (!class_exists('TermeGest_Encryption')) {
            return;
        }

        if (!function_exists('skianet_termegest_get_disponibilita') || !class_exists('Booking_Redirect')) {
            return;
        }


        // Cripta la location PRIMA di usarla
        $encryption = TermeGest_Encryption::get_instance();
        $encrypted_location = $encryption->encrypt($location);
        
        if (empty($encrypted_location)) {
            return;
        }


        // Step 1: tutti i giorni del calendario, inizializzati a false
        $all_dates = $this->get_calendar_dates();
        $results = array_fill_keys($all_dates, false);

        // Step 2: per ogni mese toccato dal calendario, una chiamata per categoria
        foreach ($this->get_months_to_check($all_dates) as $period) {
            $month = $period['month'];
            $year = $period['year'];

            foreach ($period['categories'] as $cat) {
                // Chiama API TermeGest per il mese
                $dispArr = skianet_termegest_get_disponibilita($month, $year, $cat, $encrypted_location);

                if (!empty($dispArr)) {
                    $this->merge_availability($results, $dispArr, $cat);
                }

                // Pausa fra le chiamate
                usleep(500000); // 0.5 secondi
            }
        }

        // Step 3: Salva nel file JSON
        $this->save_json_file($location, $results);
    }

    /**
     * Segna come disponibili i giorni con posti liberi nella risposta di una categoria,
     * contando solo i giorni in cui quella categoria e' quella giusta (pm nel periodo
     * natalizio, p2 fuori).
     */
    private function merge_availability(array &$results, array $dispArr, $cat) {
        $want_christmas = ($cat === 'pm');

        foreach ($dispArr as $dispo) {
            if (!isset($dispo->data)) {
                continue;
            }

            // Parse data (formato: "2025-12-09 00:00:00")
            $date_obj = DateTime::createFromFormat('Y-m-d H:i:s', $dispo->data);
            if (!$date_obj) {
                continue;
            }

            $date_key = $date_obj->format('Y-m-d'); // Es: "2025-12-09"

            // Fuori dal calendario: a novembre gennaio si chiede intero ma si mostra fino al 6
            if (!array_key_exists($date_key, $results)) {
                continue;
            }

            // Giorno di competenza dell'altra categoria
            if (Booking_Redirect::is_christmas_period($date_key) !== $want_christmas) {
                continue;
            }

            // Se questo giorno è già disponibile, skip
            if ($results[$date_key] === true) {
                continue;
            }

            // Verifica se questa fascia ha disponibilità
            if (isset($dispo->disponibili) && (int)$dispo->disponibili > 0) {
                $results[$date_key] = true;
            }
        }
    }

    /**
     * Giorni del calendario: dal primo del mese corrente all'ultimo del successivo, come il
     * form (initCalendar() in booking-form.js). Se quell'ultimo giorno cade nel periodo
     * natalizio, cioe' a novembre, si arriva fino alla fine del periodo (6 gennaio): il
     * periodo si vende intero, e senza la proroga 1-6 gennaio diventavano prenotabili solo
     * dal 1° dicembre (chiesto dal cliente il 2026-11-24).
     */
    private function get_calendar_dates() {
        $wp_timezone = wp_timezone();

        $current = new DateTime('first day of this month midnight', $wp_timezone);
        $end = new DateTime('last day of next month midnight', $wp_timezone);

        if (Booking_Redirect::is_christmas_period($end->format('Y-m-d'))) {
            while (Booking_Redirect::is_christmas_period((clone $end)->modify('+1 day')->format('Y-m-d'))) {
                $end->modify('+1 day');
            }
        }

        $dates = array();
        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }

        return $dates;
    }

    /**
     * Mesi da chiedere a TermeGest (l'API lavora per mese) e, per ognuno, le categorie che
     * servono ai suoi giorni del calendario.
     *
     * Ogni giorno va giudicato sulla categoria che il form accettera' quel giorno
     * (Booking_Handler::validate_category()): pm nel periodo natalizio, p2 fuori. Un mese a
     * cavallo (dicembre; gennaio quando e' il mese corrente) si chiede quindi con tutte e
     * due; il gennaio della proroga di novembre ha nel calendario solo 1-6, e basta pm.
     * Fino al 2026-11-12 si chiedeva pm per tutto dicembre e gennaio, e i giorni fuori dal
     * periodo risultavano aperti o chiusi secondo una categoria che il form scarta.
     */
    private function get_months_to_check(array $dates) {
        $months = array();

        foreach ($dates as $date) {
            $key = substr($date, 0, 7); // "2026-12"

            if (!isset($months[$key])) {
                $months[$key] = array(
                    'month' => (int) substr($date, 5, 2),
                    'year' => (int) substr($date, 0, 4),
                    'categories' => array(),
                );
            }

            $cat = Booking_Redirect::is_christmas_period($date) ? 'pm' : 'p2';
            $months[$key]['categories'][$cat] = $cat;
        }

        foreach ($months as &$period) {
            $period['categories'] = array_values($period['categories']);
        }
        unset($period);

        return array_values($months);
    }

    /**
     * Salva disponibilità in file JSON
     */
    private function save_json_file($location, $data) {
        if (!is_dir($this->json_path)) {
            wp_mkdir_p($this->json_path);
        }

        $filename = $this->get_json_filename($location);
        $filepath = $this->json_path . '/' . $filename;

        $json_data = array(
            'location' => $location,
            'generated_at' => current_time('mysql'),
            'availability' => $data
        );

        // Scrittura atomica: senza il file temporaneo + rename, fra l'unlink e la fine
        // della file_put_contents il form JS riceve un 404 (o un JSON troncato) e
        // disabilita tutte le date del calendario.
        $tmp_path = $filepath . '.tmp';

        $result = file_put_contents(
            $tmp_path,
            json_encode($json_data, JSON_PRETTY_PRINT),
            LOCK_EX
        );

        if ($result === false) {
            return;
        }

        if (!rename($tmp_path, $filepath)) {
            unlink($tmp_path);
            return;
        }

        clearstatcache(true, $filepath);
    }

    /**
     * Genera nome file JSON
     */
    private function get_json_filename($location) {
        $slug = sanitize_title($location);
        return "availability-{$slug}.json";
    }

    /**
     * Deactivation hook
     */
    public static function deactivate() {
        wp_clear_scheduled_hook('termegest_check_availability');
    }
}