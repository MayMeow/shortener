<?php
declare(strict_types=1);

namespace MayMeow\Shortener;

use InvalidArgumentException;

class LinkShortteningService
{
    function numToShortString($num)
    {
        $chars = '123456789abcdefghijklmnopqrstuvwxyz';
        $base = strlen($chars);

        $result = '';
        while ($num > 0) {
            $remainder = ($num - 1) % $base;
            $result = $chars[$remainder] . $result;
            $num = intval(($num - 1) / $base);
        }

        return $result;
    }

    function shortStringToNum($str)
    {
        $chars = '123456789abcdefghijklmnopqrstuvwxyz';
        $base = strlen($chars);
        $len = strlen($str);
        $num = 0;

        for ($i = 0; $i < $len; $i++) {
            $pos = strpos($chars, $str[$i]);
            if ($pos === false) {
                throw new InvalidArgumentException("Neplatný znak: {$str[$i]}");
            }
            $num = $num * $base + ($pos + 1);
        }

        return $num;
    }
}