<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_ai_models', function (Blueprint $table) {
            $table->string('capability')->default('text')->after('model');
            // values: 'text' | 'image'
        });
    }

    public function down(): void
    {
        Schema::table('user_ai_models', function (Blueprint $table) {
            $table->dropColumn('capability');
        });
    }
};
