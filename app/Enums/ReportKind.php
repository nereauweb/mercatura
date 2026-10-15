<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** messages.kind of a report; the values match the usual helpdesk kinds so a forward maps one to one. */
enum ReportKind: string implements HasLabel
{
    case Bug = 'bug';
    case Request = 'request';
    case Question = 'question';
    case Feedback = 'feedback';

    public function getLabel(): string
    {
        return __('admin.message.kinds.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c) => $c->getLabel(), self::cases()));
    }
}
