<?php

declare(strict_types=1);

use TermeGest\Type\AnyXML;
use TermeGest\Type\Disponibilita;
use TermeGest\Type\Fascia;
use TermeGest\Type\TermeGestLogger;
use TermeGestGetReserv\TermeGestGetReservClientFactory;
use TermeGestGetReserv\Type\GetDisponibilita;
use TermeGestGetReserv\Type\GetDisponibilitaById;
use TermeGestGetReserv\Type\GetFascia;
use TermeGestGetReserv\Type\SetPrenotazione;
use TermeGestSetInfo\TermeGestSetInfoClientFactory;
use TermeGestSetInfo\Type\SetVenduto;

if (! \defined('PLUGIN_SKIANET_FILE')) {
    exit();
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
