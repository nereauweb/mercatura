<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Actions\Catalog\CreateProductWithVariant;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Brand;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Creates the product together with its first (main) variant. */
final class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $variant = (array) ($data['variant'] ?? []);
        $categoryIds = array_map('intval', (array) ($data['category_ids'] ?? []));
        unset($data['variant'], $data['category_ids']);
        $data['brand'] = isset($data['brand_id']) ? (string) Brand::query()->whereKey($data['brand_id'])->value('name') : null;

        $imagePath = ! empty($variant['image']) ? Storage::disk('local')->path((string) $variant['image']) : null;

        return app(CreateProductWithVariant::class)->handle($data, $categoryIds, [
            'color_id' => (int) $variant['color_id'],
            'size_id' => isset($variant['size_id']) ? (int) $variant['size_id'] : null,
            'stock' => (int) ($variant['stock'] ?? 0),
            'price' => $variant['price'] ?? null,
            'image_path' => $imagePath,
        ]);
    }

    protected function getCreatedNotificationTitle(): string
    {
        return __('admin.catalog.created');
    }
}
