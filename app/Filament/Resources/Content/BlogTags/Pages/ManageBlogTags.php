<?php

declare(strict_types=1);

namespace App\Filament\Resources\Content\BlogTags\Pages;

use App\Filament\Resources\Content\BlogTags\BlogTagResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageBlogTags extends ManageRecords
{
    protected static string $resource = BlogTagResource::class;
}
