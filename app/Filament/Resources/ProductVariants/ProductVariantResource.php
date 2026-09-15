<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductVariants;

use App\Filament\Resources\ProductVariants\Pages\EditProductVariant;
use App\Filament\Resources\ProductVariants\RelationManagers\ImagesRelationManager;
use App\Filament\Resources\ProductVariants\Schemas\ProductVariantForm;
use App\Models\ProductVariant;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;

/** Variant edit page, reached from the product's variants tab; not in the navigation. */
final class ProductVariantResource extends Resource
{
    protected static ?string $model = ProductVariant::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'sku';

    public static function getModelLabel(): string
    {
        return __('admin.catalog.variant');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.catalog.variants');
    }

    public static function form(Schema $schema): Schema
    {
        return ProductVariantForm::configure($schema);
    }

    public static function getRelations(): array
    {
        return [ImagesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'edit' => EditProductVariant::route('/{record}/edit'),
        ];
    }
}
