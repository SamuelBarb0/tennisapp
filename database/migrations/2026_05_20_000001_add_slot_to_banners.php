<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            // Where in the public site this banner appears. Existing carousel banners
            // get 'home_carousel' (the default) so legacy data keeps working.
            // Currently supported: 'home_carousel', 'home_hero', 'prizes_hero'.
            $table->string('slot')->default('home_carousel')->after('is_hero')->index();
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn('slot');
        });
    }
};
