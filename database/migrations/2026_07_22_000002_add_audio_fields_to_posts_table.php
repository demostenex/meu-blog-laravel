<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('audio_path')->nullable()->after('content_en_error');
            $table->string('audio_status')->nullable()->after('audio_path');
            $table->string('audio_error')->nullable()->after('audio_status');
            $table->timestamp('audio_generated_at')->nullable()->after('audio_error');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['audio_path', 'audio_status', 'audio_error', 'audio_generated_at']);
        });
    }
};
