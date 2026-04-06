<?php

namespace App\Support;

/**
 * Normalize MAC addresses for comparison between SKYmanager rows and RouterOS output.
 */
final class RouterMacAddress
{
    public static function normalize(?string $mac): ?string
    {
        if ($mac === null || $mac === '') {
            return null;
        }

        $m = strtoupper(str_replace(['-', '.'], ':', trim($mac)));
        $m = preg_replace('/[^0-9A-F:]/', '', $m) ?? '';

        if ($m === '') {
            return null;
        }

        return $m;
    }

    public static function matches(?string $a, ?string $b): bool
    {
        $na = self::normalize($a);
        $nb = self::normalize($b);

        if ($na === null || $nb === null) {
            return false;
        }

        return $na === $nb;
    }
}
