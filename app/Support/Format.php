<?php

namespace App\Support;

use DateTimeInterface;

/** Formats d'affichage locaux : F CFA, dates JJ/MM/AAAA, nombres avec espace des milliers. */
final class Format
{
    /** 1200000 devient « 1 200 000 F CFA ». */
    public static function money(int|float|null $amount): string
    {
        return self::number($amount).' F CFA';
    }

    /** 1200000 devient « 1 200 000 ». L'espace est insécable pour ne pas couper le nombre. */
    public static function number(int|float|null $value): string
    {
        return number_format((float) ($value ?? 0), 0, ',', "\u{00A0}");
    }

    public static function date(?DateTimeInterface $date): string
    {
        return $date ? $date->format('d/m/Y') : '';
    }

    public static function dateTime(?DateTimeInterface $date): string
    {
        return $date ? $date->format('d/m/Y H:i') : '';
    }

    /** Variation en pourcentage, une décimale au plus : « +12,5 % », « -3 % », « 0 % ». */
    public static function percent(?float $value): string
    {
        if ($value === null) {
            return '';
        }

        $text = rtrim(rtrim(number_format(abs($value), 1, ',', "\u{00A0}"), '0'), ',');
        $sign = $value > 0 ? '+' : ($value < 0 ? '-' : '');

        return $sign.$text."\u{00A0}%";
    }

    /** Écart signé : « +3 », « -2 », « 0 ». */
    public static function signed(?int $value): string
    {
        if ($value === null) {
            return '';
        }

        return ($value > 0 ? '+' : '').self::number($value);
    }
}
