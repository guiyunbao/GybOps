<?php

namespace App\Services\Operations;

use App\Models\Article;
use App\Models\ArticleReview;
use App\Models\Author;
use App\Models\Category;
use App\Models\Task;
use App\Services\GeoFlow\ArticleRiskScanner;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 用车型与货物知识生成草稿，完成风险扫描、人工审核记录和主站发布。
 */
final class OperationsContentPilot
{
    public function __construct(
        private readonly OperationsKnowledgeProvisioner $knowledge,
        private readonly OperationsKnowledgeGuard $guard,
        private readonly ArticleRiskScanner $riskScanner,
    ) {}

    public function publish(?int $adminId = null): Article
    {
        $bases = $this->knowledge->ensure();
        $public = $bases[OperationsKnowledgeCatalog::PUBLIC_SCIENCE];
        $brief = $bases[OperationsKnowledgeCatalog::OPERATIONS_BRIEF];
        $content = $this->draft($public->content);
        $this->guard->assertClean($content);

        $category = Category::query()->firstOrCreate(
            ['slug' => 'operations-cargo'],
            ['name' => '大宗运输', 'description' => '车型与货物科普'],
        );
        $author = Author::query()->firstOrCreate(
            ['name' => '桂运宝内容'],
            ['email' => '', 'bio' => '运营内容'],
        );
        $task = Task::query()->firstOrCreate(
            ['name' => '车型货物内容试点'],
            [
                'status' => 'paused',
                'schedule_enabled' => 0,
                'need_review' => 1,
                'publish_scope' => 'local_only',
            ],
        );
        $task->knowledgeBases()->sync([
            $public->getKey() => ['sort_order' => 0],
            $brief->getKey() => ['sort_order' => 1],
        ]);

        $article = Article::query()->firstOrCreate(
            ['slug' => 'freight-vehicle-and-cargo'],
            [
                'title' => '货运车型与八大类货物运输要点',
                'excerpt' => '根据对外科普知识整理的车型与货物说明。',
                'content' => $content,
                'category_id' => $category->getKey(),
                'author_id' => $author->getKey(),
                'task_id' => $task->getKey(),
                'status' => 'draft',
                'review_status' => 'pending',
                'publication_intent' => 'hold',
                'ai_quality_required_at_creation' => false,
                'generation_evidence_snapshot' => json_encode([
                    'knowledge_base_ids' => [(int) $public->getKey(), (int) $brief->getKey()],
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ],
        );
        $article->forceFill([
            'content' => $content,
            'task_id' => $task->getKey(),
        ])->save();

        $scan = $this->riskScanner->record($article->fresh(), 'operations_pilot', $adminId);
        if ($scan->status !== 'clean') {
            throw new RuntimeException('operations_pilot_risk_'.$scan->status);
        }

        $article->forceFill([
            'review_status' => 'approved',
            'status' => 'published',
            'publication_intent' => 'none',
            'published_at' => now(),
        ])->save();
        ArticleReview::query()->create([
            'article_id' => $article->getKey(),
            'admin_id' => $adminId,
            'review_status' => 'approved',
            'review_note' => '车型与货物内容人工确认后发布',
            'content_hash' => $article->fresh()->reviewContentHash(),
        ]);

        return $article->fresh();
    }

    public function draft(string $knowledge): string
    {
        $vehicle = $this->section($knowledge, '货运车型');
        $cargo = $this->section($knowledge, '八大类货物');
        if ($vehicle === '' || $cargo === '') {
            throw new RuntimeException('operations_pilot_knowledge_incomplete');
        }

        return "# 货运车型与八大类货物\n\n".$vehicle."\n\n".$cargo."\n\n需要运力咨询时，请提交官网表单，不要在正文留下个人联系方式。";
    }

    private function section(string $knowledge, string $heading): string
    {
        $parts = preg_split('/^# /m', $knowledge) ?: [];
        foreach ($parts as $part) {
            [$title, $body] = array_pad(preg_split("/\r\n|\n|\r/", $part, 2) ?: [], 2, '');
            if (trim((string) $title) !== $heading) {
                continue;
            }

            $body = preg_replace('/\n# .*/s', '', (string) $body) ?? '';

            return Str::of($body)->trim()->toString();
        }

        return '';
    }
}
