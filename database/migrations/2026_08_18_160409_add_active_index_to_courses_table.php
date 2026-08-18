<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `courses.active` is filtered on by every course picker and by the modality
 * assignment listing, and it had no index.
 *
 * Honest about the size of this: at the target volume (800 courses) the gain is
 * small — the table fits comfortably in memory and a scan is cheap. It is here
 * because the column is filtered on in hot paths and leaving it unindexed is a
 * defect that only gets more expensive, not because it moves a budget today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->index('active', 'courses_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex('courses_active_index');
        });
    }
};
