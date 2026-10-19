<?php

namespace App\Jobs;

use App\Services\Operations\OperationsBriefSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * 工作日运营摘要任务。同一请求号只保留一个队列实例。
 */
final class SyncOperationsBriefJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 150;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly string $requestId) {}

    public function uniqueId(): string
    {
        return $this->requestId;
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['operations-brief', $this->requestId];
    }

    public function handle(OperationsBriefSync $sync): void
    {
        try {
            $sync->sync($this->requestId);
        } catch (Throwable $exception) {
            report($exception);
            throw $exception;
        }
    }
}
