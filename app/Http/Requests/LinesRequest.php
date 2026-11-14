<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base des formulaires à lignes de produits (vente, transfert, commande, réception directe).
 * Le droit est vérifié par le middleware de la route ; ici, on valide la forme des lignes.
 */
abstract class LinesRequest extends FormRequest
{
    /** Nombre maximal de lignes par document. */
    protected const MAX_LINES = 100;

    public function authorize(): bool
    {
        return true;
    }

    protected function lineRules(bool $withCost = false): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ] + ($withCost ? ['lines.*.unit_cost' => ['required', 'integer', 'min:0', 'max:2000000000']] : []);
    }

    /** Les lignes envoyées sans produit (ligne vide du formulaire) sont ignorées. */
    protected function prepareForValidation(): void
    {
        $lines = collect($this->input('lines', []))
            ->filter(fn ($line) => is_array($line) && filled($line['product_id'] ?? null))
            ->map(function ($line) {
                foreach (['quantity', 'unit_cost'] as $key) {
                    if (isset($line[$key]) && is_string($line[$key])) {
                        $line[$key] = preg_replace('/[\s\x{00A0}\x{202F}]/u', '', $line[$key]);
                    }
                }

                return $line;
            })
            ->values()->all();

        $this->merge(['lines' => $lines]);
    }

    /** @return array<int, array<string, int>> */
    public function lines(): array
    {
        return array_map(fn ($line) => array_map('intval', $line), $this->validated('lines'));
    }
}
