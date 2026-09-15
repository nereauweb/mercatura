<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

final class CategoryForm
{
    public const ICON_DIR = 'categories/icons';

    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Tabs::make()->columnSpanFull()->tabs([
                Tab::make(__('admin.catalog.tabs.data'))->columns(2)->schema([
                    TextInput::make('name')->label(__('admin.catalog.name'))->required()->maxLength(50),
                    Select::make('parent_id')->label(__('admin.catalog.parent'))->placeholder(__('admin.catalog.root'))
                        ->options(fn (?Category $record): array => Category::query()->whereNull('parent_id')->when($record, fn ($q) => $q->whereKeyNot($record->id))->orderBy('position')->pluck('name', 'id')->all())
                        ->native(false)->nullable(),
                    TextInput::make('slug')->label(__('admin.catalog.slug'))->maxLength(100)->helperText(__('admin.catalog.slug_hint')),
                    TextInput::make('position')->label(__('admin.catalog.ordering'))->numeric()->default(0),
                    Toggle::make('active')->label(__('admin.catalog.active'))->default(true)->inline(false),
                    Section::make(__('admin.catalog.icon'))->columns(2)->components([
                        FileUpload::make('icon')->label(__('admin.catalog.icon'))->image()->disk('public')->directory(self::ICON_DIR)->visibility('public'),
                        FileUpload::make('icon_rev')->label(__('admin.catalog.icon_rev'))->image()->disk('public')->directory(self::ICON_DIR)->visibility('public'),
                    ])->columnSpanFull(),
                    RichEditor::make('description')->label(__('admin.catalog.description'))->columnSpanFull(),
                    RichEditor::make('extra_text')->label(__('admin.catalog.extra_text'))->columnSpanFull(),
                ]),
                Tab::make(__('admin.catalog.tabs.seo'))->columns(2)->schema(ProductForm::seoFields()),
            ]),
        ]);
    }
}
