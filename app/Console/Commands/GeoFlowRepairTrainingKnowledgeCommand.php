<?php

namespace App\Console\Commands;

use App\Services\Operations\TrainingKnowledgeRepair;
use Illuminate\Console\Command;

class GeoFlowRepairTrainingKnowledgeCommand extends Command
{
    protected $signature = 'geoflow:repair-training-knowledge';

    protected $description = '用可公开摘要替代培训系统同步失败的资料';

    public function handle(TrainingKnowledgeRepair $repair): int
    {
        $files = $repair->apply();
        foreach ($files as $file) {
            $this->info('已写入培训修复摘要：'.$file);
        }

        return self::SUCCESS;
    }
}
