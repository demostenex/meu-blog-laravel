<?php

namespace App\Jobs;

use App\Models\PageView;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordPageViewJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $path,
        public readonly ?string $referrer,
        public readonly string $device,
        public readonly string $ipHash,
    ) {}

    public function handle(): void
    {
        PageView::record($this->path, $this->referrer, $this->device, $this->ipHash);
    }
}
