<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DemoQueueLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected ?int $userId = null,
        protected string $message = 'Demo queue job executed successfully.'
    ) {
    }

    public function handle(): void
    {
        Log::info('DemoQueueLogJob processed', [
            'message' => $this->message,
            'user_id' => $this->userId,
            'processed_at' => now()->toDateTimeString(),
        ]);
    }
}
