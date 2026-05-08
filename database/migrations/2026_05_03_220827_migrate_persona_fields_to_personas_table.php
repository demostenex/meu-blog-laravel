<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Para cada provider que tem dados de persona, cria uma UserAiPersona
        DB::table('user_ai_providers')
            ->whereNotNull('persona')
            ->orWhereNotNull('ai_name')
            ->orWhereNotNull('ai_photo')
            ->get()
            ->each(function ($provider) {
                $personaId = DB::table('user_ai_personas')->insertGetId([
                    'user_id'      => $provider->user_id,
                    'name'         => $provider->ai_name ?? 'Persona padrão',
                    'ai_name'      => $provider->ai_name,
                    'content'      => $provider->persona,
                    'ai_photo'     => $provider->ai_photo,
                    'accent_color' => $provider->accent_color ?? '#7c3aed',
                    'is_default'   => $provider->is_default,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                DB::table('user_ai_providers')
                    ->where('id', $provider->id)
                    ->update(['persona_id' => $personaId]);
            });

        Schema::table('user_ai_providers', function (Blueprint $table) {
            $table->dropColumn(['persona', 'ai_name', 'ai_photo', 'accent_color']);
        });
    }

    public function down(): void
    {
        Schema::table('user_ai_providers', function (Blueprint $table) {
            $table->text('persona')->nullable();
            $table->string('ai_name')->nullable();
            $table->string('ai_photo')->nullable();
            $table->string('accent_color', 7)->default('#7c3aed');
        });
    }
};
