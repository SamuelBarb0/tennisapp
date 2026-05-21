<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Extend the `matches.status` ENUM to include 'bye'.
     *
     * BYE rounds happen in 56/96-draw tournaments where top seeds skip the
     * first round. The bracket bootstrap now marks those rows as status='bye'
     * (winner set, no real match played) so the view can render them as
     * "BYE" instead of confusing the user with phantom walkovers.
     *
     * We modify the column directly with raw SQL because Laravel's Doctrine
     * DBAL helper doesn't always preserve ENUM list ordering on MySQL.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `matches` MODIFY COLUMN `status` ENUM('pending','live','finished','cancelled','bye') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Revert: drop 'bye' from the ENUM. Existing rows must be remapped
        // first (back to 'finished') to avoid data truncation.
        DB::statement("UPDATE `matches` SET `status` = 'finished' WHERE `status` = 'bye'");
        DB::statement("ALTER TABLE `matches` MODIFY COLUMN `status` ENUM('pending','live','finished','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
