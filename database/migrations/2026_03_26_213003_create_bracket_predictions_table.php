<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bracket_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('round');          // R128, R64, R32, R16, QF, SF, F
            $table->integer('position');       // Position within the round (1-based)
            $table->foreignId('predicted_winner_id')->constrained('players')->cascadeOnDelete();
            $table->boolean('is_correct')->nullable();
            $table->integer('points_earned')->default(0);
            $table->timestamps();

            $table->unique(['tournament_id', 'user_id', 'round', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bracket_predictions');
    }
};
