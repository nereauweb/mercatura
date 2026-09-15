<?php

declare(strict_types=1);

namespace App\Filament\Resources\Content\Pages;

use App\Filament\Resources\Content\Pages\Pages\CreatePage;
use App\Filament\Resources\Content\Pages\Pages\EditPage;
use App\Filament\Resources\Content\Pages\Pages\ListPages;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductAttribute;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * CMS pages (/contenuti/{slug}) with the optional product block whose
 * filters live in pages_contents (PageContent), exactly as the storefront
 * reads them in Page::products_ids().
 */
final class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocument;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'title';

    public const COVER_DIR = 'pages/img';

    public static function getNavigationGroup(): string
    {
        return __('admin.nav.content');
    }

    public static function getModelLabel(): string
    {
        return __('admin.content.page');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.content.pages');
    }

    public static function form(Schema $schema): Schema
    {
        $hasProducts = fn (Get $get): bool => (bool) $get('products');

        return $schema->columns(1)->components([
            Tabs::make()->columnSpanFull()->tabs([
                Tab::make(__('admin.content.tabs.data'))->columns(2)->schema([
                    TextInput::make('title')->label(__('admin.content.title'))->required()->maxLength(128),
                    TextInput::make('slug')->label(__('admin.content.slug'))->required()->maxLength(64)->unique(ignoreRecord: true)->alphaDash()->helperText(__('admin.catalog.slug_hint')),
                    Toggle::make('active')->label(__('admin.content.active'))->default(true)->inline(false),
                    Toggle::make('navbar')->label(__('admin.content.navbar'))->inline(false),
                    TextInput::make('position')->label(__('admin.content.position'))->numeric()->default(0),
                    FileUpload::make('cover')->label(__('admin.content.cover'))->image()->disk('public')->directory(self::COVER_DIR)->visibility('public'),
                    TextInput::make('cta_text')->label(__('admin.content.cta_text'))->maxLength(32),
                    TextInput::make('cta_link')->label(__('admin.content.cta_link'))->maxLength(128),
                    RichEditor::make('text')->label(__('admin.content.text'))->columnSpanFull(),
                    RichEditor::make('extra_text')->label(__('admin.catalog.extra_text'))->columnSpanFull(),
                ]),
                Tab::make(__('admin.content.tabs.products'))->schema([
                    Toggle::make('products')->label(__('admin.content.products_block'))->live()->inline(false),
                    Section::make()->visible($hasProducts)->columns(2)->description(__('admin.content.filter_hint'))->components([
                        CheckboxList::make('filter_categories')->label(__('admin.content.filter_categories'))->options(fn (): array => ProductForm::categoryOptions())->columns(2)->searchable()->columnSpanFull(),
                        Repeater::make('filter_attributes')->label(__('admin.content.filter_attributes'))->defaultItems(0)->columns(2)->reorderable(false)
                            ->schema([
                                Select::make('attribute_id')->label(__('admin.content.filter_attribute'))->required()->native(false)
                                    ->options(fn (): array => ProductAttribute::query()->orderBy('label')->pluck('label', 'id')->all()),
                                TextInput::make('value')->label(__('admin.content.filter_value'))->required(),
                            ])->columnSpanFull(),
                        DateTimePicker::make('filter_created_after')->label(__('admin.content.filter_created_after'))->native(false)->seconds(false),
                        Select::make('filter_products')->label(__('admin.content.filter_products'))->multiple()->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Product::query()->where('active', 1)
                                ->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('sku', 'like', $search.'%'))
                                ->limit(20)->get()->mapWithKeys(fn (Product $p) => [$p->id => $p->sku.' · '.$p->name])->all())
                            ->getOptionLabelsUsing(fn (array $values): array => Product::query()->whereIn('id', $values)->get()->mapWithKeys(fn (Product $p) => [$p->id => $p->sku.' · '.$p->name])->all()),
                        Toggle::make('filter_sale')->label(__('admin.content.filter_sale'))->inline(false),
                        Toggle::make('filter_bestseller')->label(__('admin.content.filter_bestseller'))->inline(false),
                        Toggle::make('filter_green')->label(__('admin.content.filter_green'))->inline(false),
                        Toggle::make('filter_promo')->label(__('admin.content.filter_promo'))->inline(false),
                    ]),
                ]),
                Tab::make(__('admin.content.tabs.seo'))->columns(2)->schema(ProductForm::seoFields()),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                TextColumn::make('title')->label(__('admin.content.title'))->searchable()->sortable(),
                TextColumn::make('slug')->label(__('admin.content.slug'))->searchable(),
                IconColumn::make('products')->label(__('admin.content.tabs.products'))->boolean(),
                IconColumn::make('active')->label(__('admin.content.active'))->boolean(),
                IconColumn::make('navbar')->label(__('admin.content.navbar'))->boolean(),
                TextColumn::make('position')->label(__('admin.content.position'))->sortable(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
