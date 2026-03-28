<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('social_x')->nullable()->after('about_me');
            $table->string('social_instagram')->nullable()->after('social_x');
            $table->string('social_facebook')->nullable()->after('social_instagram');
            $table->string('social_linkedin')->nullable()->after('social_facebook');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['social_x', 'social_instagram', 'social_facebook', 'social_linkedin']);
        });
    }
};
