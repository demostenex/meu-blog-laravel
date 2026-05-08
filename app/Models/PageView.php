<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageView extends Model
{
    public $timestamps = false;

    protected $fillable = ['path', 'referrer', 'device', 'ip_hash', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public static function record(string $path, ?string $referrer, string $device, string $ipHash): void
    {
        static::create([
            'path'       => $path,
            'referrer'   => $referrer,
            'device'     => $device,
            'ip_hash'    => $ipHash,
            'created_at' => now(),
        ]);
    }
}
