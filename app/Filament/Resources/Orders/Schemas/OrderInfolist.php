<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('admin.order.sections.summary'))
                    ->columnSpan(2)
                    ->columns(3)
                    ->components([
                        TextEntry::make('created_at')->label(__('admin.common.created_at'))->dateTime('d/m/Y H:i'),
                        TextEntry::make('status')->label(__('admin.order.status'))->badge()
                            ->formatStateUsing(fn (string $state): string => Order::$status_names[$state] ?? $state)
                            ->color(fn (string $state): string => OrdersTable::statusColor($state)),
                        TextEntry::make('payment_status')->label(__('admin.order.payment_status'))->badge()
                            ->formatStateUsing(fn (string $state): string => Order::$payment_status_names[$state] ?? $state)
                            ->color(fn (string $state): string => $state === 'paid' ? 'success' : 'warning'),
                        TextEntry::make('payment_method')->label(__('admin.order.payment_method'))
                            ->formatStateUsing(fn (?string $state): string => Order::$payment_method_names[$state] ?? (string) $state),
                        TextEntry::make('payment_transaction')->label(__('admin.order.payment_transaction'))->placeholder('-'),
                        TextEntry::make('tracking_code')->label(__('admin.order.tracking_code'))->placeholder('-'),
                        TextEntry::make('notes')->label(__('admin.order.notes'))->placeholder('-')->columnSpanFull(),
                    ]),
                Section::make(__('admin.order.sections.totals'))
                    ->columnSpan(1)
                    ->components([
                        TextEntry::make('items_price')->label(__('admin.order.items_price'))->money('EUR'),
                        TextEntry::make('delivery_cost')->label(__('admin.order.delivery_cost'))->money('EUR'),
                        TextEntry::make('total_price')->label(__('admin.order.total_price'))->money('EUR'),
                        TextEntry::make('total_tax')->label(__('admin.order.total_tax'))->money('EUR'),
                        TextEntry::make('total_taxed_price')->label(__('admin.order.total_taxed_price'))->money('EUR')->weight('bold'),
                    ]),
                Section::make(__('admin.order.sections.customer'))
                    ->columnSpan(2)
                    ->columns(3)
                    ->components([
                        TextEntry::make('customer.company')->label(__('admin.customer.company'))->placeholder('-'),
                        TextEntry::make('customer.full_name')->label(__('admin.customer.full_name')),
                        TextEntry::make('customer.customer_type')->label(__('admin.customer.type'))->placeholder('-'),
                        TextEntry::make('customer.email')->label(__('admin.customer.email'))->copyable(),
                        TextEntry::make('customer.phone')->label(__('admin.customer.phone'))->placeholder('-'),
                        TextEntry::make('customer.activity')->label(__('admin.customer.activity'))->placeholder('-'),
                        TextEntry::make('customer.tax_code')->label(__('admin.customer.tax_code'))->placeholder('-'),
                        TextEntry::make('customer.vat_code')->label(__('admin.customer.vat_code'))->placeholder('-'),
                        TextEntry::make('customer.sdi_code')->label(__('admin.customer.sdi_code'))->placeholder('-'),
                        TextEntry::make('customer.pec')->label(__('admin.customer.pec'))->placeholder('-'),
                        TextEntry::make('customer.ipa_code')->label(__('admin.customer.ipa_code'))->placeholder('-'),
                        TextEntry::make('customer.cig_code')->label(__('admin.customer.cig_code'))->placeholder('-'),
                    ]),
                Section::make(__('admin.order.sections.shipping'))
                    ->columnSpan(1)
                    ->components([
                        TextEntry::make('address')->label(__('admin.customer.address'))->placeholder('-'),
                        TextEntry::make('zip_city')->label(__('admin.customer.city'))
                            ->state(fn (Order $record): string => trim(($record->zip_code ?? '').' '.($record->city ?? '').' '.($record->province ? '('.$record->province.')' : ''))),
                        TextEntry::make('country')->label(__('admin.customer.country'))->placeholder('-'),
                    ]),
                Section::make(__('admin.order.items'))
                    ->columnSpanFull()
                    ->components([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->columns(4)
                            ->components([
                                TextEntry::make('product_name')->label(__('admin.order.product'))->weight('bold'),
                                TextEntry::make('product_sku')->label(__('admin.order.sku')),
                                TextEntry::make('quantity')->label(__('admin.order.quantity')),
                                TextEntry::make('price')->label(__('admin.order.price'))->money('EUR'),
                                TextEntry::make('is_sample')->label(__('admin.order.line_type'))->badge()->formatStateUsing(fn ($state): string => $state ? __('admin.order.sample') : __('admin.order.regular_line'))->color(fn ($state): string => $state ? 'warning' : 'gray'),
                                TextEntry::make('shipping_date')->label(__('admin.order.shipping_date'))->date('d/m/Y')->placeholder('-'),
                                RepeatableEntry::make('articles')
                                    ->label(__('admin.order.articles'))
                                    ->columnSpanFull()
                                    ->columns(5)
                                    ->components([
                                        TextEntry::make('article_sku')->label(__('admin.order.sku')),
                                        TextEntry::make('article_color_label')->label(__('admin.order.color'))->placeholder('-'),
                                        TextEntry::make('article_size_label')->label(__('admin.order.size'))->placeholder('-'),
                                        TextEntry::make('quantity')->label(__('admin.order.quantity')),
                                        TextEntry::make('unit_price')->label(__('admin.order.unit_price'))->money('EUR'),
                                    ]),
                                RepeatableEntry::make('customizations')
                                    ->label(__('admin.order.printings'))
                                    ->columnSpanFull()
                                    ->columns(6)
                                    ->components([
                                        TextEntry::make('technique_label')->label(__('admin.catalog.technique'))->placeholder('-'),
                                        TextEntry::make('position_label')->label(__('admin.catalog.position'))->placeholder('-'),
                                        TextEntry::make('area_label')->label(__('admin.order.area'))->placeholder('-'),
                                        TextEntry::make('option_label')->label(__('admin.order.option'))->placeholder('-'),
                                        TextEntry::make('quantity')->label(__('admin.order.quantity'))->placeholder('-'),
                                        TextEntry::make('price')->label(__('admin.order.price'))->money('EUR')->placeholder('-'),
                                        TextEntry::make('family')->label(__('admin.catalog.family'))->badge()->placeholder('-'),
                                        TextEntry::make('packaging_price')->label(__('admin.order.packaging'))->money('EUR')->placeholder('-'),
                                        TextEntry::make('label')->label(__('admin.order.sold_as'))->columnSpan(3),
                                        TextEntry::make('file')->label(__('admin.order.artwork'))->placeholder(__('admin.order.artwork_missing'))->formatStateUsing(fn (?string $state): string => $state ? basename($state) : ''),
                                    ]),
                                RepeatableEntry::make('extras')
                                    ->label(__('admin.order.extras'))
                                    ->columnSpanFull()
                                    ->columns(3)
                                    ->placeholder('-')
                                    ->components([
                                        TextEntry::make('type')->label(__('admin.order.extra_type'))->badge()->formatStateUsing(fn (string $state): string => __('admin.order.extra_types.'.$state)),
                                        TextEntry::make('label')->hiddenLabel(),
                                        TextEntry::make('price')->label(__('admin.order.price'))->money('EUR'),
                                    ]),
                            ]),
                    ]),
                Section::make(__('admin.order.files'))
                    ->columnSpanFull()
                    ->components([
                        RepeatableEntry::make('media')
                            ->hiddenLabel()
                            ->columns(3)
                            ->placeholder(__('admin.order.no_files'))
                            ->components([
                                TextEntry::make('file_name')->label(__('admin.order.actions.file'))
                                    ->url(fn ($record): string => (string) $record->getUrl(), shouldOpenInNewTab: true)
                                    ->icon(Heroicon::OutlinedArrowDownTray),
                                TextEntry::make('mime_type')->label('Tipo'),
                                TextEntry::make('created_at')->label(__('admin.common.created_at'))->dateTime('d/m/Y H:i'),
                            ]),
                    ]),
            ]);
    }
}
