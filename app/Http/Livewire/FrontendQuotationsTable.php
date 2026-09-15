<?php

declare(strict_types=1);

namespace App\Http\Livewire;

use App\Models\Quotation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

final class FrontendQuotationsTable extends FrontendCustomerTable
{
    protected function query(): Builder
    {
        return Quotation::query()->where('customer_email', Auth::user()?->email);
    }

    protected function columns(): array
    {
        $labels = __('frontend.account.columns');

        return ['id' => $labels['id'], 'created_at' => $labels['created'], 'updated_at' => $labels['updated']];
    }

    protected function view(): string
    {
        return 'livewire.frontend-quotations-table';
    }

    protected function formatRow(object $row): array
    {
        return [
            'id' => (string) $row->id,
            'created_at' => (string) $row->created_at?->format('d/m/Y H:i'),
            'updated_at' => (string) $row->updated_at?->format('d/m/Y H:i'),
        ];
    }
}
