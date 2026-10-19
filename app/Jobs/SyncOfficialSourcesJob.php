<?php

namespace App\Jobs;

use App\Services\Operations\OfficialSourceSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * 按工作日或每周节奏抓取官方信源。同一节奏只保留一个队列实例。
 */
final class SyncOfficialSourcesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly string $cadence) {}

    public function uniqueId(): string
    {
        return $this->cadence;
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['official-sources', $this->cadence];
    }

    public function handle(OfficialSourceSync $sync): void
    {
        try {
            $sync->sync($this->cadence);
        } catch (Throwable $exception) {
            report($exception);
            throw $exception;
        }
    }
}
