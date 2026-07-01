<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Safety-net copy of bracket predictions taken BEFORE any destructive
 * operation (re-import, reset). Lets us restore a user's picks automatically
 * with `php artisan tennis:restore-bracket-predictions` if a sync/scrape wipes
 * them. Deliberately NOT foreign-keyed to players/tournaments so a cascade can
 * never touch the backup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bracket_prediction_backups', function (Blueprint $table) {
            $table->id();
            $table->string('batch');                 // groups one backup run
            $table->string('reason')->nullable();    // e.g. "reimport", "reset"
            $table->unsignedBigInteger('tournament_id');
            $table->unsignedBigInteger('user_id');
            $table->string('round');
            $table->integer('position');
            $table->unsignedBigInteger('predicted_winner_id')->nullable();
            $table->string('predicted_player_slug')->nullable();
            $table->string('predicted_player_name')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->integer('points_earned')->default(0);
            $table->string('final_score_prediction', 20)->nullable();
            $table->timestamp('original_created_at')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'user_id']);
            $table->index('batch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bracket_prediction_backups');
    }
};
