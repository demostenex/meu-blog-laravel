<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE posts ALTER COLUMN audio_error TYPE TEXT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE posts ALTER COLUMN audio_error TYPE VARCHAR(255)');
    }
};
