<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class MessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->label(__('admin.common.id'))->sortable(),
                TextColumn::make('created_at')->label(__('admin.common.created_at'))->dateTime('d/m/Y H:i')->sortable(),
                IconColumn::make('read_at')->label(__('admin.common.read'))->boolean()
                    ->getStateUsing(fn ($record): bool => $record->read_at !== null),
                TextColumn::make('full_name')->label(__('admin.customer.full_name'))
                    ->state(fn ($record): string => trim(($record->name ?? '').' '.($record->surname ?? '')))
                    ->searchable(['name', 'surname', 'email']),
                TextColumn::make('email')->label(__('admin.customer.email'))->copyable(),
                TextColumn::make('company')->label(__('admin.customer.company'))->placeholder('-')->toggleable(),
                TextColumn::make('subject')->label(__('admin.message.subject'))->searchable()->limit(50),
                TextColumn::make('message')->label(__('admin.message.message'))->limit(60)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('read')->label(__('admin.common.read'))
                    ->placeholder(__('admin.common.all'))
                    ->trueLabel(__('admin.common.read'))
                    ->falseLabel(__('admin.common.unread'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('read_at'),
                        false: fn (Builder $query) => $query->whereNull('read_at'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
