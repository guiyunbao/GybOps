<?php

namespace App\Console\Commands;

use App\Jobs\SyncOfficialSourcesJob;
use App\Services\Operations\OfficialSourceCatalog;
use App\Services\Operations\OfficialSourceSync;
use Illuminate\Console\Command;
use Throwable;

class GeoFlowSyncOfficialSourcesCommand extends Command
{
    protected $signature = 'geoflow:sync-official-sources
        {--cadence= : weekday 或 weekly}
        {--sync : 在当前进程执行，不进入队列}';

    protected $description = '从官方列表页抓取稿件摘录并写入知识库';

    public function handle(OfficialSourceSync $sync): int
    {
        $cadence = trim((string) $this->option('cadence'));
        if (! in_array($cadence, [OfficialSourceCatalog::CADENCE_WEEKDAY, OfficialSourceCatalog::CADENCE_WEEKLY], true)) {
            $this->error('需要 --cadence=weekday 或 --cadence=weekly');

            return self::FAILURE;
        }

        if (! $this->option('sync')) {
            SyncOfficialSourcesJob::dispatch($cadence);
            $this->info('已入队官方信源：'.$cadence);

            return self::SUCCESS;
        }

        try {
            $result = $sync->sync($cadence);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '官方信源 %s：写入 %d，跳过 %d，拦截 %d',
            $cadence,
            $result['stored'],
            $result['skipped'],
            $result['rejected'],
        ));
        foreach ($result['notes'] as $note) {
            $this->line($note);
        }
        foreach ($result['failed'] as $failure) {
            $this->error($failure);
        }

        return $result['failed'] !== [] && $result['stored'] === 0 ? self::FAILURE : self::SUCCESS;
    }
}
