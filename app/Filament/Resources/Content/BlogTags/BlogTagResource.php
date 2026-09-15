<?php

declare(strict_types=1);

namespace App\Filament\Resources\Content\BlogTags;

use App\Filament\Resources\Content\BlogTags\Pages\ManageBlogTags;
use App\Models\BlogTag;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class BlogTagResource extends Resource
{
    protected static ?string $model = BlogTag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 31;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string
    {
        return __('admin.nav.content');
    }

    public static function getModelLabel(): string
    {
        return __('admin.content.tag');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.content.tags');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label(__('admin.content.name'))->required()->maxLength(255),
            TextInput::make('slug')->label(__('admin.content.slug'))->required()->maxLength(255)->unique(ignoreRecord: true)->alphaDash(),
            TextInput::make('position')->label(__('admin.content.position'))->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('articles'))
            ->defaultSort('position')
            ->columns([
                TextColumn::make('name')->label(__('admin.content.name'))->searchable()->sortable(),
                TextColumn::make('slug')->label(__('admin.content.slug')),
                TextColumn::make('articles_count')->label(__('admin.content.articles')),
                TextColumn::make('position')->label(__('admin.content.position'))->sortable(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->headerActions([CreateAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBlogTags::route('/')];
    }
}
