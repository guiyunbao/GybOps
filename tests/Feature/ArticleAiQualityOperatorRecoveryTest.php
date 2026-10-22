<?php

namespace Tests\Feature;

use App\Exceptions\ArticleAiQualityGateException;
use App\Models\Admin;
use App\Models\AiModel;
use App\Models\AiQualityAuditEvent;
use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\KnowledgeBase;
use App\Models\Prompt;
use App\Models\Task;
use App\Services\GeoFlow\ArticleAiQualityGate;
use App\Services\GeoFlow\ArticleAiQualityInspectionService;
use App\Services\GeoFlow\ArticleAiQualityPolicyResolver;
use App\Services\GeoFlow\ArticleAiQualityTechnicalSkip;
use App\Services\GeoFlow\ArticlePublicationEligibilityService;
use App\Support\Admin\ArticleAiQualityProgressPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ArticleAiQualityOperatorRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_selection_overrides_the_task_prompt_and_model(): void
    {
        Queue::fake();
        [$admin, $article, $taskPrompt, $taskModel] = $this->fixture();
        $prompt = Prompt::query()->create([
            'name' => '物流质检方案',
            'type' => 'quality_check',
            'content' => (string) $taskPrompt->content,
        ]);
        $model = AiModel::query()->create([
            'name' => '备用质检模型',
            'version' => '1',
            'api_key' => 'test',
            'model_id' => 'quality-backup',
            'model_type' => 'chat',
            'api_url' => 'https://example.test',
            'status' => 'active',
        ]);
        $model->forceFill([
            'owner_admin_id' => $admin->id,
            'access_scope' => AiModel::ACCESS_SCOPE_USER_CONTENT,
        ])->save();

        app(ArticleAiQualityInspectionService::class)->requestManualInspection(
            $article,
            auditAdminId: $admin->id,
            dispatch: false,
            applyQualitySelection: true,
            hasPromptSelection: true,
            promptId: (int) $prompt->id,
            hasModelSelection: true,
            modelId: (int) $model->id,
        );

        $article->refresh();
        $policy = app(ArticleAiQualityPolicyResolver::class)->resolve($article);

        $this->assertSame((int) $prompt->id, (int) $article->ai_quality_prompt_id);
        $this->assertSame((int) $model->id, (int) $article->ai_quality_model_id);
        $this->assertSame((int) $prompt->id, (int) $policy['prompt']->id);
        $this->assertSame((int) $model->id, (int) $policy['model']->id);
        $this->assertNotSame((int) $taskModel->id, (int) $policy['model']->id);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.articles.edit', ['articleId' => $article->id]))
            ->post(route('admin.articles.ai-quality.recheck', ['articleId' => $article->id]), [
                'ai_quality_prompt_id' => '',
                'ai_quality_model_id' => '',
            ])
            ->assertRedirect(route('admin.articles.edit', ['articleId' => $article->id]));

        $article->refresh();
        $this->assertNull($article->ai_quality_prompt_id);
        $this->assertNull($article->ai_quality_model_id);
        $restored = app(ArticleAiQualityPolicyResolver::class)->resolve($article->fresh());
        $this->assertSame((int) $taskPrompt->id, (int) $restored['prompt']->id);
        $this->assertSame((int) $taskModel->id, (int) $restored['model']->id);
    }

    public function test_invalid_model_output_explains_the_validation_failure_without_mentioning_an_api_key(): void
    {
        [$admin, $article] = $this->fixture();
        $check = app(ArticleAiQualityInspectionService::class)->createOrReuse($article, dispatch: false);
        $check->forceFill([
            'status' => 'failed',
            'decision' => 'error',
            'score' => null,
            'error_code' => 'invalid_model_output',
            'execution_meta' => [
                'current_phase' => 'inspecting',
                'failure' => [
                    'code' => 'invalid_model_output',
                    'stage' => 'inspecting',
                    'validation_code' => 'ai_quality_result_structure_invalid',
                    'retryable' => false,
                ],
            ],
            'active_dedupe_key' => null,
            'finished_at' => now(),
        ])->save();

        $failure = app(ArticleAiQualityProgressPresenter::class)->snapshot($check->fresh())['failure'];

        $this->assertSame(__('admin.articles.ai_quality.validation_ai_quality_result_structure_invalid'), $failure['reason']);
        $this->assertSame(__('admin.articles.ai_quality.failure_next_step_invalid_output'), $failure['next_step']);
        $this->assertStringNotContainsString('API Key', $failure['next_step']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.articles.edit', ['articleId' => $article->id]))
            ->assertOk()
            ->assertSee(__('admin.articles.quality_scorecard.title'))
            ->assertSee(__('admin.articles.ai_quality.decision_failed'))
            ->assertSee(__('admin.articles.ai_quality.failure_stage_inspecting'))
            ->assertSee($failure['reason'])
            ->assertSee('data-ai-quality-failure-action="switch-model"', false)
            ->assertSee('data-ai-quality-technical-skip', false)
            ->assertDontSee(__('admin.articles.ai_quality.failure_next_step_configuration'));
    }

    public function test_technical_failure_can_be_skipped_until_the_article_changes(): void
    {
        [$admin, $article] = $this->fixture();
        $service = app(ArticleAiQualityInspectionService::class);
        $check = $service->createOrReuse($article, dispatch: false);
        $service->markFailed($check, new \UnexpectedValueException('ai_quality_result_structure_invalid'));
        $check = $check->fresh();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.articles.ai-quality.skip', ['articleId' => $article->id]), [
                'technical_skip_reason' => '模型无法返回质检结构，先人工发布。',
            ])
            ->assertRedirect(route('admin.articles.edit', ['articleId' => $article->id]));

        $check->refresh();
        $this->assertTrue(app(ArticleAiQualityTechnicalSkip::class)->authorizes($check));
        $this->assertSame((string) $check->input_fingerprint, data_get($check->execution_meta, 'technical_skip.fingerprint'));
        $this->assertTrue(AiQualityAuditEvent::query()->where('event_type', 'article_quality_technical_skip')->exists());
        $this->assertNotContains(
            'ai_quality_failed',
            app(ArticlePublicationEligibilityService::class)->evaluate($article->fresh())['blocking_reasons'],
        );
        $this->assertTrue(app(ArticleAiQualityGate::class)->check($article->fresh(), 'admin_publish', $admin->id)->is($check));

        $article->update(['content' => $article->content.' 已修改。']);

        $this->expectException(ArticleAiQualityGateException::class);
        app(ArticleAiQualityGate::class)->check($article->fresh(), 'admin_publish', $admin->id);
    }

    public function test_blocked_content_cannot_skip_the_quality_gate(): void
    {
        [$admin, $article] = $this->fixture();
        $check = app(ArticleAiQualityInspectionService::class)->createOrReuse($article, dispatch: false);
        $check->forceFill([
            'status' => 'completed',
            'decision' => 'blocked',
            'score' => 40,
            'active_dedupe_key' => null,
            'finished_at' => now(),
        ])->save();

        $this->actingAs($admin, 'admin')
            ->from(route('admin.articles.edit', ['articleId' => $article->id]))
            ->post(route('admin.articles.ai-quality.skip', ['articleId' => $article->id]), [
                'technical_skip_reason' => '想跳过内容拦截。',
            ])
            ->assertRedirect(route('admin.articles.edit', ['articleId' => $article->id]))
            ->assertSessionHasErrors();

        $this->assertFalse(app(ArticleAiQualityTechnicalSkip::class)->authorizes($check->fresh()));
    }

    /** @return array{0: Admin, 1: Article, 2: Prompt, 3: AiModel} */
    private function fixture(): array
    {
        $admin = Admin::query()->create([
            'username' => 'quality-operator-'.uniqid(),
            'password' => 'secret-123',
            'display_name' => '质检运营',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $prompt = Prompt::query()->where('system_key', 'article_quality.cn_ads_knowledge.v1')->firstOrFail();
        $model = AiModel::query()->create([
            'name' => '任务质检模型 '.uniqid(),
            'version' => '1',
            'api_key' => 'test',
            'model_id' => 'quality-task-'.uniqid(),
            'model_type' => 'chat',
            'api_url' => 'https://example.test',
            'status' => 'active',
        ]);
        $model->forceFill([
            'owner_admin_id' => $admin->id,
            'access_scope' => AiModel::ACCESS_SCOPE_USER_CONTENT,
        ])->save();
        $knowledge = KnowledgeBase::query()->create([
            'name' => '质检知识库 '.uniqid(),
            'content' => '干线时效以知识库记载为准。',
            'review_status' => 'reviewed',
        ]);
        $task = Task::query()->create([
            'name' => '质检任务 '.uniqid(),
            'status' => 'active',
            'schedule_enabled' => 1,
            'ai_model_id' => $model->id,
            'ai_quality_enabled' => true,
            'ai_quality_prompt_id' => $prompt->id,
            'ai_quality_model_id' => $model->id,
            'ai_quality_pass_score' => 85,
            'ai_quality_manual_override_min_score' => 70,
            'need_review' => 0,
        ]);
        $task->knowledgeBases()->sync([$knowledge->id => ['sort_order' => 0]]);
        $category = Category::query()->create(['name' => '分类 '.uniqid(), 'slug' => 'quality-operator-'.uniqid()]);
        $author = Author::query()->create(['name' => '作者 '.uniqid()]);
        $article = Article::query()->create([
            'title' => '待质检文章',
            'slug' => 'quality-operator-'.uniqid(),
            'content' => '干线运输按约定线路执行。',
            'category_id' => $category->id,
            'author_id' => $author->id,
            'task_id' => $task->id,
            'status' => 'draft',
            'review_status' => 'approved',
            'publication_intent' => 'immediate',
        ]);

        return [$admin, $article, $prompt, $model];
    }
}
