<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks which mass emails have been sent for each tournament so the scheduler
 * doesn't re-send the same blast (e.g. opening, countdown, closing).
 *
 * Per-user emails (welcome, prediction confirmation) don't need this table —
 * those are dispatched inline at the moment of the event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_email_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            // 'opening' | 'countdown' | 'closing'
            $table->string('kind', 32);
            $table->timestamp('sent_at');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();

            $table->unique(['tournament_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_email_log');
    }
};
