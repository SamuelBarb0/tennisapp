<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['ATP', 'WTA', 'GrandSlam']);
            $table->string('location');
            $table->string('city');
            $table->string('country');
            $table->string('surface')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('image')->nullable();
            $table->decimal('points_multiplier', 3, 1)->default(1.0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
