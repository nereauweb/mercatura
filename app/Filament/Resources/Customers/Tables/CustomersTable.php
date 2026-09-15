<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->label(__('admin.common.id'))->sortable(),
                TextColumn::make('full_name')->label(__('admin.customer.full_name'))
                    ->state(fn (Customer $record): string => trim(($record->name ?? '').' '.($record->surname ?? '')))
                    ->searchable(['name', 'surname']),
                TextColumn::make('company')->label(__('admin.customer.company'))->searchable()->placeholder('-'),
                TextColumn::make('customer_type')->label(__('admin.customer.type'))->badge()->color('gray')->placeholder('-'),
                TextColumn::make('email')->label(__('admin.customer.email'))->searchable()->copyable(),
                TextColumn::make('phone')->label(__('admin.customer.phone'))->placeholder('-')->toggleable(),
                TextColumn::make('activity')->label(__('admin.customer.activity'))->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label(__('admin.common.created_at'))->dateTime('d/m/Y')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('customer_type')->label(__('admin.customer.type'))
                    ->options(array_combine(Customer::CUSTOMER_TYPES, Customer::CUSTOMER_TYPES)),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
