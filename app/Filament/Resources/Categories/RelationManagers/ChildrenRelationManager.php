<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\RelationManagers;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use App\Support\CatalogCache;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Sub-categories of a root, drag-and-drop ordered (the legacy route for this had no method). */
final class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.catalog.children');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Category && $ownerRecord->parent_id === null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                TextColumn::make('name')->label(__('admin.catalog.name')),
                IconColumn::make('active')->label(__('admin.catalog.active'))->boolean(),
                TextColumn::make('position')->label(__('admin.catalog.ordering')),
            ])
            ->recordActions([
                Action::make('edit')->label(__('filament-actions::edit.single.label'))->icon('heroicon-o-pencil-square')
                    ->url(fn (Category $record): string => CategoryResource::getUrl('edit', ['record' => $record])),
            ]);
    }

    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        parent::reorderTable($order, $draggedRecordKey);
        CatalogCache::flush();
    }
}
