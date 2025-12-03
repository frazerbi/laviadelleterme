<?php

declare(strict_types=1);

use TermeGest\Type\AnyXML;
use TermeGest\Type\Disponibilita;
use TermeGest\Type\Fascia;
use TermeGest\Type\TermeGestLogger;
use TermeGestGetReserv\TermeGestGetReservClientFactory;
use TermeGestGetReserv\Type\GetDisponibilita;
use TermeGestGetReserv\Type\GetDisponibilitaGiornoFascia;
use TermeGestGetReserv\Type\GetDisponibilitaById;
use TermeGestGetReserv\Type\GetFascia;
use TermeGestGetReserv\Type\SetPrenotazione;
use TermeGestSetInfo\TermeGestSetInfoClientFactory;
use TermeGestSetInfo\Type\SetVenduto;

if (! \defined('PLUGIN_SKIANET_FILE')) {
    exit();
}

/**
 * Verifica disponibilità per un giorno specifico
 * 
 * @param int $day Giorno del mese
 * @param int $month Mese
 * @param int $year Anno
 * @param string $location Codice location
 * @param string $time_slot Fascia oraria (es. "09:00")
 * @return int Numero di posti disponibili
 */
function skianet_termegest_get_disponibilita_by_day(int $day, int $month, int $year, string $location): array {
    $termeGestLogger = TermeGestLogger::getInstance();

    try {
        error_log("=== START GET DISPONIBILITA BY DAY ===");
        error_log("Input - Day: {$day}, Month: {$month}, Year: {$year}, Location: {$location}");
        
        // Usa la classe di criptazione
        $encryption = TermeGest_Encryption::get_instance();
        $encrypted_location = $encryption->encrypt($location);
        
        if (empty($encrypted_location)) {
            error_log("ERRORE: encrypted_location è vuota!");
            $termeGestLogger->send('Error encrypting location: ' . $location);
            return [];
        }
        
        error_log("Location originale: {$location}");
        error_log("Location criptata: {$encrypted_location}");

        error_log("Creando client SOAP...");
        $client = TermeGestGetReservClientFactory::factory('https://www.termegest.it/getReserv.asmx?WSDL');
        error_log("Client SOAP creato con successo");
        
        error_log("Chiamando getDisponibilitaByDay...");
        $response = $client->getDisponibilitaGiornoFascia(
            new GetDisponibilitaGiornoFascia($year, $month, $day, $encrypted_location)
        );
        error_log("Risposta ricevuta da SOAP");
        
        error_log("Response type: " . get_class($response));
        
        // Se è MixedResult, accedi direttamente al result
        if ($response instanceof \Phpro\SoapClient\Type\MixedResult) {
            error_log("È un MixedResult");
            $raw = $response->getResult();
            error_log("Raw result: " . print_r($raw, true));
            
            // Prova a convertire
            $result = (new AnyXML($raw))->convertXmlToPhpObject();
            error_log("Converted: " . print_r($result, true));
            return $result;
        }
        

        error_log("Tipo response: " . get_class($response));
        
        // Controlla se il metodo esiste
        if (!method_exists($response, 'getGetDisponibilitaByDayResult')) {
            error_log("ERRORE: Il metodo getGetDisponibilitaByDayResult non esiste!");
            error_log("Metodi disponibili: " . print_r(get_class_methods($response), true));
            return [];
        }
        
        error_log("Chiamando getGetDisponibilitaByDayResult...");
        $disponibilita_result = $response->getGetDisponibilitaGiornoFasciaResult();
        
        if ($disponibilita_result === null) {
            error_log("AVVISO: getGetDisponibilitaByDayResult ha ritornato NULL");
            return [];
        }
        
        error_log("Tipo disponibilita_result: " . get_class($disponibilita_result));
        
        error_log("Chiamando getAny...");
        $raw_response = $disponibilita_result->getAny();
        
        if ($raw_response === null) {
            error_log("AVVISO: getAny ha ritornato NULL");
            return [];
        }
        
        error_log("Raw response type: " . gettype($raw_response));
        error_log("Raw response content: " . print_r($raw_response, true));

        error_log("Convertendo XML a oggetto PHP...");
        $result = (new AnyXML($raw_response))->convertXmlToPhpObject();
        
        error_log("Conversione completata");
        error_log("Result type: " . gettype($result));
        error_log("Result is_array: " . (is_array($result) ? 'SI' : 'NO'));
        error_log("Result count: " . (is_array($result) ? count($result) : 'N/A'));
        error_log("Result content: " . print_r($result, true));
        
        error_log("=== END GET DISPONIBILITA BY DAY ===");
        
        return $result;

    } catch (Throwable $throwable) {
        error_log("=== EXCEPTION IN GET DISPONIBILITA BY DAY ===");
        error_log("Exception type: " . get_class($throwable));
        error_log("Exception message: " . $throwable->getMessage());
        error_log("Exception trace: " . $throwable->getTraceAsString());
        
        $termeGestLogger->send('Error getDisponibilitaByDay: ' . $throwable->getMessage());
        $termeGestLogger->flushLog();

        return [];
    }
}

/**
 * @return array|Disponibilita[]
 */
function skianet_termegest_get_disponibilita(int $month, int $year, string $categoria, string $location): array
{
    $termeGestLogger = TermeGestLogger::getInstance();

    try {
        $client = TermeGestGetReservClientFactory::factory('https://www.termegest.it/getReserv.asmx?WSDL');

        $response = $client->getDisponibilita(new GetDisponibilita($year, $month, $categoria, $location));

        return (new AnyXML($response->getGetDisponibilitaResult()?->getAny()))->convertXmlToPhpObject();
    } catch (Throwable $throwable) {
        $termeGestLogger->send('Error getDisponibilita: '.$throwable->getMessage());
        $termeGestLogger->flushLog();

        return [];
    }
}

/**
 * @return array|Fascia[]
 */
function skianet_termegest_get_fascia(int $day, int $month, int $year, string $location): array
{
    $termeGestLogger = TermeGestLogger::getInstance();

    try {
        $client = TermeGestGetReservClientFactory::factory('https://www.termegest.it/getReserv.asmx?WSDL');

        $response = $client->getFascia(new GetFascia(new DateTime($year.'-'.$month.'-'.$day.' 00:00:00'), $location));

        return (new AnyXML($response->getGetFasciaResult()?->getAny()))->convertXmlToPhpObject();
    } catch (Exception $exception) {
        $termeGestLogger->send('Error getFascia: '.$exception->getMessage());
        $termeGestLogger->flushLog();

        return [];
    }
}

function skianet_termegest_get_disponibilitaById(int $id, string $location, string $categoria): int
{   

     // Verifica che l'ID non sia zero o vuoto
    if ($id === 0 || empty($id)) {
        error_log("ERROR: ID is zero or empty!");
        return 0;
    }
    
    $termeGestLogger = TermeGestLogger::getInstance();

    try {
        
        $client = TermeGestGetReservClientFactory::factory('https://www.termegest.it/getReserv.asmx?WSDL');
        
        // Log dei parametri della chiamata
        error_log("Calling getDisponibilitaById with params: ID={$id}, Resources='{$categoria}', Location={$location}");

        // Esegui la chiamata e registra il tempo di risposta
        $startTime = microtime(true);
        $response = $client->getDisponibilitaById(new GetDisponibilitaById($id, $categoria, $location));

        $endTime = microtime(true);
                
        // Ottieni il risultato
        $result = $response->getGetDisponibilitaByIdResult();
        error_log("API result: {$result}");
        
        return $result;

    } catch (Exception $exception) {

        // Registra l'errore nel logger termegest e aggiungi log
        $errorMessage = "Error getDisponibilitaById (ID: {$id}, Location: {$location}, Categoria: {$categoria}): ".$exception->getMessage();
        error_log("Sending to TermeGestLogger: " . $errorMessage);
        
        $termeGestLogger->send($errorMessage);
        
        $termeGestLogger->flushLog();

        return 0;
    }
}

/**
 * @return array{status: bool, message: string}
 */
function skianet_termegest_set_prenotazione(
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
    $termeGestLogger = TermeGestLogger::getInstance();

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
        $response = TermeGestGetReservClientFactory::factory('https://www.termegest.it/getReserv.asmx?WSDL')
            ->setPrenotazione(new SetPrenotazione(...array_values($parameters)));

        return ['status' => $response->getSetPrenotazioneResult(), 'message' => $response->getErrMsg()];
    } catch (Exception $exception) {
        $str = 'Error setPrenotazione: '.$exception->getMessage();
        $str .= \PHP_EOL;
        ob_start();
        var_dump($parameters);
        $str .= ob_get_clean();
        $termeGestLogger->send($str);
        $termeGestLogger->flushLog();

        return ['status' => false, 'message' => $exception->getMessage()];
    }
}

function skianet_termegest_set_venduto(string $codice, float $prezzo, string $nome, string $email): string
{
    $termeGestLogger = TermeGestLogger::getInstance();

    $parameters = [
        'codice' => $codice,
        'prezzo' => $prezzo,
        'nome' => $nome,
        'email' => $email,
        'security' => 'qpoz79nt1z3p2vcllpt2iqnz66c7zk3',
    ];

    try {
        $response = TermeGestSetInfoClientFactory::factory('https://www.termegest.it/setinfo.asmx?WSDL')
            ->setVenduto(
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
        $str = 'Error setPrenotazione: '.$exception->getMessage();
        $str .= \PHP_EOL;
        ob_start();
        var_dump($parameters);
        $str .= ob_get_clean();
        $termeGestLogger->send($str);
        $termeGestLogger->flushLog();

        return $exception->getMessage();
    }
}
