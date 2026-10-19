<?php

namespace App\Services\Operations;

use App\Models\Article;
use App\Models\KnowledgeBase;
use App\Services\Admin\Analytics\LeadAnalyticsFilter;
use App\Services\Admin\Analytics\LeadAnalyticsService;
use Illuminate\Support\Carbon;

/**
 * 汇总近 7 日发布与线索，并检查运营知识是否含敏感字段。
 */
final class OperationsReview
{
    public function __construct(
        private readonly OperationsKnowledgeProvisioner $knowledge,
        private readonly OperationsKnowledgeGuard $guard,
        private readonly LeadAnalyticsService $leads,
    ) {}

    /**
     * @return array{violations:list<array{partition:string,codes:list<string>}>,published_articles:int,lead_submissions:int,clean:bool}
     */
    public function weekly(?Carbon $end = null): array
    {
        $end ??= now();
        $start = $end->copy()->subDays(6)->startOfDay();
        $bases = $this->knowledge->ensure();
        $violations = [];
        foreach ($bases as $key => $base) {
            if (! $base instanceof KnowledgeBase) {
                continue;
            }
            $codes = $this->guard->violations((string) $base->content);
            if ($codes !== []) {
                $violations[] = ['partition' => $key, 'codes' => $codes];
            }
        }

        $published = Article::query()
            ->where('status', 'published')
            ->whereBetween('published_at', [$start, $end->copy()->endOfDay()])
            ->count();
        $leadSummary = $this->leads->summary(LeadAnalyticsFilter::fromRequest([
            'lead_preset' => 'custom',
            'lead_date_from' => $start->toDateString(),
            'lead_date_to' => $end->toDateString(),
        ]));

        return [
            'violations' => $violations,
            'published_articles' => $published,
            'lead_submissions' => (int) data_get($leadSummary, 'kpis.submissions', 0),
            'clean' => $violations === [],
        ];
    }
}
