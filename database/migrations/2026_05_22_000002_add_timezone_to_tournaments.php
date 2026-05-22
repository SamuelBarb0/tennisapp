<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timezone of the venue. Used to convert event_time (which api-tennis sends
 * in tournament-local wall time) into UTC before storing scheduled_at, so
 * the "Cierra: HH:MM" label in Bogotá renders correctly regardless of
 * whether the tournament plays in Paris, London, NYC, etc.
 *
 * Stored as a TZ database identifier ("Europe/Paris", "America/New_York").
 * Defaults to America/Bogota for new rows: if we don't know better, treat
 * the times as already being in the user's home timezone.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('timezone', 64)->default('America/Bogota')->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
