<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\Tables;

use App\Enums\MessageType;
use App\Enums\ReportKind;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                TextColumn::make('type')->label(__('admin.message.type'))->badge()
                    ->color(fn (MessageType $state): string => $state === MessageType::Report ? 'warning' : 'gray'),
                TextColumn::make('kind')->label(__('admin.message.kind'))->placeholder('-')->toggleable(),
                TextColumn::make('media_count')->label(__('admin.message.attachments'))->counts('media')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? (string) $state : '')->toggleable(),
                TextColumn::make('full_name')->label(__('admin.customer.full_name'))
                    ->state(fn ($record): string => trim(($record->name ?? '').' '.($record->surname ?? '')))
                    ->searchable(['name', 'surname', 'email']),
                TextColumn::make('email')->label(__('admin.customer.email'))->copyable(),
                TextColumn::make('company')->label(__('admin.customer.company'))->placeholder('-')->toggleable(),
                TextColumn::make('subject')->label(__('admin.message.subject'))->searchable()->limit(50),
                TextColumn::make('message')->label(__('admin.message.message'))->limit(60)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->label(__('admin.message.type'))->options(MessageType::options()),
                SelectFilter::make('kind')->label(__('admin.message.kind'))->options(ReportKind::options()),
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
