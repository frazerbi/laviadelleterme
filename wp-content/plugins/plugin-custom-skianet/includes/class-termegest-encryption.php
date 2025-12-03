<?php
/**
 * Gestisce la criptazione per TermeGest
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

class TermeGest_Encryption {

    /**
     * Chiave di criptazione
     */
    private const ENCRYPTION_KEY = 'konsb1351f7kk3x7rz2phunuje1h80kk';

    /**
     * Algoritmo di criptazione
     */
    private const CIPHER_METHOD = 'AES-256-CBC';

    /**
     * Lunghezza IV (Initialization Vector)
     */
    private const IV_LENGTH = 16;

    /**
     * Istanza singleton
     */
    private static $instance = null;

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
     * Costruttore privato per singleton
     */
    private function __construct() {
        // Verifica che OpenSSL sia disponibile
        if (!function_exists('openssl_encrypt')) {
            error_log('TermeGest_Encryption: OpenSSL non disponibile!');
        }
    }

    /**
     * Cripta una stringa (location)
     * 
     * @param string $data Stringa da criptare
     * @return string Stringa criptata (vuota se errore)
     */
    public function encrypt(string $data): string {
        if (empty($data)) {
            error_log('TermeGest_Encryption: Tentativo di criptare stringa vuota');
            return '';
        }

        // Genera un IV casuale
        $iv = $this->generate_iv();

        // Cripta i dati
        $encrypted = openssl_encrypt(
            $data, 
            self::CIPHER_METHOD, 
            self::ENCRYPTION_KEY, 
            OPENSSL_RAW_DATA, 
            $iv
        );

        if ($encrypted === false) {
            error_log('TermeGest_Encryption: Errore durante la criptazione di: ' . $data);
            return '';
        }

        // Concatena IV + dati criptati (base64)
        $result = $iv . base64_encode($encrypted);

        error_log("TermeGest_Encryption: '{$data}' -> '{$result}'");

        return $result;
    }

    /**
     * Genera un Initialization Vector casuale
     * 
     * @return string IV di 16 caratteri
     */
    private function generate_iv(): string {
        return mb_substr(str_shuffle(md5(microtime())), 0, self::IV_LENGTH);
    }

    /**
     * Verifica se OpenSSL è disponibile
     * 
     * @return bool
     */
    public function is_available(): bool {
        return function_exists('openssl_encrypt');
    }

    /**
     * Ottieni informazioni sulla configurazione
     * 
     * @return array
     */
    public function get_info(): array {
        return array(
            'cipher_method' => self::CIPHER_METHOD,
            'iv_length' => self::IV_LENGTH,
            'openssl_available' => $this->is_available(),
            'key_length' => strlen(self::ENCRYPTION_KEY)
        );
    }
}