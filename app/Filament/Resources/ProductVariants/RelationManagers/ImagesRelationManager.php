<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductVariants\RelationManagers;

use App\Support\CatalogCache;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Alt text of the variant images (media.custom_properties.alt), read by the storefront gallery. */
final class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.catalog.images_alt');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('alt')->label(__('admin.catalog.alt'))->maxLength(255)
                ->formatStateUsing(fn (?Media $record): ?string => $record?->getCustomProperty('alt')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('collection_name', 'image'))
            ->defaultSort('order_column')
            ->columns([
                ImageColumn::make('preview')->label(__('admin.catalog.cover'))->getStateUsing(fn (Media $record): string => $record->getUrl('thumb'))->square()->size(48),
                TextColumn::make('file_name')->label(__('admin.order.actions.file')),
                TextColumn::make('alt')->label(__('admin.catalog.alt'))->getStateUsing(fn (Media $record): ?string => $record->getCustomProperty('alt'))->placeholder('-'),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('lg')
                    ->using(function (Media $record, array $data): Media {
                        $record->setCustomProperty('alt', trim((string) ($data['alt'] ?? '')))->save();
                        CatalogCache::flush();

                        return $record;
                    }),
            ]);
    }
}
