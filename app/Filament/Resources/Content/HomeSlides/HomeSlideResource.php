<?php

declare(strict_types=1);

namespace App\Filament\Resources\Content\HomeSlides;

use App\Filament\Resources\Content\HomeSlides\Pages\ManageHomeSlides;
use App\Models\ContentHomeSlide;
use App\Support\StoredFileName;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Home slideshow (content_home_slides): the legacy admin had no screen for it. */
final class HomeSlideResource extends Resource
{
    protected static ?string $model = ContentHomeSlide::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 20;

    public const IMAGE_DIR = 'home_slides';

    public static function getNavigationGroup(): string
    {
        return __('admin.nav.content');
    }

    public static function getModelLabel(): string
    {
        return __('admin.content.slide');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.content.slides');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title_text')->label(__('admin.content.title'))->required()->maxLength(256),
            TextInput::make('subtitle_text')->label(__('admin.content.subtitle'))->maxLength(256),
            Textarea::make('text')->label(__('admin.content.text'))->rows(2)->maxLength(256)->columnSpanFull(),
            TextInput::make('cta_text')->label(__('admin.content.cta_text'))->maxLength(256),
            TextInput::make('cta_link')->label(__('admin.content.cta_link'))->maxLength(64),
            FileUpload::make('background_image')->label(__('admin.content.background_image'))->image()->disk('public')->directory(self::IMAGE_DIR)->visibility('public')->columnSpanFull(),
            ColorPicker::make('background_color')->label(__('admin.content.background_color')),
            ColorPicker::make('title_color')->label(__('admin.content.title_color')),
            ColorPicker::make('subtitle_color')->label(__('admin.content.subtitle_color')),
            ColorPicker::make('text_color')->label(__('admin.content.text_color')),
            TextInput::make('position')->label(__('admin.content.position'))->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                ImageColumn::make('background_image')->label(__('admin.content.background_image'))->disk('public')
                    ->getStateUsing(fn (ContentHomeSlide $record): ?string => $record->background_image ? self::IMAGE_DIR.'/'.$record->background_image : null)->height(40),
                TextColumn::make('title_text')->label(__('admin.content.title'))->searchable(),
                TextColumn::make('subtitle_text')->label(__('admin.content.subtitle'))->placeholder('-'),
                TextColumn::make('cta_link')->label(__('admin.content.cta_link'))->placeholder('-'),
                TextColumn::make('position')->label(__('admin.content.position'))->sortable(),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('3xl')
                    ->mutateRecordDataUsing(fn (array $data): array => StoredFileName::toUploadPaths($data, self::IMAGE_DIR, ['background_image']))
                    ->mutateDataUsing(fn (array $data): array => StoredFileName::toBareNames($data, ['background_image'])),
                DeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make()->modalWidth('3xl')->mutateDataUsing(fn (array $data): array => StoredFileName::toBareNames($data, ['background_image'])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageHomeSlides::route('/')];
    }
}
