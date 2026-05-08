<?php

namespace App\Http\CacheProfiles;

use Illuminate\Http\Request;
use Spatie\ResponseCache\CacheProfiles\CacheAllSuccessfulGetRequests;

class PublicOnlyCacheProfile extends CacheAllSuccessfulGetRequests
{
    public function shouldCacheRequest(Request $request): bool
    {
        if (auth()->check()) {
            return false;
        }

        // Livewire envia requests de update via POST — já filtrado pelo parent,
        // mas também faz polling via GET com header X-Livewire em alguns casos.
        if ($request->hasHeader('X-Livewire')) {
            return false;
        }

        return parent::shouldCacheRequest($request);
    }
}
