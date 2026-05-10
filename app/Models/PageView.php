<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'path', 'referrer', 'device', 'ip_hash', 'created_at',
        'user_agent', 'view_token', 'scroll_depth', 'time_on_page',
        'language', 'timezone', 'screen_width', 'is_bot',
    ];

    protected $casts = [
        'created_at'   => 'datetime',
        'is_bot'       => 'boolean',
        'scroll_depth' => 'integer',
        'time_on_page' => 'integer',
        'screen_width' => 'integer',
    ];

    public static function record(
        string  $path,
        ?string $referrer,
        string  $device,
        string  $ipHash,
        ?string $userAgent,
        string  $viewToken,
        bool    $isBot,
    ): self {
        return static::create([
            'path'       => $path,
            'referrer'   => $referrer,
            'device'     => $device,
            'ip_hash'    => $ipHash,
            'user_agent' => $userAgent,
            'view_token' => $viewToken,
            'is_bot'     => $isBot,
            'created_at' => now(),
        ]);
    }
}
