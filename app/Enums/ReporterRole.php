<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** messages.reporter_role, decided server side. Admins never write messages (docs/06_USER_REPORTS.md §2.9). */
enum ReporterRole: string implements HasLabel
{
    case Customer = 'customer';
    case Guest = 'guest';

    public function getLabel(): string
    {
        return __('admin.message.roles.'.$this->value);
    }
}
