<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductVariants\RelationManagers;

use App\Actions\Catalog\SetDefaultCustomization;
use App\Models\Customizations\Customization;
use App\Models\ProductVariant;
use App\Support\CatalogCache;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The customizations of a variant (docs/03_CUSTOMIZATIONS.md v2c.6): the
 * tree technique/position → areas → options → tiers, editable. Rows created
 * here carry the manual source and are never touched by imports; rows
 * received from an import are overwritten by the next one unless locked.
 */
final class CustomizationsRelationManager extends RelationManager
{
    protected static string $relationship = 'customizations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.catalog.customization.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('technique_label')->label(__('admin.catalog.technique'))->required()->maxLength(512),
            TextInput::make('position_label')->label(__('admin.catalog.position'))->required()->maxLength(512),
            TextInput::make('family')->label(__('admin.catalog.family'))->helperText(__('admin.catalog.customization.family_hint'))->maxLength(32),
            TextInput::make('minimum_quantity')->label(__('admin.catalog.customization.minimum'))->integer()->minValue(0)->default(0)->required(),
            TextInput::make('processing_days')->label(__('admin.catalog.customization.days'))->integer()->minValue(0)->default(0)->required(),
            Toggle::make('has_packaging')->label(__('admin.catalog.customization.packaging'))->inline(false),
            Section::make(__('admin.catalog.customization.areas'))->columnSpanFull()->components([
                Repeater::make('areas')->relationship()->hiddenLabel()->minItems(1)->defaultItems(1)->columns(4)
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                    ->schema([
                        TextInput::make('label')->label(__('admin.catalog.customization.area'))->required()->maxLength(128),
                        Select::make('type')->label(__('admin.catalog.customization.area_type'))->options(['rectangle' => 'rectangle', 'circle' => 'circle', 'standard' => 'standard'])->default('rectangle')->native(false),
                        TextInput::make('width_mm')->label(__('admin.catalog.customization.width'))->integer()->minValue(0),
                        TextInput::make('height_mm')->label(__('admin.catalog.customization.height'))->integer()->minValue(0),
                        Repeater::make('options')->relationship()->label(__('admin.catalog.customization.options'))->minItems(1)->defaultItems(1)->columns(5)->columnSpanFull()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->schema([
                                TextInput::make('label')->label(__('admin.catalog.customization.option_label'))->helperText(__('admin.catalog.customization.option_hint'))->required()->maxLength(32),
                                TextInput::make('number_of_colors')->label(__('admin.catalog.customization.colours'))->integer()->minValue(0)->default(1)->required(),
                                TextInput::make('setup')->label(__('admin.catalog.customization.setup'))->numeric()->minValue(0)->step(0.01)->default(0)->required()->suffix('€'),
                                TextInput::make('setup_multiplier')->label(__('admin.catalog.customization.setup_multiplier'))->integer()->minValue(1)->default(1)->required(),
                                TextInput::make('start_cost')->label(__('admin.catalog.customization.start_cost'))->numeric()->minValue(0)->step(0.01)->default(0)->required()->suffix('€'),
                                Repeater::make('tiers')->relationship()->label(__('admin.catalog.customization.tiers'))->minItems(1)->defaultItems(1)->columns(5)->columnSpanFull()
                                    ->reorderable(false)
                                    ->schema([
                                        TextInput::make('from_quantity')->label(__('admin.catalog.customization.from_quantity'))->integer()->minValue(1)->default(1)->required(),
                                        TextInput::make('original_price')->label(__('admin.catalog.customization.cost'))->numeric()->minValue(0)->step(0.01)->required()->suffix('€'),
                                        TextInput::make('price')->label(__('admin.catalog.customization.price'))->numeric()->minValue(0)->step(0.01)->required()->suffix('€'),
                                        TextInput::make('packaging_original_price')->label(__('admin.catalog.customization.packaging_cost'))->numeric()->minValue(0)->step(0.01)->suffix('€'),
                                        TextInput::make('packaging_price')->label(__('admin.catalog.customization.packaging_price'))->numeric()->minValue(0)->step(0.01)->suffix('€'),
                                    ]),
                            ]),
                    ]),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(__('admin.catalog.customization.hint'))
            ->defaultSort('position_label')
            ->columns([
                TextColumn::make('family')->label(__('admin.catalog.family'))->badge()->placeholder('-'),
                TextColumn::make('technique_label')->label(__('admin.catalog.technique'))->searchable()->sortable(),
                TextColumn::make('position_label')->label(__('admin.catalog.position'))->searchable()->sortable(),
                TextColumn::make('minimum_quantity')->label(__('admin.catalog.customization.minimum')),
                TextColumn::make('processing_days')->label(__('admin.catalog.customization.days')),
                IconColumn::make('is_default')->label(__('admin.catalog.customization.default'))->boolean(),
                TextColumn::make('source')->label(__('admin.catalog.customization.source'))->badge()->formatStateUsing(fn (string $state, Customization $record): string => $record->isManual() ? __('admin.catalog.customization.manual') : $state)->color(fn (Customization $record): string => $record->isManual() ? 'success' : 'gray'),
                IconColumn::make('locked')->label(__('admin.catalog.customization.locked'))->boolean()->visible(fn (): bool => true),
            ])
            ->headerActions([
                CreateAction::make()->modalWidth('5xl')
                    ->mutateDataUsing(fn (array $data): array => $this->manualAttributes($data))
                    ->after(fn () => CatalogCache::flush()),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('5xl')->after(fn () => CatalogCache::flush()),
                Action::make('setDefault')->label(__('admin.catalog.customization.set_default'))->icon('heroicon-o-star')
                    ->visible(fn (Customization $record): bool => ! $record->is_default)
                    ->action(function (Customization $record): void {
                        app(SetDefaultCustomization::class)->handle($record);
                        Notification::make()->title(__('admin.catalog.customization.set_default_done'))->success()->send();
                    }),
                Action::make('toggleLock')
                    ->label(fn (Customization $record): string => $record->locked ? __('admin.catalog.customization.unlock') : __('admin.catalog.customization.lock'))
                    ->icon(fn (Customization $record): string => $record->locked ? 'heroicon-o-lock-open' : 'heroicon-o-lock-closed')
                    ->visible(fn (Customization $record): bool => ! $record->isManual())
                    ->action(fn (Customization $record) => $record->update(['locked' => ! $record->locked])),
                DeleteAction::make()->after(fn () => CatalogCache::flush()),
            ]);
    }

    /**
     * A customization created here belongs to the shop, not to a connector.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function manualAttributes(array $data): array
    {
        /** @var ProductVariant $variant */
        $variant = $this->getOwnerRecord();

        return $data + [
            'source' => Customization::MANUAL_SOURCE,
            'pipeline' => null,
            'source_product_sku' => $variant->product?->sku,
            'source_variant_sku' => $variant->sku,
            'product_id' => $variant->product_id,
            'is_default' => $variant->customizations()->count() === 0 ? 1 : 0,
        ];
    }
}
