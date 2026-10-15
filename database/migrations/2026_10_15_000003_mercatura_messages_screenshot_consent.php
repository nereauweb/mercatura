<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proof of the screen-capture consent of a report (docs/06_USER_REPORTS.md §5,
 * phase R6): when the user ticked the box and which version of the text they saw.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('messages', 'screenshot_consent_at')) {
            return;
        }
        Schema::table('messages', function (Blueprint $t): void {
            $t->timestamp('screenshot_consent_at')->nullable()->after('context');
            $t->string('screenshot_consent_version', 16)->nullable()->after('screenshot_consent_at');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $t): void {
            $t->dropColumn(['screenshot_consent_at', 'screenshot_consent_version']);
        });
    }
};
