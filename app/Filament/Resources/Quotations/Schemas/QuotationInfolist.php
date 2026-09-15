<?php

declare(strict_types=1);

namespace App\Filament\Resources\Quotations\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class QuotationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.quotation.sections.customer'))
                    ->columns(3)
                    ->components([
                        TextEntry::make('created_at')->label(__('admin.common.created_at'))->dateTime('d/m/Y H:i'),
                        TextEntry::make('read_at')->label(__('admin.common.read_at'))->dateTime('d/m/Y H:i')->placeholder('-'),
                        TextEntry::make('customer_type')->label(__('admin.customer.type'))->placeholder('-'),
                        TextEntry::make('customer_company')->label(__('admin.customer.company'))->placeholder('-'),
                        TextEntry::make('customer_name')->label(__('admin.customer.name'))->placeholder('-'),
                        TextEntry::make('customer_surname')->label(__('admin.customer.surname'))->placeholder('-'),
                        TextEntry::make('customer_email')->label(__('admin.customer.email'))->copyable(),
                        TextEntry::make('customer_phone')->label(__('admin.customer.phone'))->placeholder('-'),
                        TextEntry::make('customer_activity')->label(__('admin.customer.activity'))->placeholder('-'),
                    ]),
                Section::make(__('admin.quotation.sections.items'))
                    ->components([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->columns(6)
                            ->components([
                                ImageEntry::make('image')->hiddenLabel()->height(64)->placeholder('-'),
                                TextEntry::make('name')->label(__('admin.order.product'))->weight('bold'),
                                TextEntry::make('sku')->label(__('admin.order.sku')),
                                TextEntry::make('quantity')->label(__('admin.order.quantity')),
                                TextEntry::make('color')->label(__('admin.order.color'))->placeholder('-'),
                                TextEntry::make('size')->label(__('admin.order.size'))->placeholder('-'),
                                TextEntry::make('printing')->label(__('admin.quotation.printing'))->placeholder('-'),
                                TextEntry::make('notes')->label(__('admin.quotation.notes'))->placeholder('-')->columnSpan(5),
                            ]),
                    ]),
            ]);
    }
}
