<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->text('cover_image_prompt')->nullable()->after('cover_image');
            $table->boolean('cover_image_use_content')->default(false)->after('cover_image_prompt');
            $table->boolean('cover_image_use_bio')->default(false)->after('cover_image_use_content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['cover_image_prompt', 'cover_image_use_content', 'cover_image_use_bio']);
        });
    }
};
