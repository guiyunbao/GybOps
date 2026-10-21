<?php

namespace Tests\Feature;

use App\Ai\Agents\ContentPromptDraftAgent;
use App\Models\Admin;
use App\Models\AiModel;
use App\Models\AiModelUsageEvent;
use App\Models\Prompt;
use App\Support\AdminUiRegistry;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\LogisticsContentPromptCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAiPromptDraftTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private AiModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::query()->create([
            'username' => 'prompt_draft_admin',
            'password' => 'secret-123',
            'email' => 'prompt-draft@example.test',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $this->model = AiModel::query()->create([
            'name' => '草稿模型',
            'version' => 'test',
            'api_key' => app(ApiKeyCrypto::class)->encrypt('test-key'),
            'model_id' => 'test-chat-model',
            'model_type' => 'chat',
            'api_url' => 'https://ai.test',
            'daily_limit' => 0,
            'used_today' => 0,
            'total_used' => 0,
            'status' => 'active',
        ]);
        $this->model->forceFill([
            'owner_admin_id' => $this->admin->id,
            'access_scope' => AiModel::ACCESS_SCOPE_USER_CONTENT,
        ])->save();
        $this->actingAs($this->admin, 'admin');
    }

    public function test_logistics_prompts_are_seeded_once_and_listed(): void
    {
        foreach (LogisticsContentPromptCatalog::NAMES as $name) {
            $this->assertSame(1, Prompt::query()->where('name', $name)->where('type', 'content')->count());
        }

        $migration = require database_path('migrations/2026_10_21_160000_add_logistics_content_prompts.php');
        $migration->up();
        $migration->up();
        $this->assertSame(4, Prompt::query()->whereIn('name', array_values(LogisticsContentPromptCatalog::NAMES))->count());

        $this->get(route('admin.ai-prompts'))
            ->assertOk()
            ->assertSee('物流服务能力·信任型正文')
            ->assertSee('物流方案选型·决策指南')
            ->assertSee('物流场景方案·解决方案正文')
            ->assertSee('物流客户决策·问答型正文');

        $migration->down();
        $this->assertSame(0, Prompt::query()->whereIn('name', array_values(LogisticsContentPromptCatalog::NAMES))->count());
        $migration->up();
    }

    public function test_draft_routes_are_endpoints_and_guests_are_rejected(): void
    {
        $registry = app(AdminUiRegistry::class);
        $this->assertSame('endpoint', $registry->routeClassification('admin.ai-prompts.drafts'));
        $this->assertSame('endpoint', $registry->routeClassification('admin.ai-prompts.drafts.ai'));
        $this->assertFalse($registry->shouldRememberRoute('admin.ai-prompts.drafts'));

        auth('admin')->logout();

        $this->postJson(route('admin.ai-prompts.drafts'), ['scenario' => 'service_trust'])->assertUnauthorized();
    }

    public function test_template_draft_fills_a_logistics_prompt_without_saving(): void
    {
        $before = Prompt::query()->count();

        $draft = $this->postJson(route('admin.ai-prompts.drafts'), [
            'scenario' => 'service_trust',
            'brand' => '示例物流',
            'services' => '干线 {{content}}',
        ])->assertOk()
            ->assertJsonPath('name', '物流服务能力·信任型正文')
            ->json();

        $this->assertStringContainsString('公司或品牌：示例物流', $draft['content']);
        $this->assertStringContainsString('{{title}}', $draft['content']);
        $this->assertStringContainsString('{{#if Knowledge}}', $draft['content']);
        $this->assertStringNotContainsString('{{content}}', $draft['content']);

        $this->assertSame($before, Prompt::query()->count());
        $this->postJson(route('admin.ai-prompts.drafts'), ['scenario' => 'quality_check'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scenario']);
    }

    public function test_create_and_content_edit_pages_expose_the_generator(): void
    {
        $content = Prompt::query()->where('type', 'content')->firstOrFail();
        $quality = Prompt::query()->create([
            'name' => '自定义质检',
            'type' => 'quality_check',
            'content' => '检查 {{article_content}}',
            'variables' => '[]',
        ]);
        $system = Prompt::query()->whereNotNull('system_key')->firstOrFail();

        $this->get(route('admin.ai-prompts.create'))
            ->assertOk()
            ->assertSee('data-prompt-draft', false)
            ->assertSee('data-draft-generate', false)
            ->assertSee('data-draft-rewrite', false)
            ->assertSee(__('admin.ai_prompts.draft.generate'), false)
            ->assertSee('action="'.route('admin.ai-prompts.store').'"', false);

        $this->get(route('admin.ai-prompts.edit', $content))
            ->assertOk()
            ->assertSee('data-prompt-draft', false);

        $this->get(route('admin.ai-prompts.edit', $quality))
            ->assertOk()
            ->assertDontSee('data-prompt-draft', false);

        $this->get(route('admin.ai-prompts.edit', $system))
            ->assertOk()
            ->assertDontSee('data-prompt-draft', false);
    }

    public function test_ai_rewrite_returns_a_valid_draft_and_records_usage(): void
    {
        $baseline = '请围绕 {{title}} 写作。{{#if keyword}}关键词 {{keyword}}{{/if}}{{#if Knowledge}}资料 {{Knowledge}}{{/if}}';
        ContentPromptDraftAgent::fake([[
            'name' => '定制物流信任型提示词',
            'content' => $baseline,
        ]])->preventStrayPrompts();
        $before = Prompt::query()->count();

        $this->postJson(route('admin.ai-prompts.drafts.ai'), [
            'scenario' => 'decision_faq',
            'brand' => '示例物流',
            'model_id' => $this->model->id,
        ])->assertOk()
            ->assertJsonPath('name', '定制物流信任型提示词')
            ->assertJsonPath('content', $baseline);

        ContentPromptDraftAgent::assertPrompted(fn (mixed $prompt): bool => str_contains((string) $prompt->prompt, '示例物流'));
        $this->assertSame($before, Prompt::query()->count());
        $this->assertSame(1, $this->model->fresh()->total_used);
        $this->assertSame(1, AiModelUsageEvent::query()
            ->where('operation', 'content_prompt_draft.rewrite')
            ->where('status', 'succeeded')
            ->count());
    }

    public function test_ai_rewrite_rejects_a_contract_violation_and_an_unavailable_model(): void
    {
        ContentPromptDraftAgent::fake([[
            'name' => '坏模板',
            'content' => '请写一篇物流文章，不要使用变量。',
        ]])->preventStrayPrompts();

        $this->postJson(route('admin.ai-prompts.drafts.ai'), [
            'scenario' => 'service_trust',
            'model_id' => $this->model->id,
        ])->assertUnprocessable()
            ->assertJsonPath('message', __('admin.ai_prompts.draft.invalid_contract'));

        $this->assertSame(0, $this->model->fresh()->total_used);
        $this->assertSame(1, AiModelUsageEvent::query()
            ->where('operation', 'content_prompt_draft.rewrite')
            ->where('status', 'discarded')
            ->count());
    }

    public function test_ai_rewrite_rejects_an_unavailable_model_before_calling_the_agent(): void
    {
        ContentPromptDraftAgent::fake([[
            'name' => '不应返回',
            'content' => '不应返回',
        ]])->preventStrayPrompts();

        $this->postJson(route('admin.ai-prompts.drafts.ai'), [
            'scenario' => 'service_trust',
            'model_id' => 999999,
        ])->assertUnprocessable()
            ->assertJsonPath('message', __('admin.ai_prompts.draft.model_unavailable'));

        ContentPromptDraftAgent::assertNeverPrompted();
    }
}
