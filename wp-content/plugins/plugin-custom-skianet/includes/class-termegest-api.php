<?php
/**
 * Wrapper API TermeGest
 * Gestisce tutte le chiamate SOAP all'API TermeGest
 */

if (!defined('ABSPATH')) {
    exit;
}

use TermeGest\Type\AnyXML;
use TermeGest\Type\TermeGestLogger;
use TermeGestGetReserv\TermeGestGetReservClientFactory;
use TermeGestGetReserv\Type\GetDisponibilita;
use TermeGestGetReserv\Type\GetDisponibilitaGiornoFascia;
use TermeGestGetReserv\Type\GetDisponibilitaById;
use TermeGestGetReserv\Type\GetFascia;
use TermeGestGetReserv\Type\SetPrenotazione;
use TermeGestSetInfo\TermeGestSetInfoClientFactory;
use TermeGestSetInfo\Type\SetVenduto;

class TermeGest_API {

    /**
     * Istanza singleton
     */
    private static $instance = null;
    
    /**
     * Client SOAP GetReserv (cached)
     */
    private $client_get_reserv = null;
    
    /**
     * Client SOAP SetInfo (cached)
     */
    private $client_set_info = null;
    
    /**
     * Logger TermeGest
     */
    private $logger = null;

    /**
     * WSDL URLs
     */
    private const WSDL_GET_RESERV = 'https://www.termegest.it/getReserv.asmx?WSDL';
    private const WSDL_SET_INFO = 'https://www.termegest.it/setinfo.asmx?WSDL';
    private const SECURITY_KEY = 'qpoz79nt1z3p2vcllpt2iqnz66c7zk3';

    /**
     * Ottieni istanza singleton
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Costruttore privato
     */
    private function __construct() {
        $this->logger = TermeGestLogger::getInstance();
    }

    /**
     * Ottieni client GetReserv (cached per performance)
     */
    private function get_reserv_client() {
        if (null === $this->client_get_reserv) {
            $this->client_get_reserv = TermeGestGetReservClientFactory::factory(self::WSDL_GET_RESERV);
            error_log('✅ Client SOAP GetReserv creato e cached');
        }
        return $this->client_get_reserv;
    }

    /**
     * Ottieni client SetInfo (cached per performance)
     */
    private function get_set_info_client() {
        if (null === $this->client_set_info) {
            $this->client_set_info = TermeGestSetInfoClientFactory::factory(self::WSDL_SET_INFO);
            error_log('✅ Client SOAP SetInfo creato e cached');
        }
        return $this->client_set_info;
    }

    /**
     * Verifica disponibilità per giorno specifico
     * 
     * @param int $day Giorno del mese
     * @param int $month Mese
     * @param int $year Anno
     * @param string $location Codice location
     * @return array Array di oggetti disponibilità
     */
    public function get_disponibilita_by_day(int $day, int $month, int $year, string $location): array {
        try {
            // Cripta location
            $encryption = TermeGest_Encryption::get_instance();
            $encrypted_location = $encryption->encrypt($location);
            
            if (empty($encrypted_location)) {
                error_log("ERRORE: encrypted_location è vuota per location: {$location}");
                return [];
            }
            
            // Chiamata SOAP
            $response = $this->get_reserv_client()->getDisponibilitaGiornoFascia(
                new GetDisponibilitaGiornoFascia($year, $month, $day, $encrypted_location)
            );

            $disponibilita_result = $response->getGetDisponibilitaGiornoFasciaResult();
            
            if ($disponibilita_result === null) {
                error_log("AVVISO: getGetDisponibilitaGiornoFasciaResult ha ritornato NULL per {$year}-{$month}-{$day}");
                return [];
            }

            $raw_response = $disponibilita_result->getAny();
            if ($raw_response === null) {
                error_log("AVVISO: getAny ha ritornato NULL");
                return [];
            }
            
            $result = (new AnyXML($raw_response))->convertXmlToPhpObject();
            
            if (!is_array($result) || empty($result)) {
                error_log("AVVISO: Risultato conversione non valido o vuoto");
                return [];
            }
         
            return $result;

        } catch (Throwable $throwable) {
            error_log("Exception get_disponibilita_by_day: " . $throwable->getMessage());
            $this->logger->send('Error getDisponibilitaGiornoFascia: ' . $throwable->getMessage());
            $this->logger->flushLog();
            return [];
        }
    }

    /**
     * Ottieni disponibilità per mese
     * 
     * @param int $month Mese
     * @param int $year Anno
     * @param string $categoria Categoria (es: "P1,P2")
     * @param string $location Codice location
     * @return array Array di oggetti Disponibilita
     */
    public function get_disponibilita(int $month, int $year, string $categoria, string $location): array {
        try {
            $response = $this->get_reserv_client()->getDisponibilita(
                new GetDisponibilita($year, $month, $categoria, $location)
            );

            return (new AnyXML($response->getGetDisponibilitaResult()?->getAny()))->convertXmlToPhpObject();
        } catch (Throwable $throwable) {
            $this->logger->send('Error getDisponibilita: ' . $throwable->getMessage());
            $this->logger->flushLog();
            return [];
        }
    }

    /**
     * Ottieni fasce orarie per un giorno
     * 
     * @param int $day Giorno
     * @param int $month Mese
     * @param int $year Anno
     * @param string $location Codice location
     * @return array Array di oggetti Fascia
     */
    public function get_fascia(int $day, int $month, int $year, string $location): array {
        try {
            $response = $this->get_reserv_client()->getFascia(
                new GetFascia(new DateTime($year.'-'.$month.'-'.$day.' 00:00:00'), $location)
            );

            return (new AnyXML($response->getGetFasciaResult()?->getAny()))->convertXmlToPhpObject();
        } catch (Exception $exception) {
            $this->logger->send('Error getFascia: ' . $exception->getMessage());
            $this->logger->flushLog();
            return [];
        }
    }

    /**
     * Ottieni disponibilità per ID fascia
     * 
     * @param int $id ID disponibilità
     * @param string $location Codice location
     * @param string $categoria Categoria
     * @return int Numero posti disponibili
     */
    public function get_disponibilita_by_id(int $id, string $location, string $categoria): int {
        if ($id === 0 || empty($id)) {
            error_log("ERROR: ID is zero or empty!");
            return 0;
        }

        try {
            error_log("Calling getDisponibilitaById: ID={$id}, Location={$location}, Categoria={$categoria}");
            
            $startTime = microtime(true);
            $response = $this->get_reserv_client()->getDisponibilitaById(
                new GetDisponibilitaById($id, $categoria, $location)
            );
            $endTime = microtime(true);
            
            $result = $response->getGetDisponibilitaByIdResult();
            error_log("API result: {$result} (tempo: " . round(($endTime - $startTime) * 1000, 2) . "ms)");
            
            return $result;

        } catch (Exception $exception) {
            $errorMessage = "Error getDisponibilitaById (ID: {$id}, Location: {$location}, Categoria: {$categoria}): " . $exception->getMessage();
            error_log($errorMessage);
            $this->logger->send($errorMessage);
            $this->logger->flushLog();
            return 0;
        }
    }

    /**
     * Crea prenotazione su TermeGest
     * 
     * @return array{status: bool, message: string}
     */
    public function set_prenotazione(
        int $idDisponibilita,
        string $codice,
        string $cognome,
        string $nome,
        string $telefono,
        string $note,
        string $provincia,
        bool $uomodonna,
        string $email,
        bool $allInclusive,
        string $categoria,
        string $codControllo,
        string $protection
    ): array {
        $parameters = [
            'idDisponibilita' => $idDisponibilita,
            'codice' => $codice,
            'Cognome' => $cognome,
            'Nome' => $nome,
            'Telefono' => $telefono,
            'Note' => $note,
            'Provincia' => $provincia,
            'uomodonna' => $uomodonna,
            'Email' => $email,
            'AllInclusive' => $allInclusive,
            'Categoria' => $categoria,
            'CodControllo' => $codControllo,
            'protection' => $protection,
        ];

        try {
            $response = $this->get_reserv_client()->setPrenotazione(
                new SetPrenotazione(...array_values($parameters))
            );

            return [
                'status' => $response->getSetPrenotazioneResult(), 
                'message' => $response->getErrMsg()
            ];
        } catch (Exception $exception) {
            $str = 'Error setPrenotazione: ' . $exception->getMessage();
            $str .= PHP_EOL;
            ob_start();
            var_dump($parameters);
            $str .= ob_get_clean();
            $this->logger->send($str);
            $this->logger->flushLog();

            return ['status' => false, 'message' => $exception->getMessage()];
        }
    }

    /**
     * Marca codice come venduto su TermeGest
     * 
     * @param string $codice Codice licenza
     * @param float $prezzo Prezzo
     * @param string $nome Nome cliente
     * @param string $email Email cliente
     * @return string Risultato operazione
     */
    public function set_venduto(string $codice, float $prezzo, string $nome, string $email): string {
        $parameters = [
            'codice' => $codice,
            'prezzo' => $prezzo,
            'nome' => $nome,
            'email' => $email,
            'security' => self::SECURITY_KEY,
        ];

        try {
            $response = $this->get_set_info_client()->setVenduto(
                new SetVenduto(
                    $parameters['codice'],
                    $parameters['prezzo'],
                    $parameters['nome'],
                    $parameters['email'],
                    $parameters['security']
                )
            );

            return $response->getSetVendutoResult();
        } catch (Exception $exception) {
            $str = 'Error setVenduto: ' . $exception->getMessage();
            $str .= PHP_EOL;
            ob_start();
            var_dump($parameters);
            $str .= ob_get_clean();
            $this->logger->send($str);
            $this->logger->flushLog();

            return $exception->getMessage();
        }
    }
}