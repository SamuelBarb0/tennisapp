<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-match seed/qualifier marker for each player. Values:
 *   - Numeric string "1".."32" → tournament seed
 *   - "Q"  → qualifier (entered through qualifying rounds)
 *   - "WC" → wildcard (organizer invitation)
 *   - "LL" → lucky loser
 *   - NULL → unseeded regular entry
 *
 * Stored per match (not per player) because the same player can have a
 * different seed across tournaments — and even appear unseeded sometimes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->string('player1_seed', 4)->nullable()->after('player1_id');
            $table->string('player2_seed', 4)->nullable()->after('player2_id');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['player1_seed', 'player2_seed']);
        });
    }
};
