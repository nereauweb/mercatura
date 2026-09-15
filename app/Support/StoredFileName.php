<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Legacy content tables (pages.cover, blog_articles.cover,
 * content_home_slides.background_image, categories.icon) store a bare file
 * name under a fixed directory of the public disk; Filament's FileUpload
 * works with the path inside the disk. These two mappers convert.
 */
final class StoredFileName
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public static function toUploadPaths(array $data, string $dir, array $fields): array
    {
        foreach ($fields as $field) {
            if (! empty($data[$field]) && ! str_contains((string) $data[$field], '/')) {
                $data[$field] = $dir.'/'.$data[$field];
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public static function toBareNames(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (! empty($data[$field])) {
                $data[$field] = basename((string) $data[$field]);
            } elseif (array_key_exists($field, $data)) {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
