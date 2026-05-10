<?php

namespace App\Jobs;

use App\Models\PageView;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordPageViewJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string  $path,
        public readonly ?string $referrer,
        public readonly string  $device,
        public readonly string  $ipHash,
        public readonly ?string $userAgent,
        public readonly string  $viewToken,
        public readonly bool    $isBot,
    ) {}

    public function handle(): void
    {
        PageView::record(
            path:      $this->path,
            referrer:  $this->referrer,
            device:    $this->device,
            ipHash:    $this->ipHash,
            userAgent: $this->userAgent,
            viewToken: $this->viewToken,
            isBot:     $this->isBot,
        );
    }
}
