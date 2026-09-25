<?php

declare(strict_types=1);

namespace App\Support\Connectors;

/**
 * The flags of the running import (download, live update, products, forced update,
 * categories, printing data), published by the wrapper (app:import or the panel job)
 * so every connector command of the run applies the same choices.
 */
final class ImportFlags
{
    public function __construct(
        public readonly bool $download_data = true,
        public readonly bool $update_live = true,
        public readonly bool $process_product_data = true,
        public readonly bool $full_products_update = false,
        public readonly bool $update_categories = false,
        public readonly bool $process_customization_data = false,
    ) {}

    public const KEYS = ['download_data', 'update_live', 'process_product_data', 'full_products_update', 'update_categories', 'process_customization_data'];

    /** Make these flags the ones the connector commands of this process read. */
    public static function publish(self $flags): void
    {
        app()->instance(self::class, $flags);
    }

    /** The published flags, or null outside an import run. */
    public static function current(): ?self
    {
        return app()->bound(self::class) ? app(self::class) : null;
    }

    public static function forget(): void
    {
        app()->forgetInstance(self::class);
    }
}
