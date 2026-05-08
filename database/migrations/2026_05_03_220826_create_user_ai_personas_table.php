<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_ai_personas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');          // nome da persona (ex: "Kikito Sarcástico")
            $table->string('ai_name')->nullable();     // nome exibido no blog (ex: "Kikito")
            $table->text('content')->nullable();       // prompt de sistema
            $table->string('ai_photo')->nullable();
            $table->string('accent_color', 7)->default('#7c3aed');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::table('user_ai_providers', function (Blueprint $table) {
            $table->foreignId('persona_id')->nullable()->constrained('user_ai_personas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_ai_providers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('persona_id');
        });

        Schema::dropIfExists('user_ai_personas');
    }
};
