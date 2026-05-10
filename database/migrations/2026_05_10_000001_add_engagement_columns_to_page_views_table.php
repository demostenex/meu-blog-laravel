<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->text('user_agent')->nullable()->after('ip_hash');
            $table->char('view_token', 36)->nullable()->unique()->after('user_agent');
            $table->unsignedTinyInteger('scroll_depth')->nullable()->after('view_token');
            $table->unsignedSmallInteger('time_on_page')->nullable()->after('scroll_depth');
            $table->string('language', 10)->nullable()->after('time_on_page');
            $table->string('timezone', 50)->nullable()->after('language');
            $table->unsignedSmallInteger('screen_width')->nullable()->after('timezone');
            $table->boolean('is_bot')->default(false)->after('screen_width');

            $table->index('is_bot');
        });
    }

    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->dropIndex(['is_bot']);
            $table->dropUnique(['view_token']);
            $table->dropColumn([
                'user_agent', 'view_token', 'scroll_depth', 'time_on_page',
                'language', 'timezone', 'screen_width', 'is_bot',
            ]);
        });
    }
};
