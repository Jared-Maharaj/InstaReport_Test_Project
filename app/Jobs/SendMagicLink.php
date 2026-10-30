<?php

namespace App\Jobs;

use App\Services\MagicLinkService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMagicLink implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public string $email, public int $requestedAt) {}

    /**
     * Execute the job.
     */
    public function handle(MagicLinkService $links): void
    {
        if (now()->getTimestamp() - $this->requestedAt >= config('magiclink.expires_in_minutes') * 60) {
            return;
        }

        $links->send($this->email);
    }
}