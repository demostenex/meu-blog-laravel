<?php

namespace App\Http\Controllers;

use App\Models\PageView;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AnalyticsEngageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'path'         => ['required', 'string', 'max:500'],
            'scroll_depth' => ['required', 'integer', 'min:0', 'max:100'],
            'time_on_page' => ['required', 'integer', 'min:0', 'max:86400'],
            'language'     => ['nullable', 'string', 'max:10'],
            'timezone'     => ['nullable', 'string', 'max:50'],
            'screen_width' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $ipHash = hash('sha256', $request->ip() . config('app.key'));

        PageView::where('ip_hash', $ipHash)
            ->where('path', $data['path'])
            ->where('is_bot', false)
            ->where('created_at', '>=', now()->subHours(2))
            ->whereNull('time_on_page')
            ->latest('created_at')
            ->first()
            ?->update([
                'scroll_depth' => $data['scroll_depth'],
                'time_on_page' => $data['time_on_page'],
                'language'     => $data['language'] ?? null,
                'timezone'     => $data['timezone'] ?? null,
                'screen_width' => $data['screen_width'] ?? null,
            ]);

        return response()->noContent();
    }
}
