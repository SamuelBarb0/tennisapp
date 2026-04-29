<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `players` columns from the SportRadar era are NOT NULL with no default,
 * which makes inserts from the Matchstat sync fail. Relax them to nullable so
 * the new sync flow doesn't have to know about every legacy field.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->string('country')->nullable()->change();
            $table->string('nationality_code')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Revert is risky if rows already have NULL — only enforce NOT NULL if you
        // know your data. Skipped on purpose.
    }
};
