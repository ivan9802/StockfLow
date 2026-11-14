<?php

namespace App\Services;

use Picqer\Barcode\BarcodeGeneratorSVG;

/**
 * Codes-barres des produits.
 *
 * Un produit sans code fabricant reçoit un EAN-13 interne : préfixe 200 (plage
 * réservée à l'usage en magasin), neuf chiffres de numéro d'ordre, clé de contrôle.
 */
final class BarcodeGenerator
{
    private const INTERNAL_PREFIX = '200';

    public function __construct(private readonly ReferenceGenerator $references) {}

    /** Nouveau code EAN-13 interne, unique. */
    public function next(): string
    {
        $body = self::INTERNAL_PREFIX.sprintf('%09d', $this->references->nextNumber('EAN'));

        return $body.self::checkDigit($body);
    }

    /** Clé de contrôle EAN-13 des douze premiers chiffres. */
    public static function checkDigit(string $twelveDigits): int
    {
        $sum = 0;
        foreach (str_split($twelveDigits) as $index => $digit) {
            // Rangs pairs (en partant de 1) pondérés par 3.
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10;
    }

    public static function isValidEan13(string $code): bool
    {
        return preg_match('/^\d{13}$/', $code) === 1
            && self::checkDigit(substr($code, 0, 12)) === (int) $code[12];
    }

    /**
     * Dessin du code-barres en SVG. EAN-13 quand le code en est un, Code 128 sinon
     * (code fabricant d'une autre longueur). Le SVG est produit par le serveur à
     * partir d'un code déjà validé : il peut être inséré tel quel dans la page.
     */
    public function svg(string $code, int $height = 56): string
    {
        $generator = new BarcodeGeneratorSVG;
        $type = self::isValidEan13($code) ? $generator::TYPE_EAN_13 : $generator::TYPE_CODE_128;

        return $generator->getBarcode($code, $type, 2, $height);
    }
}
