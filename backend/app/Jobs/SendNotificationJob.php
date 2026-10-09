<?php

namespace App\Jobs;

use App\Models\Notification\NotificationLog;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Delivers one queued notification.
 *
 * The log id is passed instead of the model so the job stays small on the
 * queue. The row is loaded across tenants because the job runs without an
 * active tenant (workers are tenant-agnostic); the tenant_id is already stamped
 * on the row when it was queued.
 */
class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public int $logId) {}

    public function handle(NotificationService $notifications): void
    {
        $log = NotificationLog::allTenants()->find($this->logId);

        if ($log === null) {
            return;
        }

        $notifications->deliver($log);
    }

    public function failed(Throwable $exception): void
    {
        $log = NotificationLog::allTenants()->find($this->logId);

        $log?->markFailed($exception->getMessage());
    }
}
