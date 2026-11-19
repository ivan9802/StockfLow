<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Période d'analyse du tableau de bord.
 *
 * Toujours construite et validée côté serveur, à partir d'un préréglage ou de deux
 * dates. Les bornes sont des journées entières (début à 00:00:00, fin à 23:59:59)
 * dans le fuseau de l'application (Africa/Abidjan).
 */
final class Period
{
    public const PRESETS = [
        'today' => 'Aujourd\'hui',
        'yesterday' => 'Hier',
        'last_7_days' => 'Les 7 derniers jours',
        'last_30_days' => 'Les 30 derniers jours',
        'this_week' => 'Cette semaine',
        'last_week' => 'La semaine dernière',
        'this_month' => 'Ce mois-ci',
        'last_month' => 'Le mois dernier',
        'this_year' => 'Cette année',
        'last_year' => 'L\'année dernière',
        'custom' => 'Période personnalisée',
    ];

    public const DEFAULT = 'last_30_days';

    /** Une période personnalisée ne couvre pas plus de deux ans. */
    public const MAX_DAYS = 731;

    private function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly string $preset,
    ) {}

    public static function preset(string $preset, ?CarbonImmutable $today = null): self
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();

        [$start, $end] = match ($preset) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            'last_7_days' => [$today->subDays(6), $today],
            'last_30_days' => [$today->subDays(29), $today],
            // La semaine commence le lundi.
            'this_week' => [$today->startOfWeek(CarbonImmutable::MONDAY), $today],
            'last_week' => [$today->subWeek()->startOfWeek(CarbonImmutable::MONDAY), $today->subWeek()->endOfWeek(CarbonImmutable::SUNDAY)],
            'this_month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$today->startOfYear(), $today],
            'last_year' => [$today->subYear()->startOfYear(), $today->subYear()->endOfYear()],
            default => throw new InvalidArgumentException("Période inconnue : $preset"),
        };

        return new self($start->startOfDay(), $end->endOfDay(), $preset);
    }

    /**
     * @throws InvalidArgumentException si les dates sont incohérentes
     */
    public static function custom(string $from, string $to): self
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $from);
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $to);

        if (! $start || ! $end || $start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to) {
            throw new InvalidArgumentException('Les dates doivent être au format AAAA-MM-JJ et exister au calendrier.');
        }
        if ($start->greaterThan($end)) {
            throw new InvalidArgumentException('La date de début doit précéder la date de fin.');
        }
        if ($start->diffInDays($end) + 1 > self::MAX_DAYS) {
            throw new InvalidArgumentException('Une période ne peut pas dépasser deux ans.');
        }

        return new self($start->startOfDay(), $end->endOfDay(), 'custom');
    }

    /** Nombre de jours couverts, bornes comprises. */
    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end->startOfDay()) + 1;
    }

    /**
     * Période de référence pour la comparaison.
     *
     *  - Mois : le mois précédent, sur la même tranche de jours (1er au 19 novembre
     *    comparé au 1er au 19 octobre ; un mois complet comparé au mois complet d'avant).
     *  - Année : la même tranche de l'année précédente.
     *  - Sinon : la période de même durée qui se termine la veille du début
     *    (7 derniers jours comparés aux 7 jours d'avant, semaine à la semaine d'avant).
     */
    public function previous(): self
    {
        if (in_array($this->preset, ['this_month', 'last_month'], true)) {
            $start = $this->start->subMonthNoOverflow()->startOfMonth();
            $fullMonth = $this->end->isSameDay($this->end->endOfMonth());
            $end = $fullMonth
                ? $start->endOfMonth()
                : $start->setDay(min($this->end->day, $start->daysInMonth))->endOfDay();

            return new self($start, $end, 'previous');
        }

        if (in_array($this->preset, ['this_year', 'last_year'], true)) {
            $start = $this->start->subYear()->startOfYear();
            $end = $this->end->isSameDay($this->end->endOfYear())
                ? $start->endOfYear()
                : $this->end->subYearNoOverflow()->endOfDay();

            return new self($start, $end, 'previous');
        }

        $end = $this->start->subDay()->endOfDay();

        return new self($end->subDays($this->days() - 1)->startOfDay(), $end, 'previous');
    }

    /**
     * Pas des graphiques : par jour jusqu'à 62 jours, par semaine jusqu'à 26 semaines,
     * par mois au-delà. Un graphique garde ainsi entre 7 et 60 points environ.
     */
    public function granularity(): string
    {
        return match (true) {
            $this->days() <= 62 => 'day',
            $this->days() <= 183 => 'week',
            default => 'month',
        };
    }

    /**
     * Clés des intervalles du graphique, dans l'ordre, avec leur libellé.
     * Les clés correspondent aux expressions SQL de bucketExpression().
     *
     * @return array<string, string>
     */
    public function buckets(): array
    {
        $buckets = [];
        $cursor = $this->start;

        while ($cursor->lessThanOrEqualTo($this->end)) {
            switch ($this->granularity()) {
                case 'day':
                    $buckets[$cursor->format('Y-m-d')] = $cursor->format('d/m');
                    $cursor = $cursor->addDay();
                    break;
                case 'week':
                    $monday = $cursor->startOfWeek(CarbonImmutable::MONDAY);
                    $buckets[$monday->format('Y-m-d')] = 'Sem. du '.$monday->max($this->start)->format('d/m');
                    $cursor = $monday->addWeek();
                    break;
                default:
                    $buckets[$cursor->format('Y-m')] = ucfirst($cursor->locale('fr')->isoFormat('MMM YY'));
                    $cursor = $cursor->startOfMonth()->addMonthNoOverflow();
            }
        }

        return $buckets;
    }

    /** Expression SQL (MySQL) qui ramène une date à la clé de son intervalle. */
    public function bucketExpression(string $column): string
    {
        return match ($this->granularity()) {
            'day' => "DATE_FORMAT($column, '%Y-%m-%d')",
            // Lundi de la semaine : WEEKDAY() vaut 0 le lundi.
            'week' => "DATE_FORMAT(DATE_SUB(DATE($column), INTERVAL WEEKDAY($column) DAY), '%Y-%m-%d')",
            default => "DATE_FORMAT($column, '%Y-%m')",
        };
    }

    /** @return array{0: string, 1: string} bornes pour un whereBetween */
    public function bounds(): array
    {
        return [$this->start->format('Y-m-d H:i:s'), $this->end->format('Y-m-d H:i:s')];
    }

    public function label(): string
    {
        $range = $this->days() === 1
            ? $this->start->format('d/m/Y')
            : 'du '.$this->start->format('d/m/Y').' au '.$this->end->format('d/m/Y');

        return $this->preset === 'custom' || $this->preset === 'previous'
            ? ucfirst($range)
            : self::PRESETS[$this->preset].' ('.$range.')';
    }

    public function toArray(): array
    {
        return [
            'preset' => $this->preset,
            'from' => $this->start->format('Y-m-d'),
            'to' => $this->end->format('Y-m-d'),
            'days' => $this->days(),
            'granularity' => $this->granularity(),
            'label' => $this->label(),
        ];
    }
}
