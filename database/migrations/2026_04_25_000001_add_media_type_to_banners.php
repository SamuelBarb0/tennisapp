<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            // 'image' or 'video' — drives whether the carousel renders an <img> or <video>
            $table->string('media_type', 10)->default('image')->after('image');
            // External URL fallback (useful when video lives on CDN)
            $table->string('media_url')->nullable()->after('media_type');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['media_type', 'media_url']);
        });
    }
};
