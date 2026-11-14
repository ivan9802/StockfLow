<?php

namespace App\Exports;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV en flux, lisible directement par Excel en français :
 * encodage UTF-8 avec BOM, séparateur point-virgule, une ligne écrite à la fois
 * (pas de chargement de tout l'historique en mémoire).
 */
final class CsvWriter
{
    /**
     * @param  array<int, string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';', '"', '');

            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'cell'], $row), ';', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Neutralise les formules : une cellule qui commence par =, +, - ou @ serait
     * exécutée par le tableur à l'ouverture (injection de formule).
     */
    private static function cell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        if ($value !== '' && ! is_numeric($value) && Str::startsWith($value, ['=', '+', '-', '@', "\t", "\r"])) {
            return "'".$value;
        }

        return $value;
    }
}
