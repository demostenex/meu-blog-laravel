<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_post', function (Blueprint $table) {
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['document_id', 'post_id']);
        });

        DB::table('documents')
            ->whereNotNull('post_id')
            ->orderBy('id')
            ->select(['id', 'post_id'])
            ->chunkById(500, function ($documents) {
                $now = now();

                $rows = $documents->map(fn ($document) => [
                    'document_id' => $document->id,
                    'post_id' => $document->post_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('document_post')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_post');
    }
};
