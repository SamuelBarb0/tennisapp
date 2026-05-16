<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `family_slug` groups ATP and WTA editions of the same event so the home and
 * the calendar can render a single card per family. For example:
 *   - Tournament "Internazionali BNL d'Italia" (ATP) → family_slug = 'internazionali-bnl-d-italia-2026'
 *   - Tournament "Internazionali BNL d'Italia" (WTA) → family_slug = 'internazionali-bnl-d-italia-2026'
 *
 * The slug we already use for bracket.tennis is the natural family key —
 * we reuse `tennisexplorer_slug` value as the family_slug, just stored in
 * its own column so other queries (home / calendar / payments) don't need
 * to know about the scraper.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('family_slug')->nullable()->after('slug')->index();
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('family_slug');
        });
    }
};
