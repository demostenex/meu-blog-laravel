<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('visitor_id')->index();
            $table->timestamp('started_at')->index();
            $table->timestamp('last_seen_at')->index();
            $table->string('landing_path', 500)->index();
            $table->string('landing_url', 1000);
            $table->string('initial_referrer', 1000)->nullable();
            $table->string('initial_referrer_domain', 255)->nullable()->index();
            $table->string('source_key', 100)->default('direct')->index();
            $table->string('utm_source', 255)->nullable()->index();
            $table->string('utm_medium', 255)->nullable();
            $table->string('utm_campaign', 255)->nullable();
            $table->string('utm_content', 255)->nullable();
            $table->string('utm_term', 255)->nullable();
            $table->enum('device', ['desktop', 'mobile', 'tablet'])->default('desktop');
            $table->string('browser', 100)->nullable();
            $table->string('operating_system', 100)->nullable();
            $table->string('ip_hash', 64);
            $table->string('country', 2)->nullable();
            $table->boolean('is_bot')->default(false)->index();
            $table->timestamps();
        });

        Schema::table('page_views', function (Blueprint $table) {
            $table->uuid('session_id')->nullable()->after('id');
            $table->string('page_referrer', 1000)->nullable()->after('referrer');
            $table->string('page_referrer_domain', 255)->nullable()->after('page_referrer');
            $table->foreign('session_id')->references('id')->on('analytics_sessions')->nullOnDelete();
            $table->index(['session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->dropForeign(['session_id']);
            $table->dropIndex(['session_id', 'created_at']);
            $table->dropColumn(['session_id', 'page_referrer', 'page_referrer_domain']);
        });

        Schema::dropIfExists('analytics_sessions');
    }
};
