<?php namespace Nano7\Framework\Support;

use MongoDB\BSON\UTCDateTime;

class Carbon extends \Carbon\Carbon
{
    /**
     * Retorna uma string de quanto tempo faz da data do objeto e a data de agora.
     *
     * @return string
     */
    public function ago()
    {
        $periods = ['segundo', 'minuto', 'hora', 'dia', 'semana', 'mes', 'ano', 'década'];
        $lengths = ['60','60','24','7','4.35','12','10'];

        $now  = $this->now()->getTimestamp();
        $diff = $now - $this->getTimestamp();

        for($j = 0; $diff >= $lengths[$j] && $j < count($lengths)-1; $j++) {
            $diff /= $lengths[$j];
        }

        $diff = round($diff);
        if($diff != 1) {
            $periods[$j] .= 's';
        }

        return "há $diff $periods[$j]";
    }

    /**
     * Create carbon via UTCDateTime do mongo.
     *
     * @param UTCDateTime $dateTime
     * @return Carbon
     */
    public static function createFromMongoDateTime(UTCDateTime $dateTime)
    {
        return self::createFromTimestamp($dateTime->toDateTime()->getTimestamp());
    }

    /**
     * Converte carbon para UTCDateTime dp Mongo.
     *
     * @return UTCDateTime
     */
    public function toMongoDateTime()
    {
        return new UTCDateTime($this->getTimestamp() * 1000);
    }
}