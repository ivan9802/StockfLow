<?php

namespace App\Http\Requests;

use App\Support\Period;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/** Période d'analyse du tableau de bord, validée côté serveur. */
class DashboardPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('stock.view');
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', 'string', Rule::in(array_keys(Period::PRESETS))],
            'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'compare' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'period.in' => 'Cette période n\'existe pas.',
            'from.required_if' => 'Indiquez une date de début.',
            'to.required_if' => 'Indiquez une date de fin.',
            'from.date_format' => 'La date de début n\'est pas valide.',
            'to.date_format' => 'La date de fin n\'est pas valide.',
            'to.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }

    /**
     * Sur la page (et non par Fetch), une période invalide ramène à la période par défaut
     * avec un message : renvoyer vers la même adresse provoquerait une boucle.
     */
    protected function failedValidation(Validator $validator): void
    {
        if (! $this->expectsJson()) {
            throw new HttpResponseException(
                redirect()->route('dashboard')->with('warning', $validator->errors()->first().' Période par défaut affichée.')
            );
        }

        parent::failedValidation($validator);
    }

    public function period(): Period
    {
        $preset = $this->validated('period') ?? Period::DEFAULT;

        if ($preset !== 'custom') {
            return Period::preset($preset);
        }

        try {
            return Period::custom($this->validated('from'), $this->validated('to'));
        } catch (InvalidArgumentException $e) {
            // Erreur métier (période trop longue, date inexistante) rendue comme une erreur de saisie.
            $validator = validator([], []);
            $validator->errors()->add('from', $e->getMessage());
            $this->failedValidation($validator);
        }
    }

    public function wantsComparison(): bool
    {
        return $this->boolean('compare', true);
    }
}
