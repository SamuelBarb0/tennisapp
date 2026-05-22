<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overrides for the badge/seed shown next to a player in a specific
 * tournament. Used when bracket.tennis omits a marker that the official
 * tournament site confirms (e.g. T. Rakotomanga's WC at Roland Garros 2026,
 * which BT left blank but RG's own page shows as "(W)").
 *
 * The sync loop reads this table AFTER applying BT's data, so the override
 * always wins.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('player_seed_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('badge', 8); // "WC", "Q", "LL", "PR", "SE", or "1".."32"
            $table->string('reason')->nullable(); // free text, e.g. "BT omitió el badge"
            $table->timestamps();

            $table->unique(['tournament_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_seed_overrides');
    }
};
