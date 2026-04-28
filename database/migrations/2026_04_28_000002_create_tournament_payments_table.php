<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('preference_id')->nullable()->index();
            $table->string('mp_payment_id')->nullable()->index();
            // pending | approved | rejected | cancelled | refunded
            $table->string('status', 20)->default('pending');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('COP');
            $table->json('mp_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            // A user can only successfully pay once per tournament; we enforce uniqueness
            // on (user, tournament) only for the approved row via app logic, since the
            // user might have multiple pending/rejected attempts.
            $table->index(['user_id', 'tournament_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_payments');
    }
};
