<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Pages;

/**
 * categories.icon / icon_rev store a bare file name under
 * storage/app/public/categories/icons (the storefront builds the URL);
 * FileUpload works with the path inside the disk.
 */
final class CategoryIconPaths
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function toUploads(array $data, string $dir): array
    {
        foreach (['icon', 'icon_rev'] as $field) {
            if (! empty($data[$field]) && ! str_contains((string) $data[$field], '/')) {
                $data[$field] = $dir.'/'.$data[$field];
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function toColumns(array $data, string $dir): array
    {
        foreach (['icon', 'icon_rev'] as $field) {
            if (! empty($data[$field])) {
                $data[$field] = basename((string) $data[$field]);
            } elseif (array_key_exists($field, $data)) {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
