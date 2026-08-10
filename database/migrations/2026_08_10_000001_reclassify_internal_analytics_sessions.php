<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('analytics_sessions')
            ->whereNull('utm_source')
            ->whereNotNull('initial_referrer_domain')
            ->orderBy('id')
            ->chunk(500, function ($sessions): void {
                foreach ($sessions as $session) {
                    $landingHost = $this->normalizedHost((string) parse_url($session->landing_url, PHP_URL_HOST));
                    $referrerHost = $this->normalizedHost($session->initial_referrer_domain);

                    if ($landingHost === '' || $landingHost !== $referrerHost) {
                        continue;
                    }

                    $referrerPath = parse_url($session->initial_referrer, PHP_URL_PATH) ?: '/';

                    DB::table('analytics_sessions')
                        ->where('id', $session->id)
                        ->update([
                            'source_key' => 'direct',
                            'initial_referrer' => substr($referrerPath, 0, 1000),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // A classificação anterior era incorreta e não deve ser restaurada.
    }

    private function normalizedHost(string $host): string
    {
        return preg_replace('/^www\./i', '', strtolower(trim($host, '.'))) ?? '';
    }
};
