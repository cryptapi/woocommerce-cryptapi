<?php

namespace CryptAPI\Utils;

class Helper {
    public static function sig_fig($value, $digits): string
    {
        $value = (string) $value;
        if (strpos($value, '.') !== false) {
            if ($value[0] != '-') {
                return bcadd($value, '0.' . str_repeat('0', $digits) . '5', $digits);
            }

            return bcsub($value, '0.' . str_repeat('0', $digits) . '5', $digits);
        }

        return $value;
    }
}
