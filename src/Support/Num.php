<?php namespace Nano7\Framework\Support;

class Num
{
    /**
     * Returns the percentage of a fraction of a total value.
     *
     * @param $npart
     * @param $value
     * @param int $dec
     * @param int $round
     * @return float|int
     */
    public static function percentage($npart, $value, $dec = 2, $round = PHP_ROUND_HALF_UP)
    {
        if ($value <= 0) {
            return 0;
        }

        return round(($npart * 100) / $value, $dec, $round);
    }

    /**
     * Returns the value of a percentage of a total value.
     *
     * @param $percentage
     * @param $value
     * @param int $dec
     * @param int $round
     * @return float
     */
    public static function percent($percentage, $value, $dec = 2, $round = PHP_ROUND_HALF_UP)
    {
        return round(($value * $percentage) / 100, $dec, $round);
    }
}