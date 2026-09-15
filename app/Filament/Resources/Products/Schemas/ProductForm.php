<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductColor;
use App\Models\ProductSize;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

final class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make()->columnSpanFull()->tabs([
                    Tab::make(__('admin.catalog.tabs.data'))->columns(2)->schema([
                        TextInput::make('sku')->label(__('admin.catalog.sku'))->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->required(fn (string $operation): bool => $operation === 'edit')
                            ->helperText(fn (string $operation): ?string => $operation === 'create' ? 'Vuoto: usa l\'ID del prodotto' : null),
                        TextInput::make('name')->label(__('admin.catalog.name'))->required()->maxLength(256),
                        Select::make('brand_id')->label(__('admin.catalog.brand'))
                            ->options(fn (): array => Brand::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()->native(false)->nullable(),
                        Toggle::make('active')->label(__('admin.catalog.active'))->default(true)->inline(false),
                        RichEditor::make('description')->label(__('admin.catalog.description'))->columnSpanFull(),
                        Select::make('forced_status')->label(__('admin.catalog.forced_status'))
                            ->options(__('admin.catalog.forced_status_options'))->default('none')->native(false),
                        Select::make('isBestseller')->label(__('admin.catalog.is_bestseller'))->options(__('admin.catalog.yes_no'))->default('0')->native(false),
                        Select::make('isGreen')->label(__('admin.catalog.is_green'))->options(__('admin.catalog.flag_options'))->default('0')->native(false),
                        Select::make('isPromo')->label(__('admin.catalog.is_promo'))->options(__('admin.catalog.flag_options'))->default('0')->native(false),
                        TextInput::make('slug')->label(__('admin.catalog.slug'))->maxLength(256)->helperText(__('admin.catalog.slug_hint'))
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                    ]),
                    Tab::make(__('admin.catalog.tabs.categories'))->schema([
                        CheckboxList::make('category_ids')->hiddenLabel()
                            ->options(fn (): array => self::categoryOptions())
                            ->columns(2)->searchable()->bulkToggleable(),
                    ]),
                    Tab::make(__('admin.catalog.tabs.main_variant'))
                        ->visible(fn (string $operation): bool => $operation === 'create')
                        ->columns(2)
                        ->schema([
                            Select::make('variant.color_id')->label(__('admin.catalog.color'))->required()
                                ->options(fn (): array => ProductColor::query()->orderBy('label')->pluck('label', 'id')->all())->searchable()->native(false),
                            Select::make('variant.size_id')->label(__('admin.catalog.size'))
                                ->options(fn (): array => ProductSize::query()->orderBy('label')->pluck('label', 'id')->all())
                                ->default(fn (): int => (int) config('mercatura.catalog.one_size_id', 52))->searchable()->native(false),
                            TextInput::make('variant.stock')->label(__('admin.catalog.stock'))->numeric()->default(0),
                            TextInput::make('variant.price')->label(__('admin.catalog.price'))->numeric()->step(0.01)->prefix('€'),
                            FileUpload::make('variant.image')->label(__('admin.catalog.cover'))->image()
                                ->disk('local')->directory('tmp/product-images')->visibility('private')->columnSpanFull(),
                        ]),
                    Tab::make(__('admin.catalog.tabs.seo'))->columns(2)->schema(self::seoFields()),
                ]),
            ]);
    }

    /** @return array<int, string> "Parent › Child" for every child category, roots without children listed alone. */
    public static function categoryOptions(): array
    {
        $options = [];
        $roots = Category::query()->whereNull('parent_id')->orderBy('position')->with(['children' => fn ($q) => $q->orderBy('position')])->get();
        foreach ($roots as $root) {
            if ($root->children->isEmpty()) {
                $options[$root->id] = $root->name;

                continue;
            }
            foreach ($root->children as $child) {
                $options[$child->id] = $root->name.' › '.$child->name;
            }
        }

        return $options;
    }

    /** @return list<\Filament\Schemas\Components\Component> */
    public static function seoFields(): array
    {
        return [
            TextInput::make('seo_title')->label(__('admin.catalog.seo.title'))->maxLength(70),
            Textarea::make('seo_description')->label(__('admin.catalog.seo.description'))->rows(2)->maxLength(300),
            TextInput::make('canonical_url')->label(__('admin.catalog.seo.canonical'))->url()->maxLength(512)->helperText(__('admin.catalog.seo.canonical_hint')),
            Toggle::make('noindex')->label(__('admin.catalog.seo.noindex'))->inline(false),
            TextInput::make('og_title')->label(__('admin.catalog.seo.og_title'))->maxLength(255),
            Textarea::make('og_description')->label(__('admin.catalog.seo.og_description'))->rows(2),
            TextInput::make('og_image')->label(__('admin.catalog.seo.og_image'))->maxLength(512)->columnSpanFull(),
        ];
    }
}
