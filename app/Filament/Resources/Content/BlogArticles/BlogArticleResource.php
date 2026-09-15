<?php

declare(strict_types=1);

namespace App\Filament\Resources\Content\BlogArticles;

use App\Filament\Resources\Content\BlogArticles\Pages\CreateBlogArticle;
use App\Filament\Resources\Content\BlogArticles\Pages\EditBlogArticle;
use App\Filament\Resources\Content\BlogArticles\Pages\ListBlogArticles;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Models\BlogArticle;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class BlogArticleResource extends Resource
{
    protected static ?string $model = BlogArticle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'title';

    public const COVER_DIR = 'blog/img';

    public static function getNavigationGroup(): string
    {
        return __('admin.nav.content');
    }

    public static function getModelLabel(): string
    {
        return __('admin.content.article');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.content.articles');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Tabs::make()->columnSpanFull()->tabs([
                Tab::make(__('admin.content.tabs.data'))->columns(2)->schema([
                    TextInput::make('title')->label(__('admin.content.title'))->required()->maxLength(255),
                    TextInput::make('slug')->label(__('admin.content.slug'))->required()->maxLength(255)->unique(ignoreRecord: true)->alphaDash()->helperText(__('admin.catalog.slug_hint')),
                    Select::make('tags')->label(__('admin.content.tags'))->relationship('tags', 'name')->multiple()->preload(),
                    FileUpload::make('cover')->label(__('admin.content.cover'))->image()->disk('public')->directory(self::COVER_DIR)->visibility('public'),
                    Toggle::make('active')->label(__('admin.content.active'))->default(true)->inline(false),
                    Toggle::make('navbar')->label(__('admin.content.navbar'))->inline(false),
                    TextInput::make('position')->label(__('admin.content.position'))->numeric()->default(0),
                    Textarea::make('excerpt')->label(__('admin.content.excerpt'))->rows(3)->columnSpanFull(),
                    RichEditor::make('text')->label(__('admin.content.text'))->columnSpanFull(),
                ]),
                Tab::make(__('admin.content.tabs.seo'))->columns(2)->schema(ProductForm::seoFields()),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('admin.content.title'))->searchable()->sortable(),
                TextColumn::make('slug')->label(__('admin.content.slug'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tags.name')->label(__('admin.content.tags'))->badge()->color('gray'),
                IconColumn::make('active')->label(__('admin.content.active'))->boolean(),
                TextColumn::make('created_at')->label(__('admin.common.created_at'))->dateTime('d/m/Y')->sortable(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogArticles::route('/'),
            'create' => CreateBlogArticle::route('/create'),
            'edit' => EditBlogArticle::route('/{record}/edit'),
        ];
    }
}
