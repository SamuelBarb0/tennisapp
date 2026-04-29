<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Matchstat (Tennis API ATP/WTA/ITF) external IDs so we can sync
 * incrementally without duplicating records on each pull.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->unsignedBigInteger('matchstat_id')->nullable()->unique()->after('id');
            $table->string('wikidata_id', 32)->nullable()->after('matchstat_id');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            // season_id is the Matchstat identifier per edition (year-specific)
            $table->unsignedBigInteger('matchstat_season_id')->nullable()->unique()->after('api_event_type_key');
            $table->unsignedBigInteger('matchstat_tournament_id')->nullable()->index()->after('matchstat_season_id');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedBigInteger('matchstat_match_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['matchstat_id', 'wikidata_id']);
        });
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['matchstat_season_id', 'matchstat_tournament_id']);
        });
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('matchstat_match_id');
        });
    }
};
