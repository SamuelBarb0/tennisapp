<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-match annotation for unusual finishes. Values:
 *   - "ret_p1"    → player1 retired (player2 wins by retirement)
 *   - "ret_p2"    → player2 retired
 *   - "wo_p1"     → player1 walkover (didn't show, player2 wins)
 *   - "wo_p2"     → player2 walkover
 *   - "suspended" → match suspended (rain, darkness, etc.)
 *   - NULL        → normal finish (or not played yet)
 *
 * The bracket renderer uses this to put "(ret.)" or "(wo)" next to the LOSING
 * player's name instead of cluttering the score string.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->string('status_note', 16)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('status_note');
        });
    }
};
