<?php

declare(strict_types=1);

namespace App\Filament\Resources\Quotations\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class QuotationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('items')->withSum('items', 'quantity'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->label(__('admin.common.id'))->sortable(),
                TextColumn::make('created_at')->label(__('admin.common.created_at'))->dateTime('d/m/Y H:i')->sortable(),
                IconColumn::make('read_at')->label(__('admin.common.read'))->boolean()
                    ->getStateUsing(fn ($record): bool => $record->read_at !== null),
                TextColumn::make('customer_full_name')->label(__('admin.customer.full_name'))
                    ->state(fn ($record): string => trim(($record->customer_name ?? '').' '.($record->customer_surname ?? '')))
                    ->searchable(['customer_name', 'customer_surname', 'customer_email']),
                TextColumn::make('customer_company')->label(__('admin.customer.company'))->searchable()->placeholder('-'),
                TextColumn::make('customer_activity')->label(__('admin.customer.activity'))->placeholder('-')->toggleable(),
                TextColumn::make('items_count')->label(__('admin.quotation.items_count'))->sortable(),
                TextColumn::make('items_sum_quantity')->label(__('admin.quotation.total_quantity'))->sortable(),
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
                DeleteAction::make(),
            ]);
    }
}
