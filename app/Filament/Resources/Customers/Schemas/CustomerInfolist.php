<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\CustomerAddress;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make(__('admin.customer.sections.identity'))
                    ->columns(2)
                    ->components([
                        TextEntry::make('customer_type')->label(__('admin.customer.type'))->badge()->color('gray')->placeholder('-'),
                        TextEntry::make('company')->label(__('admin.customer.company'))->placeholder('-'),
                        TextEntry::make('name')->label(__('admin.customer.name'))->placeholder('-'),
                        TextEntry::make('surname')->label(__('admin.customer.surname'))->placeholder('-'),
                        TextEntry::make('email')->label(__('admin.customer.email'))->copyable(),
                        TextEntry::make('phone')->label(__('admin.customer.phone'))->placeholder('-'),
                        TextEntry::make('activity')->label(__('admin.customer.activity'))->placeholder('-'),
                        TextEntry::make('user.email')->label(__('admin.customer.user'))->placeholder('-'),
                        TextEntry::make('created_at')->label(__('admin.common.created_at'))->dateTime('d/m/Y H:i'),
                    ]),
                Section::make(__('admin.customer.sections.fiscal'))
                    ->columns(2)
                    ->components([
                        TextEntry::make('tax_code')->label(__('admin.customer.tax_code'))->placeholder('-'),
                        TextEntry::make('vat_code')->label(__('admin.customer.vat_code'))->placeholder('-'),
                        TextEntry::make('sdi_code')->label(__('admin.customer.sdi_code'))->placeholder('-'),
                        TextEntry::make('pec')->label(__('admin.customer.pec'))->placeholder('-'),
                        TextEntry::make('ipa_code')->label(__('admin.customer.ipa_code'))->placeholder('-'),
                        TextEntry::make('cig_code')->label(__('admin.customer.cig_code'))->placeholder('-'),
                    ]),
                Section::make(__('admin.customer.billing_address'))
                    ->components([
                        TextEntry::make('billing_address_text')->hiddenLabel()
                            ->state(fn ($record): string => self::formatAddress($record->billing_address))->placeholder('-'),
                    ]),
                Section::make(__('admin.customer.shipping_address'))
                    ->components([
                        TextEntry::make('shipping_address_text')->hiddenLabel()
                            ->state(fn ($record): string => self::formatAddress($record->shipping_address))->placeholder('-'),
                    ]),
                Section::make(__('admin.customer.sections.notes'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        TextEntry::make('shared_notes')->label(__('admin.customer.shared_notes'))->placeholder('-'),
                        TextEntry::make('internal_notes')->label(__('admin.customer.internal_notes'))->placeholder('-'),
                    ]),
            ]);
    }

    private static function formatAddress(mixed $address): string
    {
        if (! $address instanceof CustomerAddress) {
            return '';
        }

        return trim(implode(', ', array_filter([
            $address->address,
            trim(($address->zip_code ?? '').' '.($address->city ?? '').' '.($address->province ? '('.$address->province.')' : '')),
            $address->country,
        ])), ', ');
    }
}
