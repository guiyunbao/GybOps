<?php

namespace App\Console\Commands;

use App\Jobs\SyncOperationsBriefJob;
use App\Services\Operations\OperationsBriefSync;
use Illuminate\Console\Command;
use Throwable;

class GeoFlowSyncOperationsBriefCommand extends Command
{
    protected $signature = 'geoflow:sync-operations-brief
        {--request-id= : 稳定请求号，默认按日期生成}
        {--region= : 摘要区域}
        {--cargo= : 货类}
        {--sync : 在当前进程执行，不进入队列}';

    protected $description = '从 Dify 拉取只读运营摘要并写入知识库';

    public function handle(OperationsBriefSync $sync): int
    {
        $requestId = trim((string) $this->option('request-id'));
        if ($requestId === '') {
            $requestId = 'operations-brief-'.now()->toDateString();
        }

        if (! $this->option('sync')) {
            SyncOperationsBriefJob::dispatch($requestId);
            $this->info('已入队运营摘要：'.$requestId);

            return self::SUCCESS;
        }

        try {
            $run = $sync->sync(
                $requestId,
                $this->option('region') ? (string) $this->option('region') : null,
                $this->option('cargo') ? (string) $this->option('cargo') : null,
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('运营摘要 '.$run->status.'：'.$run->request_id);
        if ($run->status === 'failed') {
            $this->error((string) $run->error_message);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
