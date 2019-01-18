<?php namespace Nano7\Framework\Support;

class Str extends \Illuminate\Support\Str
{
    /**
     * Convert a value to unstudly caps case.
     *
     * @param  string  $value
     * @return string
     */
    public static function unstudly($value)
    {
        $rets = [];
        $value = ucfirst($value);

        preg_match_all('/([A-Z]{1}[a-z0-9_]+)/', $value, $items, PREG_PATTERN_ORDER);
        for ($i = 0; $i < count($items[0]); $i++) {
            $item = $items[1][$i];
            $rets[] = strtolower($item);
        }

        return implode('_', $rets);
    }

    /**
     * @param $value
     * @return bool|null|string
     */
    public static function value($value)
    {
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        if (($valueLength = strlen($value)) > 1 && $value[0] === '"' && $value[$valueLength - 1] === '"') {
            return substr($value, 1, -1);
        }

        return $value;
    }
}