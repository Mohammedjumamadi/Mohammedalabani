<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** تجميع السجلات حسب اليوم أو الشهر بطريقة تعمل على MySQL/MariaDB وSQLite معًا. */
class DateBuckets
{
    /** @return array{labels: list<string>, counts: list<int>} آخر $months شهرًا (شهر بشهر) */
    public static function monthly(Builder $query, string $column = 'created_at', int $months = 6): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $rows = self::grouped(clone $query, $column, 'month', $start);

        $labels = [];
        $counts = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $labels[] = $month->translatedFormat('M Y');
            $counts[] = (int) ($rows[$month->format('Y-m')] ?? 0);
        }

        return ['labels' => $labels, 'counts' => $counts];
    }

    /** @return array{labels: list<string>, counts: list<int>} آخر $days يومًا */
    public static function daily(Builder $query, string $column = 'created_at', int $days = 14): array
    {
        $start = now()->startOfDay()->subDays($days - 1);
        $rows = self::grouped(clone $query, $column, 'day', $start);

        $labels = [];
        $counts = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $labels[] = $day->translatedFormat('j M');
            $counts[] = (int) ($rows[$day->format('Y-m-d')] ?? 0);
        }

        return ['labels' => $labels, 'counts' => $counts];
    }

    /** @return array<string, int> */
    private static function grouped(Builder $query, string $column, string $unit, Carbon $since): array
    {
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $format = $unit === 'month' ? '%Y-%m' : '%Y-%m-%d';
        $wrapped = $query->getQuery()->getGrammar()->wrap($column);
        $expression = $sqlite ? "strftime('{$format}', {$wrapped})" : "DATE_FORMAT({$wrapped}, '{$format}')";

        return $query->where($column, '>=', $since)
            ->selectRaw("{$expression} as bucket, count(*) as total")
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->all();
    }
}
