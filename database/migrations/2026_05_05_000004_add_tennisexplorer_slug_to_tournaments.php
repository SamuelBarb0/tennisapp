<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * api-tennis.com doesn't expose draw position, so we fall back to scraping
 * tennisexplorer.com for the canonical bracket order. This column stores the
 * TE slug (e.g. "rome/2026/atp-men", "us-open/2026/wta-women") for each
 * tournament that supports the scrape path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('tennisexplorer_slug')->nullable()->after('api_tournament_key');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('tennisexplorer_slug');
        });
    }
};
