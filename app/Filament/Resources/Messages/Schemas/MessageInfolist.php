<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class MessageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.message.sections.sender'))
                    ->columns(3)
                    ->components([
                        TextEntry::make('created_at')->label(__('admin.common.created_at'))->dateTime('d/m/Y H:i'),
                        TextEntry::make('read_at')->label(__('admin.common.read_at'))->dateTime('d/m/Y H:i')->placeholder('-'),
                        TextEntry::make('company')->label(__('admin.customer.company'))->placeholder('-'),
                        TextEntry::make('name')->label(__('admin.customer.name'))->placeholder('-'),
                        TextEntry::make('surname')->label(__('admin.customer.surname'))->placeholder('-'),
                        TextEntry::make('activity')->label(__('admin.customer.activity'))->placeholder('-'),
                        TextEntry::make('email')->label(__('admin.customer.email'))->copyable(),
                        TextEntry::make('phone')->label(__('admin.customer.phone'))->placeholder('-'),
                        IconEntry::make('subscribe_newsletter')->label(__('admin.message.subscribe_newsletter'))->boolean(),
                    ]),
                Section::make(__('admin.message.sections.content'))
                    ->components([
                        TextEntry::make('subject')->label(__('admin.message.subject'))->weight('bold'),
                        TextEntry::make('message')->label(__('admin.message.message'))->prose(),
                    ]),
            ]);
    }
}
