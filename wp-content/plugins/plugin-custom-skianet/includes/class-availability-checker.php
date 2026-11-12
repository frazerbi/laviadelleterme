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


        // Step 1: Crea array con tutti i giorni dei 2 mesi
        $all_dates = $this->get_all_dates_for_two_months();
        
        
        // Step 2: Inizializza risultati con tutti i giorni a false
        $results = array();
        foreach ($all_dates as $date) {
            $results[$date] = false;
        }
        
        // Step 3: Per ogni mese, chiama l'API
        $months_to_check = $this->get_months_to_check();
        
        foreach ($months_to_check as $period) {
            $month = $period['month'];
            $year = $period['year'];

            foreach ($this->get_categories_for_month($month) as $cat) {
                // Chiama API TermeGest per il mese
                $dispArr = skianet_termegest_get_disponibilita($month, $year, $cat, $encrypted_location);

                if (!empty($dispArr)) {
                    $this->merge_availability($results, $dispArr, $cat);
                }

                // Pausa fra le chiamate
                usleep(500000); // 0.5 secondi
            }
        }

        // Step 5: Salva nel file JSON
        $this->save_json_file($location, $results);
    }

    /**
     * Categorie da chiedere a TermeGest per un mese.
     *
     * Ogni giorno va giudicato sulla categoria che il form accettera' quel giorno
     * (Booking_Handler::validate_category()): p2 fuori dal periodo natalizio, pm dentro.
     * Dicembre e gennaio contengono entrambi, quindi si chiedono tutte e due; prima si
     * chiedeva pm per tutto il mese, e i giorni fuori dal periodo (1-23 dicembre,
     * 7-31 gennaio) risultavano aperti o chiusi secondo una categoria che il form scarta.
     */
    private function get_categories_for_month($month) {
        return ($month == 12 || $month == 1) ? array('p2', 'pm') : array('p2');
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

            // Giorno di competenza dell'altra categoria
            if (Booking_Redirect::is_christmas_period($date_key) !== $want_christmas) {
                continue;
            }

            // Se questo giorno è già disponibile, skip
            if (isset($results[$date_key]) && $results[$date_key] === true) {
                continue;
            }

            // Verifica se questa fascia ha disponibilità
            if (isset($dispo->disponibili) && (int)$dispo->disponibili > 0) {
                $results[$date_key] = true;
            }
        }
    }

    /**
     * Ottieni tutti i giorni dei prossimi 2 mesi
     */
    private function get_all_dates_for_two_months() {
        $dates = array();
        $wp_timezone = wp_timezone();

        // Primo giorno del mese corrente
        $start = new DateTime('first day of this month', $wp_timezone);
        
        // Ultimo giorno del mese successivo
        $end = new DateTime('last day of next month', $wp_timezone);
        
        $current = clone $start;
        
        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }
        
        return $dates;
    }

    /**
     * Ottieni i mesi da controllare
     */
    private function get_months_to_check() {
        $wp_timezone = wp_timezone();

        $current_date = new DateTime('first day of this month', $wp_timezone);
        $current_month = (int) $current_date->format('n');
        $current_year = (int) $current_date->format('Y');


        $next_date = new DateTime('first day of next month', $wp_timezone);
        $next_month = (int) $next_date->format('n');
        $next_year = (int) $next_date->format('Y');


        return array(
            array('month' => $current_month, 'year' => $current_year),
            array('month' => $next_month, 'year' => $next_year)
        );
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