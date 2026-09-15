<?php

declare(strict_types=1);

namespace App\Filament\Resources\Quotations;

use App\Filament\Resources\Quotations\Pages\ListQuotations;
use App\Filament\Resources\Quotations\Pages\ViewQuotation;
use App\Filament\Resources\Quotations\Schemas\QuotationInfolist;
use App\Filament\Resources\Quotations\Tables\QuotationsTable;
use App\Models\Quotation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/** Quotation requests from the storefront, read-only; opening one marks it read. */
final class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): string
    {
        return __('admin.nav.sales');
    }

    public static function getModelLabel(): string
    {
        return __('admin.quotation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.quotation.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Quotation::query()->whereNull('read_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return __('admin.common.unread');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return QuotationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuotationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuotations::route('/'),
            'view' => ViewQuotation::route('/{record}'),
        ];
    }
}
