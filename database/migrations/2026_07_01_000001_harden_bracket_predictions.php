<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make a user's bracket pick survive player churn.
 *
 * Before: a pick was stored ONLY as `predicted_winner_id` with an ON DELETE
 * CASCADE foreign key to `players`. Any operation that removed a player row
 * (dedupe, reset, re-import, a scraping bug) silently deleted the user's pick
 * along with it — nothing was left for the realigner to repair.
 *
 * After:
 *   - We snapshot the chosen player (`predicted_player_slug` + name) at save
 *     time, so the pick can always be re-linked even if the player's row id
 *     changes or disappears.
 *   - The FK becomes ON DELETE SET NULL and the column nullable, so deleting a
 *     player never deletes the pick — the row (and its position) stays put and
 *     the reconciler re-links it from the snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bracket_predictions', function (Blueprint $table) {
            $table->string('predicted_player_slug')->nullable()->after('predicted_winner_id');
            $table->string('predicted_player_name')->nullable()->after('predicted_player_slug');
        });

        // Backfill the snapshot from the currently referenced player.
        // Correlated subquery form works on both MySQL and SQLite.
        DB::statement(
            'UPDATE bracket_predictions SET ' .
            'predicted_player_slug = (SELECT slug FROM players WHERE players.id = bracket_predictions.predicted_winner_id), ' .
            'predicted_player_name = (SELECT name FROM players WHERE players.id = bracket_predictions.predicted_winner_id)'
        );

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // Swap CASCADE -> SET NULL and make the column nullable.
            Schema::table('bracket_predictions', function (Blueprint $table) {
                $table->dropForeign(['predicted_winner_id']);
            });
            DB::statement('ALTER TABLE bracket_predictions MODIFY predicted_winner_id BIGINT UNSIGNED NULL');
            Schema::table('bracket_predictions', function (Blueprint $table) {
                $table->foreign('predicted_winner_id')->references('id')->on('players')->nullOnDelete();
            });
        } else {
            // SQLite (tests): just relax the column to nullable. FK rebuild is
            // limited on SQLite and not needed for the in-memory test suite.
            Schema::table('bracket_predictions', function (Blueprint $table) {
                $table->unsignedBigInteger('predicted_winner_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            Schema::table('bracket_predictions', function (Blueprint $table) {
                $table->dropForeign(['predicted_winner_id']);
            });
            // Restore rows that lost their player before tightening back to NOT NULL.
            DB::statement('DELETE FROM bracket_predictions WHERE predicted_winner_id IS NULL');
            DB::statement('ALTER TABLE bracket_predictions MODIFY predicted_winner_id BIGINT UNSIGNED NOT NULL');
            Schema::table('bracket_predictions', function (Blueprint $table) {
                $table->foreign('predicted_winner_id')->references('id')->on('players')->cascadeOnDelete();
            });
        }

        Schema::table('bracket_predictions', function (Blueprint $table) {
            $table->dropColumn(['predicted_player_slug', 'predicted_player_name']);
        });
    }
};
