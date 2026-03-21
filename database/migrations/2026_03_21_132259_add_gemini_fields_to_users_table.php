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
        Schema::table('users', function (Blueprint $table) {
            $table->string('gemini_api_key')->nullable()->after('about_me');
            $table->string('gemini_model')->default('gemini-2.0-flash')->after('gemini_api_key');
            $table->string('gemini_ai_name')->default('BOT Sarcástico')->after('gemini_model');
            $table->string('gemini_ai_photo')->nullable()->after('gemini_ai_name');
            $table->text('gemini_persona')->nullable()->after('gemini_ai_photo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gemini_api_key', 'gemini_model', 'gemini_ai_name', 'gemini_ai_photo', 'gemini_persona']);
        });
    }
};
