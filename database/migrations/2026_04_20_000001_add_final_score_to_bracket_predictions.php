<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bracket_predictions', function (Blueprint $table) {
            $table->string('final_score_prediction', 20)->nullable()->after('points_earned');
        });
    }

    public function down(): void
    {
        Schema::table('bracket_predictions', function (Blueprint $table) {
            $table->dropColumn('final_score_prediction');
        });
    }
};
