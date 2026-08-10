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
            'view_token' => ['required', 'uuid'],
            'scroll_depth' => ['required', 'integer', 'min:0', 'max:100'],
            'time_on_page' => ['required', 'integer', 'min:0', 'max:86400'],
            'language' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'screen_width' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        PageView::where('view_token', $data['view_token'])
            ->where('is_bot', false)
            ->where('created_at', '>=', now()->subHours(2))
            ->update([
                'scroll_depth' => $data['scroll_depth'],
                'time_on_page' => $data['time_on_page'],
                'language' => $data['language'] ?? null,
                'timezone' => $data['timezone'] ?? null,
                'screen_width' => $data['screen_width'] ?? null,
            ]);

        return response()->noContent();
    }
}
