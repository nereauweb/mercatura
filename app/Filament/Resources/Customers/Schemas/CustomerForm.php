<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Schemas;

use App\Enums\CustomerType;
use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * The customer profile with the same rules as the storefront
 * (App\Support\CustomerFormRules): fiscal fields depend on the type,
 * billing address required, shipping optional with "copy from billing".
 * Addresses live in customer_addresses and are saved by EditCustomer.
 */
final class CustomerForm
{
    private const ADDRESS_FIELDS = ['address', 'city', 'province', 'zip_code', 'country', 'notes'];

    public static function configure(Schema $schema): Schema
    {
        $requiresVat = fn (Get $get): bool => Customer::requiresVat((string) $get('customer_type'));
        $isPublicAdmin = fn (Get $get): bool => Customer::isPublicAdmin((string) $get('customer_type'));
        $isPrivate = fn (Get $get): bool => (string) $get('customer_type') === Customer::TYPE_PRIVATE;
        $hasType = fn (Get $get): bool => (string) $get('customer_type') !== '';

        return $schema
            ->columns(2)
            ->components([
                Section::make(__('admin.customer.sections.identity'))
                    ->columns(2)
                    ->components([
                        Select::make('customer_type')->label(__('admin.customer.type'))->options(CustomerType::options())->required()->live()->native(false),
                        Select::make('activity')->label(__('admin.customer.activity'))->options(array_combine(Customer::$activities, Customer::$activities))->searchable()->native(false),
                        TextInput::make('name')->label(__('admin.customer.name'))->required()->maxLength(64),
                        TextInput::make('surname')->label(__('admin.customer.surname'))->required()->maxLength(64),
                        TextInput::make('email')->label(__('admin.customer.email'))->email()->required()->maxLength(64),
                        TextInput::make('phone')->label(__('admin.customer.phone'))->required()->maxLength(50)->regex('/^[+]?[0-9][0-9\s\-()]{7,22}$/'),
                        TextInput::make('company')->label(__('admin.customer.company'))->maxLength(64)
                            ->visible($requiresVat)->required($requiresVat),
                    ]),
                Section::make(__('admin.customer.sections.fiscal'))
                    ->columns(2)
                    ->components([
                        TextInput::make('tax_code')->label(__('admin.customer.tax_code'))->maxLength(16)
                            ->visible($hasType)->required($isPrivate)
                            ->regex('/^(?:[A-Za-z0-9]{16}|[0-9]{11})$/')->helperText(__('admin.customer.hints.tax_code')),
                        TextInput::make('vat_code')->label(__('admin.customer.vat_code'))->maxLength(11)
                            ->visible($requiresVat)->required($requiresVat)
                            ->regex('/^[0-9]{11}$/')->helperText(__('admin.customer.hints.vat_code')),
                        TextInput::make('pec')->label(__('admin.customer.pec'))->email()->maxLength(128)
                            ->visible($requiresVat)
                            ->required(fn (Get $get): bool => $requiresVat($get) && (string) $get('customer_type') !== CustomerType::Company->value),
                        TextInput::make('sdi_code')->label(__('admin.customer.sdi_code'))->maxLength(7)
                            ->visible($requiresVat)->regex('/^(?:[A-Za-z0-9]{7}|[A-Za-z0-9]{6}|OO[0-9]{11})$/'),
                        TextInput::make('ipa_code')->label(__('admin.customer.ipa_code'))->maxLength(6)
                            ->visible($isPublicAdmin)->required($isPublicAdmin)
                            ->regex('/^[A-Za-z0-9]{6}$/')->helperText(__('admin.customer.hints.ipa_code')),
                        TextInput::make('cig_code')->label(__('admin.customer.cig_code'))->maxLength(10)
                            ->visible($isPublicAdmin)->required($isPublicAdmin)
                            ->regex('/^[A-Za-z0-9]{10}$/')->helperText(__('admin.customer.hints.cig_code')),
                    ]),
                Section::make(__('admin.customer.billing_address'))
                    ->columns(2)
                    ->components(self::addressFields('billing', true)),
                Section::make(__('admin.customer.shipping_address'))
                    ->columns(2)
                    ->components([
                        Actions::make([
                            Action::make('copyBilling')->label(__('admin.customer.copy_billing'))->color('gray')
                                ->action(function (Get $get, Set $set): void {
                                    foreach (self::ADDRESS_FIELDS as $field) {
                                        $set('shipping.'.$field, $get('billing.'.$field));
                                    }
                                }),
                        ])->columnSpanFull(),
                        ...self::addressFields('shipping', false),
                    ]),
                Section::make(__('admin.customer.sections.notes'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        Textarea::make('shared_notes')->label(__('admin.customer.shared_notes'))->rows(3),
                        Textarea::make('internal_notes')->label(__('admin.customer.internal_notes'))->rows(3),
                    ]),
            ]);
    }

    /** @return list<\Filament\Schemas\Components\Component> */
    private static function addressFields(string $prefix, bool $required): array
    {
        return [
            TextInput::make($prefix.'.address')->label(__('admin.customer.address'))->required($required)->maxLength(255)->columnSpanFull(),
            TextInput::make($prefix.'.city')->label(__('admin.customer.city'))->required($required)->maxLength(64),
            Select::make($prefix.'.province')->label(__('admin.customer.province'))->options(array_combine(Customer::PROVINCES, Customer::PROVINCES))->required($required)->searchable()->native(false),
            TextInput::make($prefix.'.zip_code')->label(__('admin.customer.zip_code'))->required($required)->maxLength(16),
            TextInput::make($prefix.'.country')->label(__('admin.customer.country'))->required($required)->maxLength(64)->default('Italia'),
            Textarea::make($prefix.'.notes')->label(__('admin.customer.notes_address'))->rows(2)->maxLength(500)->columnSpanFull(),
        ];
    }
}
