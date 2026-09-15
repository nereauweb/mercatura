<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

final class OrdersByMonthChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string
    {
        return __('admin.reports.orders_by_month');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $months = self::lastMonths();
        $rows = Order::query()->where('status', '!=', 'cancelled')->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"), DB::raw('COUNT(*) as n'), DB::raw('SUM(total_taxed_price) as total'))
            ->groupBy('ym')->get()->keyBy('ym');

        return [
            'labels' => array_values($months),
            'datasets' => [
                ['label' => __('admin.reports.orders_count'), 'data' => array_map(fn (string $ym) => (int) ($rows[$ym]->n ?? 0), array_keys($months)), 'backgroundColor' => '#2563eb'],
                ['label' => __('admin.reports.orders_total'), 'data' => array_map(fn (string $ym) => round((float) ($rows[$ym]->total ?? 0), 2), array_keys($months)), 'backgroundColor' => '#93c5fd', 'yAxisID' => 'y1'],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['beginAtZero' => true], 'y1' => ['beginAtZero' => true, 'position' => 'right', 'grid' => ['drawOnChartArea' => false]]]];
    }

    /** @return array<string, string> "Y-m" => "M Y" for the last 12 months */
    public static function lastMonths(): array
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i)->startOfMonth();
            $months[$date->format('Y-m')] = $date->translatedFormat('M Y');
        }

        return $months;
    }
}
