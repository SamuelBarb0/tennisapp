<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The legacy `tournaments.type` column is an enum limited to ATP/WTA/GrandSlam,
 * which truncates labels like 'ATP Masters 1000' or 'WTA 1000' that the
 * Matchstat discovery flow needs to insert. Relax to a plain VARCHAR.
 *
 * Schema::change() can't alter enums on MySQL without doctrine/dbal, so we drop
 * down to raw SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tournaments MODIFY type VARCHAR(64) NOT NULL DEFAULT 'ATP'");
    }

    public function down(): void
    {
        // No reversal — going back to enum would lose any value not in the original list.
    }
};
