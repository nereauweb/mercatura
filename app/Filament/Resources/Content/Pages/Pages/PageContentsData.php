<?php

declare(strict_types=1);

namespace App\Filament\Resources\Content\Pages\Pages;

use App\Enums\PageFilterType;
use App\Models\Page;
use App\Models\PageContent;
use App\Support\CatalogCache;
use App\Support\StoredFileName;
use Illuminate\Support\Facades\DB;

/** Maps the product-block form fields to pages_contents rows and back. */
final class PageContentsData
{
    private const FILTER_KEYS = ['filter_categories', 'filter_attributes', 'filter_created_after', 'filter_products', 'filter_sale', 'filter_bestseller', 'filter_green', 'filter_promo'];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fill(Page $page, array $data): array
    {
        $data = StoredFileName::toUploadPaths($data, \App\Filament\Resources\Content\Pages\PageResource::COVER_DIR, ['cover']);
        $contents = $page->contents()->get();
        $data['filter_categories'] = $contents->where('filter_type', PageFilterType::CategoryId->value)->pluck('filter_target')->map(fn ($v) => (int) $v)->values()->all();
        $data['filter_attributes'] = $contents->where('filter_type', PageFilterType::AttributeValue->value)->map(fn (PageContent $c) => ['attribute_id' => (int) $c->filter_target, 'value' => (string) $c->filter_value])->values()->all();
        $data['filter_created_after'] = $contents->firstWhere('filter_type', PageFilterType::CreatedAfter->value)?->filter_value;
        $data['filter_products'] = $contents->where('filter_type', PageFilterType::ProductId->value)->pluck('filter_target')->map(fn ($v) => (int) $v)->values()->all();
        foreach (['sale', 'bestseller', 'green', 'promo'] as $flag) {
            $data['filter_'.$flag] = (int) $contents->firstWhere('filter_type', 'is_'.$flag)?->filter_value === 1;
        }

        return $data;
    }

    /**
     * Strips the virtual fields from the data to save and returns them.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function split(array $data): array
    {
        $filters = [];
        foreach (self::FILTER_KEYS as $key) {
            $filters[$key] = $data[$key] ?? null;
            unset($data[$key]);
        }
        $data = StoredFileName::toBareNames($data, ['cover']);

        return [$data, $filters];
    }

    /**
     * Rewrites the page's pages_contents rows from the form (the legacy
     * update did the same per type with updateOrCreate/destroy).
     *
     * @param  array<string, mixed>  $filters
     */
    public static function save(Page $page, array $filters): void
    {
        DB::transaction(function () use ($page, $filters): void {
            $page->contents()->delete();
            if (! $page->products) {
                return;
            }
            $rows = [];
            foreach ((array) ($filters['filter_categories'] ?? []) as $id) {
                $rows[] = [PageFilterType::CategoryId->value, (string) (int) $id, null];
            }
            foreach ((array) ($filters['filter_attributes'] ?? []) as $row) {
                if (! empty($row['attribute_id']) && trim((string) ($row['value'] ?? '')) !== '') {
                    $rows[] = [PageFilterType::AttributeValue->value, (string) (int) $row['attribute_id'], trim((string) $row['value'])];
                }
            }
            if (! empty($filters['filter_created_after'])) {
                $rows[] = [PageFilterType::CreatedAfter->value, null, (string) $filters['filter_created_after']];
            }
            foreach ((array) ($filters['filter_products'] ?? []) as $id) {
                $rows[] = [PageFilterType::ProductId->value, (string) (int) $id, null];
            }
            foreach (['sale', 'bestseller', 'green', 'promo'] as $flag) {
                if (! empty($filters['filter_'.$flag])) {
                    $rows[] = ['is_'.$flag, null, '1'];
                }
            }
            foreach ($rows as [$type, $target, $value]) {
                PageContent::query()->create(['page_id' => $page->id, 'filter_type' => $type, 'filter_target' => $target, 'filter_value' => $value]);
            }
        });
        CatalogCache::flush();
    }
}
