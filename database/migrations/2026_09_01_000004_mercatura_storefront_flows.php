<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mercatura over the monolith schema, part 4: storefront flows
 * (docs/04_STOREFRONT_FLOWS.md §5). Products that are only quoted, sample
 * order lines, the shipping date promised on an order line, home slides
 * with an on/off switch and a phone-sized image.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t): void {
            if (! Schema::hasColumn('products', 'quote_only')) {
                $t->boolean('quote_only')->default(false)->after('isBestseller');
            }
        });
        Schema::table('content_home_slides', function (Blueprint $t): void {
            if (! Schema::hasColumn('content_home_slides', 'active')) {
                $t->boolean('active')->default(true)->after('position');
            }
            if (! Schema::hasColumn('content_home_slides', 'mobile_image')) {
                $t->string('mobile_image', 256)->nullable()->after('background_image');
            }
        });
        Schema::table('order_items', function (Blueprint $t): void {
            if (! Schema::hasColumn('order_items', 'is_sample')) {
                $t->boolean('is_sample')->default(false)->after('unit_price');
            }
            if (! Schema::hasColumn('order_items', 'shipping_date')) {
                $t->date('shipping_date')->nullable()->after('is_sample');
            }
        });
    }

    public function down(): void
    {
        // The baseline dump is the way back: these migrations only move forward.
    }
};
