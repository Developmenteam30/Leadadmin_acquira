<?php

namespace App\Jobs;

use App\Services\PushIncomingDataService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ResendPendingMarketplaceJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        protected int $idFeedOut,
        protected int $chunkSize = 200
    ) {
    }

    public function handle(): void
    {
        $summary = PushIncomingDataService::resendPendingMarketplaceForFeed($this->idFeedOut, $this->chunkSize);
        $this->writeMeta([
            'summary' => $summary,
            'message' => null,
            'submittedAt' => now()->toDateTimeString(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $this->writeMeta([
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
        ]);
    }

    protected function writeMeta(array $meta): void
    {
        $batch = $this->batch();
        if (!$batch) {
            return;
        }
        Cache::put('resend-pending-marketplace:' . $batch->id, $meta, now()->addDays(7));
    }
}
