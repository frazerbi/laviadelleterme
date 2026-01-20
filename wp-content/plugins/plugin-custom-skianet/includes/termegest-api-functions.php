<?php
/**
 * Funzioni wrapper per API TermeGest
 * Mantengono retrocompatibilità con il codice esistente
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Verifica disponibilità per un giorno specifico
 * 
 * @param int $day Giorno del mese
 * @param int $month Mese
 * @param int $year Anno
 * @param string $location Codice location
 * @return array Array di oggetti disponibilità
 */
function skianet_termegest_get_disponibilita_by_day(int $day, int $month, int $year, string $location): array {
    return TermeGest_API::get_instance()->get_disponibilita_by_day($day, $month, $year, $location);
}

/**
 * Ottieni disponibilità per mese
 * 
 * @param int $month Mese
 * @param int $year Anno
 * @param string $categoria Categoria
 * @param string $location Codice location
 * @return array Array di oggetti Disponibilita
 */
function skianet_termegest_get_disponibilita(int $month, int $year, string $categoria, string $location): array {
    return TermeGest_API::get_instance()->get_disponibilita($month, $year, $categoria, $location);
}

/**
 * Ottieni fasce orarie
 * 
 * @param int $day Giorno
 * @param int $month Mese
 * @param int $year Anno
 * @param string $location Codice location
 * @return array Array di oggetti Fascia
 */
function skianet_termegest_get_fascia(int $day, int $month, int $year, string $location): array {
    return TermeGest_API::get_instance()->get_fascia($day, $month, $year, $location);
}

/**
 * Ottieni disponibilità per ID
 * 
 * @param int $id ID disponibilità
 * @param string $location Codice location
 * @param string $categoria Categoria
 * @return int Numero posti disponibili
 */
function skianet_termegest_get_disponibilitaById(int $id, string $location, string $categoria): int {
    return TermeGest_API::get_instance()->get_disponibilita_by_id($id, $location, $categoria);
}

/**
 * Crea prenotazione
 * 
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
    return TermeGest_API::get_instance()->set_prenotazione(
        $idDisponibilita,
        $codice,
        $cognome,
        $nome,
        $telefono,
        $note,
        $provincia,
        $uomodonna,
        $email,
        $allInclusive,
        $categoria,
        $codControllo,
        $protection
    );
}

/**
 * Marca codice come venduto
 * 
 * @param string $codice Codice licenza
 * @param float $prezzo Prezzo
 * @param string $nome Nome cliente
 * @param string $email Email cliente
 * @return string Risultato operazione
 */
function skianet_termegest_set_venduto(string $codice, float $prezzo, string $nome, string $email): string {
    return TermeGest_API::get_instance()->set_venduto($codice, $prezzo, $nome, $email);
}