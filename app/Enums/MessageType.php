<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** messages.type: a contact-form message or a user report (docs/06_USER_REPORTS.md). */
enum MessageType: string implements HasLabel
{
    case Contact = 'contact';
    case Report = 'report';

    public function getLabel(): string
    {
        return __('admin.message.types.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c) => $c->getLabel(), self::cases()));
    }
}
