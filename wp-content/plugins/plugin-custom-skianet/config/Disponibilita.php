<?php

declare(strict_types=1);

namespace TermeGest\Type;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use stdClass;
use Throwable;

class Disponibilita extends stdClass
{
    public const AVAILABILITY_LIMIT = 30;

    public string $categorie = '';

    public string $data = '';

    public int $disponibili = 0;

    public int $dispoorig = 0;

    public string $fascia = '';

    public int $iddispo = 0;

    public string $note = '';

    public int $prenotati = 0;

    public function getAvailable(): int
    {   
        // return max(0, $this->disponibili ?: ($this->dispoorig ?: self::AVAILABILITY_LIMIT) - $this->prenotati);
        return $this->disponibili;

    }

    /**
     * Determina la categoria corretta basata sul giorno della settimana
     */
    public function setCategory(): string
    {
        $dayOfWeek = (int) $this->getDateTime()->format('N'); // 1=Lunedì, 7=Domenica
        return ($dayOfWeek >= 1 && $dayOfWeek <= 5) ? 'p3' : 'p4';
    }

    /**
     * @return string[]
     */
    public function getCategory(): array
    {
        $tmp = explode(',', $this->categorie);

        if ($tmp === false) {
            $tmp = [];
        }

        $tmp = array_filter($tmp);
        sort($tmp);

        return $tmp;
    }

    public function getMilliseconds(): int
    {
        return $this->getDateTime()
            ->getTimestamp() * 1000;
    }

    public function getDateTime(): DateTimeImmutable
    {
        $date = $this->getDate();
        try {
            return $date->add(new DateInterval('PT'.$this->getHour().'H'));
        } catch (Throwable) {
            return $date;
        }
    }

    public function getDate(): DateTimeImmutable
    {
        try {
            $date = new DateTimeImmutable($this->data, new DateTimeZone(wp_timezone_string()));
        } catch (Throwable) {
            $date = new DateTimeImmutable();
        }

        return $date;
    }

    public function getHour(): int
    {
        if (str_contains($this->getFascia(), ':') === false) {
            return 0;
        }

        return (int) strtok($this->getFascia(), ':');
    }

    public function getFascia(): string
    {
        if (str_contains($this->fascia, 'Ingresso ore') === false) {
            return $this->fascia;
        }

        return trim(str_ireplace('Ingresso ore', '', $this->fascia));
    }
}
