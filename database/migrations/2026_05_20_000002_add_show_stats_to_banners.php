<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            // For hero slots (home_hero, prizes_hero) controls whether the
            // associated stats block is visible. Defaults to true so existing
            // heroes keep their stats on.
            $table->boolean('show_stats')->default(true)->after('slot');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn('show_stats');
        });
    }
};
