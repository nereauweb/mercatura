<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User reports as messages rows (docs/06_USER_REPORTS.md §3, phase R1): the
 * sender's account and the report fields; the forward (R3) and capture
 * consent (R6) columns come with their phases. Contact rows keep the
 * report columns null. Existing rows get no user_id: the account pages match
 * by user_id from now on, so messages sent before stay visible to the admin only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('messages', 'type')) {
            return;
        }
        Schema::table('messages', function (Blueprint $t): void {
            $t->string('type', 16)->default('contact')->after('id')->index();
            $t->foreignId('user_id')->nullable()->after('type')->constrained('users')->nullOnDelete();
            $t->string('reporter_role', 16)->nullable()->after('user_id');
            $t->string('kind', 16)->nullable()->after('reporter_role');
            $t->string('page_url', 2048)->nullable()->after('message');
            $t->json('context')->nullable()->after('page_url');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('user_id');
            $t->dropIndex(['type']);
            $t->dropColumn(['type', 'reporter_role', 'kind', 'page_url', 'context']);
        });
    }
};
