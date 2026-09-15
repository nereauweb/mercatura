<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

final class OrdersByStatusChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    public function getHeading(): string
    {
        return __('admin.reports.orders_by_status');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $rows = Order::query()->select('status', DB::raw('COUNT(*) as n'))->groupBy('status')->get()->keyBy('status');
        $labels = [];
        $data = [];
        foreach (OrderStatus::cases() as $status) {
            $labels[] = $status->getLabel();
            $data[] = (int) ($rows[$status->value]->n ?? 0);
        }

        return [
            'labels' => $labels,
            'datasets' => [['data' => $data, 'backgroundColor' => ['#9ca3af', '#f59e0b', '#fbbf24', '#3b82f6', '#60a5fa', '#2563eb', '#16a34a', '#dc2626']]],
        ];
    }
}
