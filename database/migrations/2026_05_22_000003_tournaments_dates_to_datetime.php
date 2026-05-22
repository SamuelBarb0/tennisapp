<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Promote tournaments.start_date / end_date from DATE → DATETIME.
 *
 * The DATE type drops the hour-of-day portion, which prevented the sync
 * from storing the real first-match time (e.g. Roland Garros 11:00 Paris).
 * With DATETIME we can now persist the wall-clock and convert to UTC at
 * write time and to Bogotá at display time.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE tournaments MODIFY start_date DATETIME NULL DEFAULT NULL');
        DB::statement('ALTER TABLE tournaments MODIFY end_date DATETIME NULL DEFAULT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tournaments MODIFY start_date DATE NULL DEFAULT NULL');
        DB::statement('ALTER TABLE tournaments MODIFY end_date DATE NULL DEFAULT NULL');
    }
};
