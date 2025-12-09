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
     * Directory per i file JSON
     */
    private const JSON_DIR = 'termegest-availability';

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

        $this->json_path = plugin_dir_path(PLUGIN_SKIANET_FILE) . '/assets/data';

        // Crea directory se non esiste
        if (!file_exists($this->json_path)) {
            wp_mkdir_p($this->json_path);
        }

        $this->init_hooks();
    }

    /**
     * Inizializza gli hooks
     */
    private function init_hooks() {
        // Cron giornaliero per aggiornare disponibilità
        add_action('termegest_check_availability', array($this, 'check_all_locations'));

        // Registra cron se non esiste (controlla ad ogni page load)
        if (!wp_next_scheduled('termegest_check_availability')) {
            wp_schedule_event(time(), 'daily', 'termegest_check_availability');
        }
    }

    /**
     * Controlla disponibilità per tutte le location
     */
    public function check_all_locations() {
        error_log('=== INIZIO CONTROLLO DISPONIBILITÀ ===');
        
        // Verifica che Booking_Handler sia caricata
        if (!class_exists('Booking_Handler')) {
            error_log('ERRORE: Booking_Handler non caricata!');
            return;
        }
        
        // Verifica che il metodo esista
        if (!method_exists('Booking_Handler', 'get_locations_to_encrypt')) {
            error_log('ERRORE: Metodo get_locations_to_encrypt non trovato!');
            return;
        }

        // Prendi le location da Booking_Handler (unica fonte)
        $locations = Booking_Handler::get_locations_to_encrypt();
        
        if (empty($locations)) {
            error_log('ERRORE: Nessuna location trovata');
            return;
        }

        foreach ($locations as $slug => $location_name) {
            error_log("Controllando: {$location_name} ({$slug})");
            $this->check_location_availability($location_name);
        }

        error_log('=== FINE CONTROLLO DISPONIBILITÀ ===');
    }

    /**
     * Controlla disponibilità per una location (mese corrente + successivo)
     */
    private function check_location_availability($location) {

        // Cripta la location PRIMA di usarla
        $encryption = TermeGest_Encryption::get_instance();
        $encrypted_location = $encryption->encrypt($location);
        
        if (empty($encrypted_location)) {
            error_log("ERRORE: Impossibile criptare location: {$location}");
            return;
        }

        error_log("Location criptata: {$encrypted_location}");

        // Step 1: Crea array con tutti i giorni dei 2 mesi
        $all_dates = $this->get_all_dates_for_two_months();
        
        error_log("Totale giorni da controllare: " . count($all_dates));
        
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
            
            error_log("Controllando: {$location} - {$month}/{$year}");
            
            // Determina categoria in base al mese
            if ($month == 12 || $month == 1) {
                $cat = 'pm';
            } else {
                $cat = 'p2';
            }
            
            // Chiama API TermeGest per il mese
            // $dispArr = skianet_termegest_get_disponibilita($month, $year, $cat, $location);
            $dispArr = skianet_termegest_get_disponibilita($month, $year, $cat, $encrypted_location);

            // LOG: Struttura dati
            error_log("=== STRUTTURA dispArr per {$location} - {$month}/{$year} ===");
            error_log("Tipo: " . gettype($dispArr));
            error_log("Count: " . (is_array($dispArr) ? count($dispArr) : 'N/A'));
            error_log("Dump completo: " . print_r($dispArr, true));
            error_log("=== FINE STRUTTURA ===");


            if (empty($dispArr)) {
                error_log("Nessuna disponibilità API per {$location} - {$month}/{$year}");
                continue;
            }
            
            // Step 4: Aggiorna risultati per i giorni disponibili
            foreach ($dispArr as $dispo) {
                if (!isset($dispo->data)) {
                    continue;
                }
                
                // Parse data (formato: "2025-12-09 00:00:00")
                $date_obj = DateTime::createFromFormat('Y-m-d H:i:s', $dispo->data);
                if (!$date_obj) {
                    error_log("ERRORE parsing data: {$dispo->data}");
                    continue;
                }
                
                $date_key = $date_obj->format('Y-m-d'); // Es: "2025-12-09"
                
                // Se questo giorno è già disponibile, skip
                if (isset($results[$date_key]) && $results[$date_key] === true) {
                    continue;
                }
                
                // Verifica se questa fascia ha disponibilità
                if (isset($dispo->disponibili) && (int)$dispo->disponibili > 0) {
                    $results[$date_key] = true; // ✅ SALVA TRUE per questo giorno
                    error_log("✅ {$date_key}: Disponibile");
                }
            }
            
            // Pausa tra i mesi
            usleep(500000); // 0.5 secondi
        }
        
        // Step 5: Log riepilogo
        $available_count = count(array_filter($results));
        $total_count = count($results);
        error_log("Riepilogo {$location}: {$available_count}/{$total_count} giorni disponibili");
        
        // Step 6: Salva nel file JSON
        $this->save_json_file($location, $results);
    }

    /**
     * Ottieni tutti i giorni dei prossimi 2 mesi
     */
    private function get_all_dates_for_two_months() {
        $dates = array();
        
        // Primo giorno del mese corrente
        $start = new DateTime('first day of this month');
        
        // Ultimo giorno del mese successivo
        $end = new DateTime('last day of next month');
        
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
        $current_month = (int) date('n');
        $current_year = (int) date('Y');
        
        $next_date = new DateTime('+1 month');
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
        $filename = $this->get_json_filename($location);
        $filepath = $this->json_path . '/' . $filename;

        $json_data = array(
            'location' => $location,
            'generated_at' => current_time('mysql'),
            'availability' => $data
        );

        $result = file_put_contents(
            $filepath, 
            json_encode($json_data, JSON_PRETTY_PRINT)
        );

        if ($result !== false) {
            error_log("✅ JSON salvato: {$filepath}");
        } else {
            error_log("❌ Errore salvataggio JSON: {$filepath}");
        }
    }

    /**
     * Genera nome file JSON
     */
    private function get_json_filename($location) {
        $slug = sanitize_title($location);
        return "availability-{$slug}.json";
    }

    /**
     * Ottieni path pubblico del file JSON
     */
    public function get_json_url($location) {
        $upload_dir = wp_upload_dir();
        $filename = $this->get_json_filename($location);
        return $upload_dir['baseurl'] . '/' . self::JSON_DIR . '/' . $filename;
    }

    /**
     * Deactivation hook
     */
    public static function deactivate() {
        wp_clear_scheduled_hook('termegest_check_availability');
    }
}