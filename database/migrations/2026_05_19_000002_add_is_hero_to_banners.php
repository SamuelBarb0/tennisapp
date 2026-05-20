<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            // When true, this banner replaces the hardcoded "Predice. Compite. Gana."
            // slide 0 in the home carousel. Only one banner should be the hero at a
            // time (we don't enforce uniqueness — the home picks the first match).
            $table->boolean('is_hero')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn('is_hero');
        });
    }
};
