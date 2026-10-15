<?php

declare(strict_types=1);

use App\Support\Orders\OrderFilesToPrivateDisk;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Order files leave the public disk (security fix): attachments to the
 * private `order-files` disk, customization artwork to the `local` disk.
 * Old public URLs stop working; the files are reachable through the order
 * file routes (customer: own orders; panel: OrderPolicy::view).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media') || ! Schema::hasTable('order_item_customizations')) {
            return;
        }
        OrderFilesToPrivateDisk::run();
    }

    public function down(): void
    {
        // Moving private files back to a public disk is not a step to automate.
    }
};
