<?php

namespace Tests\Unit;

use App\Support\Period;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Périodes du tableau de bord : bornes, comparaison, intervalles, validation. */
class PeriodTest extends TestCase
{
    /** Jeudi 19 novembre 2026. */
    private function today(): CarbonImmutable
    {
        return CarbonImmutable::create(2026, 11, 19);
    }

    public static function presets(): array
    {
        return [
            'aujourd\'hui' => ['today', '2026-11-19', '2026-11-19'],
            'hier' => ['yesterday', '2026-11-18', '2026-11-18'],
            '7 derniers jours' => ['last_7_days', '2026-11-13', '2026-11-19'],
            '30 derniers jours' => ['last_30_days', '2026-10-21', '2026-11-19'],
            'cette semaine, du lundi' => ['this_week', '2026-11-16', '2026-11-19'],
            'semaine dernière' => ['last_week', '2026-11-09', '2026-11-15'],
            'ce mois-ci' => ['this_month', '2026-11-01', '2026-11-19'],
            'mois dernier' => ['last_month', '2026-10-01', '2026-10-31'],
            'cette année' => ['this_year', '2026-01-01', '2026-11-19'],
            'année dernière' => ['last_year', '2025-01-01', '2025-12-31'],
        ];
    }

    #[DataProvider('presets')]
    public function test_chaque_preset_couvre_les_bons_jours(string $preset, string $from, string $to): void
    {
        $period = Period::preset($preset, $this->today());

        $this->assertSame($from.' 00:00:00', $period->start->format('Y-m-d H:i:s'));
        $this->assertSame($to.' 23:59:59', $period->end->format('Y-m-d H:i:s'));
    }

    public static function previousPeriods(): array
    {
        return [
            '7 jours comparés aux 7 jours d\'avant' => ['last_7_days', '2026-11-06', '2026-11-12'],
            'aujourd\'hui comparé à hier' => ['today', '2026-11-18', '2026-11-18'],
            'semaine en cours, même nombre de jours' => ['this_week', '2026-11-12', '2026-11-15'],
            'mois en cours, même tranche du mois précédent' => ['this_month', '2026-10-01', '2026-10-19'],
            'mois complet, mois complet précédent' => ['last_month', '2026-09-01', '2026-09-30'],
            'année en cours, même tranche de l\'année précédente' => ['this_year', '2025-01-01', '2025-11-19'],
            'année complète, année complète précédente' => ['last_year', '2024-01-01', '2024-12-31'],
        ];
    }

    #[DataProvider('previousPeriods')]
    public function test_la_periode_de_comparaison_est_la_bonne(string $preset, string $from, string $to): void
    {
        $previous = Period::preset($preset, $this->today())->previous();

        $this->assertSame($from, $previous->start->format('Y-m-d'));
        $this->assertSame($to, $previous->end->format('Y-m-d'));
    }

    public function test_un_mois_de_31_jours_se_compare_a_un_mois_plus_court_sans_debordement(): void
    {
        // Le 31 mars, « ce mois-ci » se compare au 1er au 28 février (2026 n'est pas bissextile).
        $previous = Period::preset('this_month', CarbonImmutable::create(2026, 3, 31))->previous();

        $this->assertSame('2026-02-01', $previous->start->format('Y-m-d'));
        $this->assertSame('2026-02-28', $previous->end->format('Y-m-d'));
    }

    public function test_une_periode_personnalisee_et_sa_comparaison(): void
    {
        $period = Period::custom('2026-10-01', '2026-10-30');

        $this->assertSame(30, $period->days());
        $this->assertSame('custom', $period->preset);
        $this->assertSame('2026-09-01', $period->previous()->start->format('Y-m-d'));
        $this->assertSame('2026-09-30', $period->previous()->end->format('Y-m-d'));
    }

    public static function invalidCustom(): array
    {
        return [
            'début après la fin' => ['2026-10-30', '2026-10-01'],
            'date inexistante' => ['2026-02-30', '2026-03-05'],
            'mauvais format' => ['01/10/2026', '2026-10-30'],
            'plus de deux ans' => ['2023-01-01', '2026-01-02'],
        ];
    }

    #[DataProvider('invalidCustom')]
    public function test_une_periode_personnalisee_incoherente_est_refusee(string $from, string $to): void
    {
        $this->expectException(InvalidArgumentException::class);

        Period::custom($from, $to);
    }

    public function test_un_preset_inconnu_est_refuse(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Period::preset('depuis_toujours', $this->today());
    }

    public function test_le_pas_des_graphiques_s_adapte_a_la_duree(): void
    {
        $this->assertSame('day', Period::custom('2026-10-01', '2026-11-30')->granularity());
        $this->assertSame('week', Period::custom('2026-06-01', '2026-11-19')->granularity());
        $this->assertSame('month', Period::preset('last_year', $this->today())->granularity());
    }

    public function test_les_intervalles_couvrent_toute_la_periode_sans_trou(): void
    {
        $days = Period::preset('last_7_days', $this->today())->buckets();
        $this->assertCount(7, $days);
        $this->assertSame('2026-11-13', array_key_first($days));
        $this->assertSame('19/11', end($days));

        $months = Period::preset('last_year', $this->today())->buckets();
        $this->assertCount(12, $months);
        $this->assertSame('2025-01', array_key_first($months));

        // Les semaines commencent le lundi ; la première peut commencer avant la période.
        $weeks = Period::custom('2026-06-03', '2026-11-19')->buckets();
        $this->assertSame('2026-06-01', array_key_first($weeks));
        $this->assertSame('Sem. du 03/06', reset($weeks));
    }
}
