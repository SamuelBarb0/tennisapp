<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift fix: `matches.bracket_position` exists in production (it was
 * added out-of-band) but no migration ever created it, so a fresh `migrate`
 * produced a table without it — breaking the bracket realigner/reconciler and
 * scoring, which all key off bracket_position.
 *
 * Guarded with hasColumn so it is a no-op on databases that already have it
 * (i.e. production) and only adds it where it is missing (fresh installs, CI).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('matches', 'bracket_position')) {
            Schema::table('matches', function (Blueprint $table) {
                $table->integer('bracket_position')->nullable()->after('round');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('matches', 'bracket_position')) {
            Schema::table('matches', function (Blueprint $table) {
                $table->dropColumn('bracket_position');
            });
        }
    }
};
