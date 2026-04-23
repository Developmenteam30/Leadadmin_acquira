<?php

namespace App\Jobs;

use App\Services\PushIncomingDataService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RetryOutboundRejectionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        protected int $idFeedOut,
        protected string $dateStart,
        protected string $dateEnd
    ) {
    }

    public function handle(): void
    {
        $summary = PushIncomingDataService::retryOutboundRejectionsForFeed(
            $this->idFeedOut,
            $this->dateStart,
            $this->dateEnd
        );

        Cache::put($this->cacheKey(), [
            'summary' => $summary,
            'message' => null,
            'submittedAt' => now()->toDateTimeString(),
            'status' => 'finished',
        ], now()->addDays(7));
    }

    public function failed(Throwable $exception): void
    {
        Cache::put($this->cacheKey(), [
            'summary' => [
                'total' => 0,
                'accepted' => 0,
                'rejected' => 0,
                'pending_manual' => 0,
                'pending_webhook' => 0,
                'errors' => 1,
            ],
            'message' => $exception->getMessage(),
            'submittedAt' => now()->toDateTimeString(),
            'status' => 'error',
        ], now()->addDays(7));
    }

    private function cacheKey(): string
    {
        return sprintf(
            'retry-outbound-rejections:%d:%s:%s',
            $this->idFeedOut,
            $this->dateStart,
            $this->dateEnd
        );
    }
}

