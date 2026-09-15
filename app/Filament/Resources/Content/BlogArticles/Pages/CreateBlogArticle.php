<?php

declare(strict_types=1);

namespace App\Filament\Resources\Content\BlogArticles\Pages;

use App\Filament\Resources\Content\BlogArticles\BlogArticleResource;
use App\Support\StoredFileName;
use Filament\Resources\Pages\CreateRecord;

final class CreateBlogArticle extends CreateRecord
{
    protected static string $resource = BlogArticleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return StoredFileName::toBareNames($data, ['cover']);
    }
}
