<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Split the existing "module.action" permission names into their own
     * columns, so the identity module can group and filter by module without
     * parsing the name on every read.
     */
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('module')->after('id')->nullable();
            $table->string('action')->after('module')->nullable();

            $table->index('module');
        });

        foreach (DB::table('permissions')->select('id', 'name')->get() as $permission) {
            DB::table('permissions')
                ->where('id', $permission->id)
                ->update([
                    'module' => Str::before($permission->name, '.'),
                    'action' => Str::contains($permission->name, '.')
                        ? Str::after($permission->name, '.')
                        : $permission->name,
                ]);
        }

        Schema::table('permissions', function (Blueprint $table) {
            $table->string('module')->nullable(false)->change();
            $table->string('action')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropIndex(['module']);
            $table->dropColumn(['module', 'action']);
        });
    }
};
