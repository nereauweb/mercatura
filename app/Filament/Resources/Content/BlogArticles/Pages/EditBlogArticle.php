<?php

declare(strict_types=1);

namespace App\Filament\Resources\Content\BlogArticles\Pages;

use App\Filament\Resources\Content\BlogArticles\BlogArticleResource;
use App\Models\BlogArticle;
use App\Support\StoredFileName;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditBlogArticle extends EditRecord
{
    protected static string $resource = BlogArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')->label(__('admin.common.storefront'))->icon('heroicon-o-eye')->color('gray')
                ->url(fn (BlogArticle $record): string => route('frontend.blog.show', ['slug' => $record->slug]), shouldOpenInNewTab: true),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return StoredFileName::toUploadPaths($data, BlogArticleResource::COVER_DIR, ['cover']);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return StoredFileName::toBareNames($data, ['cover']);
    }
}
