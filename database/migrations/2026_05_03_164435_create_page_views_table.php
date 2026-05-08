<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('path', 500);
            $table->string('referrer', 500)->nullable();
            $table->enum('device', ['desktop', 'mobile', 'tablet'])->default('desktop');
            $table->string('ip_hash', 64);
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index('path');
            $table->index('referrer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
