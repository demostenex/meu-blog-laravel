<?php

namespace App\Jobs;

use App\Models\PageView;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordPageViewJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ?string $sessionId,
        public readonly string $path,
        public readonly ?string $referrer,
        public readonly ?string $pageReferrer,
        public readonly ?string $pageReferrerDomain,
        public readonly string $device,
        public readonly string $ipHash,
        public readonly ?string $userAgent,
        public readonly string $viewToken,
        public readonly bool $isBot,
    ) {}

    public function handle(): void
    {
        PageView::record(
            sessionId: $this->sessionId,
            path: $this->path,
            referrer: $this->referrer,
            pageReferrer: $this->pageReferrer,
            pageReferrerDomain: $this->pageReferrerDomain,
            device: $this->device,
            ipHash: $this->ipHash,
            userAgent: $this->userAgent,
            viewToken: $this->viewToken,
            isBot: $this->isBot,
        );
    }
}
