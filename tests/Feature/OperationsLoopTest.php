<?php

namespace Tests\Feature;

use App\Jobs\SyncOperationsBriefJob;
use App\Models\Admin;
use App\Models\Article;
use App\Models\LeadForm;
use App\Models\OperationsBriefRun;
use App\Models\OperationsDifyConnection;
use App\Services\Operations\OperationsContentPilot;
use App\Services\Operations\OperationsKnowledgeCatalog;
use App\Services\Operations\OperationsKnowledgeGuard;
use App\Services\Operations\OperationsKnowledgeProvisioner;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OperationsLoopTest extends TestCase
{
    use RefreshDatabase;

    public function test_knowledge_partitions_reject_sensitive_fields_and_repair_failed_training_files(): void
    {
        $guard = app(OperationsKnowledgeGuard::class);
        $this->assertSame(['phone'], $guard->violations('联系 13812345678'));
        $this->assertSame(['national_id'], $guard->violations('证件 110101199001011234'));
        $this->assertSame(['order_number'], $guard->violations('订单号：GYB123456'));
        $this->assertSame(['denied_tool:upsert-cargo-owner-lead'], $guard->violations('调用 upsert-cargo-owner-lead'));

        $this->artisan('geoflow:repair-training-knowledge')->assertSuccessful();
        $this->artisan('geoflow:repair-training-knowledge')->assertSuccessful();

        $bases = app(OperationsKnowledgeProvisioner::class)->ensure();
        $public = (string) $bases[OperationsKnowledgeCatalog::PUBLIC_SCIENCE]->content;
        $internal = (string) $bases[OperationsKnowledgeCatalog::INTERNAL_SCRIPTS]->content;

        $this->assertSame(8, count($bases));
        $this->assertSame(1, substr_count($public, '桂运宝公司介绍.pptx'));
        $this->assertStringContainsString('超过 50MB', $public);
        $this->assertStringNotContainsString('桂运宝商业计划书.pdf', $public);
        $this->assertStringContainsString('桂运宝商业计划书.pdf', $internal);
        $this->assertStringContainsString('桂运宝居家业务培训讲解版.pptx', $internal);
    }

    public function test_weekday_brief_writes_redacted_summary_and_rejects_sensitive_answers(): void
    {
        config([
            'geoflow.operations_dify.base_url' => 'https://dify.test',
            'geoflow.operations_dify.api_key' => 'dataset-secret',
        ]);
        Http::fake(function ($request) {
            $query = (string) ($request->data()['query'] ?? '');
            $answer = str_contains($query, 'brief-phone')
                ? '请联系 13812345678'
                : "热门线路：南宁至广州\n货类：煤炭、矿石\n运价区间：以业务系统聚合为准\n线索行业：建材";

            return Http::response(['answer' => $answer]);
        });

        $this->artisan('geoflow:sync-operations-brief', [
            '--sync' => true,
            '--request-id' => 'brief-clean',
        ])->assertSuccessful();
        $this->artisan('geoflow:sync-operations-brief', [
            '--sync' => true,
            '--request-id' => 'brief-clean',
        ])->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->url() === 'https://dify.test/v1/chat-messages'
                && ! array_key_exists('tools', $payload)
                && str_contains((string) $payload['query'], '不要写入货主线索')
                && str_contains((string) $payload['query'], '不要发送短信或应用推送')
                && ! str_contains((string) $payload['query'], 'upsert-cargo-owner-lead');
        });

        $connection = OperationsDifyConnection::query()->firstOrFail();
        $this->assertStringStartsWith('enc:v1:', $connection->api_key);
        $this->assertStringNotContainsString('dataset-secret', $connection->api_key);
        $brief = (string) app(OperationsKnowledgeProvisioner::class)
            ->find(OperationsKnowledgeCatalog::OPERATIONS_BRIEF)
            ->content;
        $this->assertStringContainsString('brief-clean', $brief);
        $this->assertStringContainsString('来源：Dify 只读摘要', $brief);

        $this->artisan('geoflow:sync-operations-brief', [
            '--sync' => true,
            '--request-id' => 'brief-phone',
        ])->assertFailed();

        $failed = OperationsBriefRun::query()->where('request_id', 'brief-phone')->firstOrFail();
        $this->assertSame(OperationsBriefRun::STATUS_FAILED, $failed->status);
        $this->assertStringNotContainsString('13812345678', (string) app(OperationsKnowledgeProvisioner::class)
            ->find(OperationsKnowledgeCatalog::OPERATIONS_BRIEF)
            ->content);
    }

    public function test_brief_command_queues_without_calling_dify(): void
    {
        Queue::fake();

        $this->artisan('geoflow:sync-operations-brief', [
            '--request-id' => 'brief-queued',
        ])->assertSuccessful();

        Queue::assertPushed(SyncOperationsBriefJob::class, function (SyncOperationsBriefJob $job): bool {
            return $job->requestId === 'brief-queued';
        });
    }

    public function test_vehicle_cargo_pilot_publishes_wechat_html_and_receives_a_lead(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = Admin::query()->create([
            'username' => 'operations_pilot',
            'password' => 'secret-123',
            'email' => 'operations-pilot@example.com',
            'display_name' => '运营试点',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $article = app(OperationsContentPilot::class)->publish((int) $admin->id);

        $this->assertSame('published', $article->status);
        $this->assertSame('approved', $article->review_status);
        $this->assertStringContainsString('货运车型', (string) $article->content);
        $this->assertStringContainsString('八大类货物', (string) $article->content);
        $this->assertDatabaseHas('article_risk_scans', [
            'article_id' => $article->id,
            'status' => 'clean',
            'trigger' => 'operations_pilot',
        ]);
        $this->assertSame(2, $article->task?->knowledgeBases()->count());

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.articles.editor.wechat-html'), [
                'content' => $article->content,
            ])
            ->assertOk()
            ->assertJsonPath('message', __('admin.article_editor.wechat.success'));

        $form = LeadForm::query()->create([
            'name' => '运力咨询',
            'slug' => 'operations-cargo-inquiry',
            'status' => LeadForm::STATUS_ACTIVE,
            'description' => '提交运力需求',
            'submit_button_label' => '提交',
            'success_message' => '已收到咨询',
            'fields' => [
                ['name' => 'name', 'label' => '姓名', 'type' => 'text', 'required' => true, 'options' => []],
                ['name' => 'phone', 'label' => '手机号', 'type' => 'phone', 'required' => true, 'options' => []],
                ['name' => 'message', 'label' => '需求', 'type' => 'textarea', 'required' => true, 'options' => []],
            ],
        ]);
        $this->post(route('site.lead-forms.submit', ['slug' => $form->slug]), [
            'name' => '南宁货主',
            'phone' => '13800000000',
            'message' => '咨询煤炭运输',
            'source_url' => route('site.article', ['slug' => $article->slug]),
        ])->assertRedirect();

        $this->assertDatabaseHas('lead_submissions', [
            'lead_form_id' => $form->id,
        ]);
        $this->artisan('geoflow:operations-review')->assertSuccessful();
    }

    public function test_weekly_review_fails_when_operations_summary_contains_a_phone(): void
    {
        app(OperationsKnowledgeProvisioner::class)->ensure();
        $brief = app(OperationsKnowledgeProvisioner::class)->find(OperationsKnowledgeCatalog::OPERATIONS_BRIEF);
        $brief->forceFill([
            'content' => $brief->content."\n联系 13900001111",
        ])->save();

        $this->artisan('geoflow:operations-review')->assertFailed();
        $this->assertSame(0, Article::query()->where('status', 'published')->count());
    }

    public function test_scheduler_registers_operations_brief_and_review(): void
    {
        $schedule = file_get_contents(base_path('routes/console.php'));

        $this->assertIsString($schedule);
        $this->assertStringContainsString("Schedule::command('geoflow:sync-operations-brief')", $schedule);
        $this->assertStringContainsString("Schedule::command('geoflow:operations-review')", $schedule);
    }
}
