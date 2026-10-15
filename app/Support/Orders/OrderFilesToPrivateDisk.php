<?php

declare(strict_types=1);

namespace App\Support\Orders;

use App\Models\Order;
use App\Models\OrderItemCustomization;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Moves the order files written before v2.21 off the public disk, where
 * anyone holding a URL could read them: the order_files media to the private
 * `order-files` disk (same relative path, the row's disk updated), the
 * customizations' artwork to the `local` disk (same path, so `file` keeps its
 * value). Idempotent: only what is still on the public disk moves.
 */
final class OrderFilesToPrivateDisk
{
    /** @return array{media: int, artwork: int, missing: int} */
    public static function run(): array
    {
        $moved = ['media' => 0, 'artwork' => 0, 'missing' => 0];
        $public = Storage::disk('public');

        DB::table('media')
            ->where('model_type', (new Order)->getMorphClass())
            ->where('collection_name', Order::FILES)
            ->where('disk', 'public')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($public, &$moved): void {
                $target = Storage::disk(Order::FILES_DISK);
                foreach ($rows as $row) {
                    $path = $row->id.'/'.$row->file_name;
                    if ($public->exists($path)) {
                        self::copy($public, $target, $path);
                        $public->deleteDirectory((string) $row->id);
                        $moved['media']++;
                    } else {
                        $moved['missing']++;
                    }
                    DB::table('media')->where('id', $row->id)->update(['disk' => Order::FILES_DISK, 'conversions_disk' => Order::FILES_DISK]);
                }
            });

        $target = Storage::disk(OrderItemCustomization::FILE_DISK);
        OrderItemCustomization::query()->whereNotNull('file')->where('file', '<>', '')->orderBy('id')
            ->chunkById(100, function ($rows) use ($public, $target, &$moved): void {
                foreach ($rows as $row) {
                    $path = (string) $row->file;
                    if (! $public->exists($path)) {
                        continue;
                    }
                    if (! $target->exists($path)) {
                        self::copy($public, $target, $path);
                    }
                    $public->delete($path);
                    $moved['artwork']++;
                }
            });

        return $moved;
    }

    /** Streams one file across disks (artwork may be tens of MB). */
    private static function copy(Filesystem $from, Filesystem $to, string $path): void
    {
        $stream = $from->readStream($path);
        if ($stream === null) {
            throw new \RuntimeException("Cannot read {$path} from the public disk.");
        }
        try {
            $to->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
