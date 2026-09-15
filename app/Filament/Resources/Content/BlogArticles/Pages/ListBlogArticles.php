<?php

declare(strict_types=1);

namespace App\Filament\Resources\Content\BlogArticles\Pages;

use App\Filament\Resources\Content\BlogArticles\BlogArticleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListBlogArticles extends ListRecords
{
    protected static string $resource = BlogArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
