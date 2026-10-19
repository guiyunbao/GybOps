<?php

namespace App\Console\Commands;

use App\Services\Operations\OperationsReview;
use Illuminate\Console\Command;

class GeoFlowOperationsReviewCommand extends Command
{
    protected $signature = 'geoflow:operations-review';

    protected $description = '检查运营知识敏感字段，并汇总近 7 日发布与线索';

    public function handle(OperationsReview $review): int
    {
        $report = $review->weekly();
        $this->info('近 7 日已发布文章：'.$report['published_articles']);
        $this->info('近 7 日线索提交：'.$report['lead_submissions']);
        if (! $report['clean']) {
            foreach ($report['violations'] as $violation) {
                $this->error($violation['partition'].'：'.implode(',', $violation['codes']));
            }

            return self::FAILURE;
        }

        $this->info('运营知识未发现电话、证件号、订单号或禁止工具。');

        return self::SUCCESS;
    }
}
