<?php

declare(strict_types=1);

namespace App\Filament\Resources\Content\Pages\Pages;

use App\Filament\Resources\Content\Pages\PageResource;
use App\Models\Page;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        [$data, $filters] = PageContentsData::split($data);
        $page = Page::query()->create($data);
        PageContentsData::save($page, $filters);

        return $page;
    }
}
