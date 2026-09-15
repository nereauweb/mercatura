<?php

declare(strict_types=1);

namespace App\Http\Livewire;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

final class FrontendOrdersTable extends FrontendCustomerTable
{
    protected function query(): Builder
    {
        return Order::query()->where('user_id', Auth::id());
    }

    protected function columns(): array
    {
        $labels = __('frontend.account.columns');

        return ['id' => $labels['id'], 'total_taxed_price' => $labels['total'], 'created_at' => $labels['created'], 'updated_at' => $labels['updated'], 'status' => $labels['status'], 'payment_status' => $labels['payment']];
    }

    protected function view(): string
    {
        return 'livewire.frontend-orders-table';
    }

    protected function rowUrl(object $row): string
    {
        return route('frontend.auth.order.show', $row->id);
    }

    protected function formatRow(object $row): array
    {
        return [
            'id' => (string) $row->id,
            'total_taxed_price' => number_format((float) $row->total_taxed_price, 2, ',', '.').' €',
            'created_at' => (string) $row->created_at?->format('d/m/Y H:i'),
            'updated_at' => (string) $row->updated_at?->format('d/m/Y H:i'),
            'status' => (string) (Order::$status_names[$row->status] ?? $row->status),
            'payment_status' => (string) (Order::$payment_status_names[$row->payment_status] ?? $row->payment_status),
        ];
    }
}
