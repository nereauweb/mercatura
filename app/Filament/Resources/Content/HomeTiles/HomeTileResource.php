<?php

declare(strict_types=1);

namespace App\Filament\Resources\Content\HomeTiles;

use App\Filament\Resources\Content\HomeTiles\Pages\ManageHomeTiles;
use App\Models\ContentHomeTile;
use App\Support\StoredFileName;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

/** Home tiles (content_home_tiles): the photo blocks under the slideshow, independent of the categories. */
final class HomeTileResource extends Resource
{
    protected static ?string $model = ContentHomeTile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 21;

    public const IMAGE_DIR = 'home_tiles';

    public static function getNavigationGroup(): string
    {
        return __('admin.nav.content');
    }

    public static function getModelLabel(): string
    {
        return __('admin.content.tile');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.content.tiles');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->label(__('admin.content.title'))->required()->maxLength(128),
            TextInput::make('link')->label(__('admin.content.tile_link'))->helperText(__('admin.content.tile_link_hint'))->maxLength(512),
            TextInput::make('text')->label(__('admin.content.text'))->maxLength(256)->columnSpanFull(),
            FileUpload::make('image')->label(__('admin.content.tile_image'))->helperText(__('admin.content.tile_image_hint'))->image()->disk('public')->directory(self::IMAGE_DIR)->visibility('public')->required(),
            TextInput::make('position')->label(__('admin.content.position'))->numeric()->default(0),
            Toggle::make('active')->label(__('admin.content.active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                ImageColumn::make('image')->label(__('admin.content.tile_image'))->disk('public')
                    ->getStateUsing(fn (ContentHomeTile $record): ?string => $record->image ? self::IMAGE_DIR.'/'.$record->image : null)->height(40),
                TextColumn::make('title')->label(__('admin.content.title'))->searchable(),
                ToggleColumn::make('active')->label(__('admin.content.active'))->afterStateUpdated(fn () => self::flush()),
                TextColumn::make('link')->label(__('admin.content.tile_link'))->placeholder('-'),
                TextColumn::make('position')->label(__('admin.content.position'))->sortable(),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('2xl')
                    ->mutateRecordDataUsing(fn (array $data): array => StoredFileName::toUploadPaths($data, self::IMAGE_DIR, ['image']))
                    ->mutateDataUsing(fn (array $data): array => StoredFileName::toBareNames($data, ['image']))
                    ->after(fn () => self::flush()),
                DeleteAction::make()->after(fn () => self::flush()),
            ])
            ->headerActions([
                CreateAction::make()->modalWidth('2xl')->mutateDataUsing(fn (array $data): array => StoredFileName::toBareNames($data, ['image']))->after(fn () => self::flush()),
            ]);
    }

    /** The storefront caches the tiles for a day. */
    public static function flush(): void
    {
        Cache::forget('home_tiles');
    }

    public static function getPages(): array
    {
        return ['index' => ManageHomeTiles::route('/')];
    }
}
