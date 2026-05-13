<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The api-tennis.com `get_tournaments` catalog endpoint doesn't return dates —
 * those only arrive once fixtures publish. The legacy schema had `start_date`
 * and `end_date` as NOT NULL, which blocked discovery. Relax to nullable.
 *
 * Existing rows keep their non-null dates; only new inserts can be null.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tournaments MODIFY start_date DATE NULL DEFAULT NULL');
        DB::statement('ALTER TABLE tournaments MODIFY end_date DATE NULL DEFAULT NULL');
    }

    public function down(): void
    {
        // Reverting to NOT NULL would fail on rows that became null after this
        // migration. Skipped on purpose.
    }
};
