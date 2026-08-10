<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'session_id', 'path', 'referrer', 'page_referrer', 'page_referrer_domain', 'device', 'ip_hash', 'created_at',
        'user_agent', 'view_token', 'scroll_depth', 'time_on_page',
        'language', 'timezone', 'screen_width', 'is_bot',
    ];

    public function analyticsSession(): BelongsTo
    {
        return $this->belongsTo(AnalyticsSession::class, 'session_id');
    }

    protected $casts = [
        'created_at' => 'datetime',
        'is_bot' => 'boolean',
        'scroll_depth' => 'integer',
        'time_on_page' => 'integer',
        'screen_width' => 'integer',
    ];

    public static function record(
        ?string $sessionId,
        string $path,
        ?string $referrer,
        ?string $pageReferrer,
        ?string $pageReferrerDomain,
        string $device,
        string $ipHash,
        ?string $userAgent,
        string $viewToken,
        bool $isBot,
    ): self {
        return static::create([
            'session_id' => $sessionId,
            'path' => $path,
            'referrer' => $referrer,
            'page_referrer' => $pageReferrer,
            'page_referrer_domain' => $pageReferrerDomain,
            'device' => $device,
            'ip_hash' => $ipHash,
            'user_agent' => $userAgent,
            'view_token' => $viewToken,
            'is_bot' => $isBot,
            'created_at' => now(),
        ]);
    }
}
