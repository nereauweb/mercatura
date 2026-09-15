<?php

declare(strict_types=1);

namespace App\Http\Livewire;

use App\Models\Message;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

final class FrontendContactsTable extends FrontendCustomerTable
{
    protected function query(): Builder
    {
        return Message::query()->where('email', Auth::user()?->email);
    }

    protected function columns(): array
    {
        $labels = __('frontend.account.columns');

        return ['id' => $labels['id'], 'created_at' => $labels['sent'], 'read_at' => $labels['read'], 'subject' => $labels['subject'], 'message' => $labels['message']];
    }

    protected function view(): string
    {
        return 'livewire.frontend-contacts-table';
    }

    protected function rowUrl(object $row): string
    {
        return route('frontend.contacts.show', $row->id);
    }

    protected function formatRow(object $row): array
    {
        return [
            'id' => (string) $row->id,
            'created_at' => (string) $row->created_at?->format('d/m/Y H:i'),
            'read_at' => $row->read_at ? (string) $row->read_at->format('d/m/Y H:i') : '',
            'subject' => (string) $row->subject,
            'message' => Str::limit(strip_tags((string) $row->message), 80),
        ];
    }
}
