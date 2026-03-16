<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('api_tournament_key')->nullable()->unique()->after('id');
            $table->string('api_event_type_key')->nullable()->after('api_tournament_key');
            $table->string('season')->nullable()->after('end_date');
            // Make location/city/country nullable for API-synced tournaments
            $table->string('location')->nullable()->change();
            $table->string('city')->nullable()->change();
            $table->string('country')->nullable()->change();
        });

        Schema::table('players', function (Blueprint $table) {
            $table->string('api_player_key')->nullable()->unique()->after('id');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->string('api_event_key')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['api_tournament_key', 'api_event_type_key', 'season']);
            $table->string('location')->nullable(false)->change();
            $table->string('city')->nullable(false)->change();
            $table->string('country')->nullable(false)->change();
        });

        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('api_player_key');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('api_event_key');
        });
    }
};
