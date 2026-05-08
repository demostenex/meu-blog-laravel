<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate existing gemini config from users → new tables
        DB::table('users')
            ->whereNotNull('gemini_api_key')
            ->get()
            ->each(function ($user) {
                $providerId = DB::table('user_ai_providers')->insertGetId([
                    'user_id'      => $user->id,
                    'provider'     => 'gemini',
                    'api_key'      => $user->gemini_api_key,
                    'persona'      => $user->gemini_persona,
                    'ai_name'      => $user->gemini_ai_name,
                    'ai_photo'     => $user->gemini_ai_photo,
                    'accent_color' => $user->gemini_accent_color ?? '#7c3aed',
                    'is_default'   => true,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                $model = $user->gemini_model ?? 'gemini-2.0-flash';
                DB::table('user_ai_models')->insert([
                    'user_ai_provider_id' => $providerId,
                    'model'               => $model,
                    'is_default'          => true,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'gemini_api_key',
                'gemini_model',
                'gemini_persona',
                'gemini_ai_name',
                'gemini_ai_photo',
                'gemini_accent_color',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('gemini_api_key')->nullable();
            $table->string('gemini_model')->default('gemini-2.0-flash');
            $table->text('gemini_persona')->nullable();
            $table->string('gemini_ai_name')->nullable();
            $table->string('gemini_ai_photo')->nullable();
            $table->string('gemini_accent_color', 7)->default('#7c3aed');
        });
    }
};
