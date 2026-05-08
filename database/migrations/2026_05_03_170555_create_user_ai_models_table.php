<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_ai_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_ai_provider_id')->constrained()->cascadeOnDelete();
            $table->string('model');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_ai_models');
    }
};
