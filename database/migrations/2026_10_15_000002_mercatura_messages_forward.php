<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Forward of a report to an external helpdesk (docs/06_USER_REPORTS.md §6,
 * phase R3): who forwarded it, when, and the reference the target returned.
 * The `messages.forward` permission is created here too and given to the
 * admin role, so installations get it on deploy without re-running CoreSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('messages', 'forwarded_at')) {
            Schema::table('messages', function (Blueprint $t): void {
                $t->timestamp('forwarded_at')->nullable()->after('read_at');
                $t->foreignId('forwarded_by')->nullable()->after('forwarded_at')->constrained('users')->nullOnDelete();
                $t->string('forward_reference', 64)->nullable()->after('forwarded_by');
            });
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            $permission = Permission::findOrCreate('messages.forward', 'web');
            Role::query()->where(['name' => 'admin', 'guard_name' => 'web'])->first()?->givePermissionTo($permission);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('forwarded_by');
            $t->dropColumn(['forwarded_at', 'forward_reference']);
        });
        if (Schema::hasTable('permissions')) {
            Permission::query()->where(['name' => 'messages.forward', 'guard_name' => 'web'])->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
