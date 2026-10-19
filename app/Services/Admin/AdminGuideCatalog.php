<?php

namespace App\Services\Admin;

use App\Models\Admin;

/**
 * 后台第一次闭环的分步引导目录。
 *
 * 按路由名注册。选择器只指向 data-gf-guide，不依赖按钮文案或 class。
 */
class AdminGuideCatalog
{
    /**
     * 当前路由上的引导载荷。没有引导时返回 null。
     *
     * @return array<string, mixed>|null
     */
    public function payload(Admin $admin, ?string $routeName): ?array
    {
        $tour = $this->tours()[$routeName] ?? null;
        if (! is_array($tour)) {
            return null;
        }

        $progress = is_array($admin->guide_progress) ? $admin->guide_progress : [];
        $guideId = (string) $tour['id'];

        return [
            'id' => $guideId,
            'autoOpen' => ! array_key_exists($guideId, $progress),
            'completeUrl' => route('admin.guides.complete'),
            'labels' => [
                'next' => __('admin.page_guide.next'),
                'back' => __('admin.page_guide.back'),
                'skip' => __('admin.page_guide.skip'),
                'done' => __('admin.page_guide.done'),
                'progress' => __('admin.page_guide.progress'),
            ],
            'steps' => array_map(static function (array $step): array {
                return [
                    'target' => (string) $step['target'],
                    'title' => __((string) $step['title']),
                    'body' => __((string) $step['body']),
                    'placement' => (string) ($step['placement'] ?? 'bottom'),
                ];
            }, $tour['steps']),
        ];
    }

    /**
     * 引导 id 是否在目录中。
     */
    public function has(string $guideId): bool
    {
        foreach ($this->tours() as $tour) {
            if (($tour['id'] ?? null) === $guideId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array{id: string, steps: list<array{target: string, title: string, body: string, placement?: string}>}>
     */
    private function tours(): array
    {
        return [
            'admin.ai-models.index' => [
                'id' => 'ai-models',
                'steps' => [
                    [
                        'target' => '[data-gf-guide="models-create"]',
                        'title' => 'admin.page_guide.ai_models.create_title',
                        'body' => 'admin.page_guide.ai_models.create_body',
                        'placement' => 'bottom',
                    ],
                    [
                        'target' => '[data-gf-guide="models-list"]',
                        'title' => 'admin.page_guide.ai_models.list_title',
                        'body' => 'admin.page_guide.ai_models.list_body',
                        'placement' => 'top',
                    ],
                    [
                        'target' => '[data-gf-guide="models-defaults"]',
                        'title' => 'admin.page_guide.ai_models.defaults_title',
                        'body' => 'admin.page_guide.ai_models.defaults_body',
                        'placement' => 'bottom',
                    ],
                ],
            ],
            'admin.title-libraries.index' => [
                'id' => 'title-libraries',
                'steps' => [
                    [
                        'target' => '[data-gf-guide="titles-nav"]',
                        'title' => 'admin.page_guide.titles.nav_title',
                        'body' => 'admin.page_guide.titles.nav_body',
                        'placement' => 'bottom',
                    ],
                    [
                        'target' => '[data-gf-guide="titles-create"]',
                        'title' => 'admin.page_guide.titles.create_title',
                        'body' => 'admin.page_guide.titles.create_body',
                        'placement' => 'bottom',
                    ],
                    [
                        'target' => '[data-gf-guide="titles-list"]',
                        'title' => 'admin.page_guide.titles.list_title',
                        'body' => 'admin.page_guide.titles.list_body',
                        'placement' => 'top',
                    ],
                ],
            ],
            'admin.materials.index' => [
                'id' => 'knowledge-bases',
                'steps' => [
                    [
                        'target' => '[data-gf-guide="knowledge-create"]',
                        'title' => 'admin.page_guide.knowledge.create_title',
                        'body' => 'admin.page_guide.knowledge.create_body',
                        'placement' => 'bottom',
                    ],
                    [
                        'target' => '[data-gf-guide="knowledge-hub"]',
                        'title' => 'admin.page_guide.knowledge.write_title',
                        'body' => 'admin.page_guide.knowledge.write_body',
                        'placement' => 'bottom',
                    ],
                    [
                        'target' => '[data-gf-guide="knowledge-list"]',
                        'title' => 'admin.page_guide.knowledge.list_title',
                        'body' => 'admin.page_guide.knowledge.list_body',
                        'placement' => 'bottom',
                    ],
                ],
            ],
            'admin.tasks.index' => [
                'id' => 'tasks',
                'steps' => [
                    [
                        'target' => '[data-gf-guide="tasks-create"]',
                        'title' => 'admin.page_guide.tasks.create_title',
                        'body' => 'admin.page_guide.tasks.create_body',
                        'placement' => 'bottom',
                    ],
                    [
                        'target' => '[data-gf-guide="tasks-overview"]',
                        'title' => 'admin.page_guide.tasks.overview_title',
                        'body' => 'admin.page_guide.tasks.overview_body',
                        'placement' => 'top',
                    ],
                    [
                        'target' => '[data-gf-guide="tasks-workers"]',
                        'title' => 'admin.page_guide.tasks.workers_title',
                        'body' => 'admin.page_guide.tasks.workers_body',
                        'placement' => 'bottom',
                    ],
                    [
                        'target' => '[data-gf-guide="tasks-jobs"]',
                        'title' => 'admin.page_guide.tasks.jobs_title',
                        'body' => 'admin.page_guide.tasks.jobs_body',
                        'placement' => 'bottom',
                    ],
                ],
            ],
            'admin.articles.index' => [
                'id' => 'articles',
                'steps' => [
                    [
                        'target' => '[data-gf-guide="articles-list"]',
                        'title' => 'admin.page_guide.articles.list_title',
                        'body' => 'admin.page_guide.articles.list_body',
                        'placement' => 'bottom',
                    ],
                    [
                        'target' => '[data-gf-guide="articles-table"]',
                        'title' => 'admin.page_guide.articles.draft_title',
                        'body' => 'admin.page_guide.articles.draft_body',
                        'placement' => 'top',
                    ],
                    [
                        'target' => '[data-gf-guide="articles-review"]',
                        'title' => 'admin.page_guide.articles.review_title',
                        'body' => 'admin.page_guide.articles.review_body',
                        'placement' => 'bottom',
                    ],
                    [
                        'target' => '[data-gf-guide="articles-publish"]',
                        'title' => 'admin.page_guide.articles.publish_title',
                        'body' => 'admin.page_guide.articles.publish_body',
                        'placement' => 'bottom',
                    ],
                ],
            ],
        ];
    }
}
