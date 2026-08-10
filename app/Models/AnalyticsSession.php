<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalyticsSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'id', 'visitor_id', 'started_at', 'last_seen_at', 'landing_path',
        'landing_url', 'initial_referrer', 'initial_referrer_domain', 'source_key',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'device', 'browser', 'operating_system', 'ip_hash', 'country', 'is_bot',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'is_bot' => 'boolean',
    ];

    public function pageViews(): HasMany
    {
        return $this->hasMany(PageView::class, 'session_id');
    }
}
