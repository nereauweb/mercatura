<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Home tiles: photo blocks under the slideshow, managed in the admin, independent of the categories. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('content_home_tiles')) {
            return;
        }
        Schema::create('content_home_tiles', function (Blueprint $t): void {
            $t->id();
            $t->integer('position')->default(0)->index();
            $t->boolean('active')->default(true);
            $t->string('title', 128);
            $t->string('text', 256)->nullable();
            $t->string('image', 256)->nullable();
            $t->string('link', 512)->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_home_tiles');
    }
};
