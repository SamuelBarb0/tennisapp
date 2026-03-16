<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_round_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('round'); // R128, R64, R32, R16, QF, SF, F, etc.
            $table->integer('points')->default(10);
            $table->timestamps();

            $table->unique(['tournament_id', 'round']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_round_points');
    }
};
