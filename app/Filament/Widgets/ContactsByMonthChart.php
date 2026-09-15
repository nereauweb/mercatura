<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Message;
use App\Models\Quotation;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

final class ContactsByMonthChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    public function getHeading(): string
    {
        return __('admin.reports.contacts_by_month');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $months = OrdersByMonthChart::lastMonths();
        $since = now()->subMonths(11)->startOfMonth();
        $quotations = Quotation::query()->where('created_at', '>=', $since)->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"), DB::raw('COUNT(*) as n'))->groupBy('ym')->get()->keyBy('ym');
        $messages = Message::query()->where('created_at', '>=', $since)->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"), DB::raw('COUNT(*) as n'))->groupBy('ym')->get()->keyBy('ym');

        return [
            'labels' => array_values($months),
            'datasets' => [
                ['label' => __('admin.reports.quotations'), 'data' => array_map(fn (string $ym) => (int) ($quotations[$ym]->n ?? 0), array_keys($months)), 'borderColor' => '#2563eb', 'fill' => false],
                ['label' => __('admin.reports.messages'), 'data' => array_map(fn (string $ym) => (int) ($messages[$ym]->n ?? 0), array_keys($months)), 'borderColor' => '#16a34a', 'fill' => false],
            ],
        ];
    }
}
