<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 后台第一次闭环的分步引导：首次展示、完成记忆和重看入口。
 */
class AdminPageGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_visit_includes_guide_data_and_stable_targets(): void
    {
        config()->set('geoflow.admin_ui_v3_enabled', true);
        $admin = $this->admin();

        $pages = [
            'admin.ai-models.index' => 'ai-models',
            'admin.title-libraries.index' => 'title-libraries',
            'admin.materials.index' => 'knowledge-bases',
            'admin.tasks.index' => 'tasks',
            'admin.articles.index' => 'articles',
        ];

        foreach ($pages as $route => $guideId) {
            $response = $this->actingAs($admin, 'admin')->get(route($route));
            $response->assertOk();
            $payload = $this->guidePayload($response->getContent());

            $this->assertSame($guideId, $payload['id']);
            $this->assertTrue($payload['autoOpen']);
            $this->assertNotEmpty($payload['steps']);
            $response->assertSee(__('admin.page_guide.replay'), false);
            $response->assertSee('data-gf-guide-replay', false);

            foreach ($payload['steps'] as $step) {
                $this->assertMatchesRegularExpression('/^\[data-gf-guide="[a-z0-9-]+"\]$/', $step['target']);
                $attribute = trim($step['target'], '[]');
                $response->assertSee($attribute, false);
            }
        }
    }

    public function test_completing_a_guide_is_remembered_and_does_not_auto_open_again(): void
    {
        config()->set('geoflow.admin_ui_v3_enabled', true);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.guides.complete'), ['id' => 'ai-models'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $admin->refresh();
        $this->assertArrayHasKey('ai-models', $admin->guide_progress);
        $this->assertNotSame('', (string) $admin->guide_progress['ai-models']);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.ai-models.index'));
        $payload = $this->guidePayload($response->getContent());

        $this->assertFalse($payload['autoOpen']);
        $response->assertSee('data-gf-guide-replay', false);
        $response->assertSee('data-gf-guide="models-create"', false);
    }

    public function test_guest_cannot_save_guide_progress(): void
    {
        $this->post(route('admin.guides.complete'), ['id' => 'ai-models'])
            ->assertRedirect(route('admin.login'));
    }

    public function test_unknown_guide_is_rejected(): void
    {
        config()->set('geoflow.admin_ui_v3_enabled', true);

        $this->actingAs($this->admin(), 'admin')
            ->postJson(route('admin.guides.complete'), ['id' => 'distribution'])
            ->assertStatus(422);
    }

    public function test_legacy_layout_does_not_emit_the_page_guide(): void
    {
        config()->set('geoflow.admin_ui_v3_enabled', false);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.articles.index'))
            ->assertOk()
            ->assertDontSee('id="gf-page-guide"', false)
            ->assertDontSee('data-gf-guide-replay', false);
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'username' => 'page_guide_'.uniqid(),
            'password' => 'secret-123',
            'email' => uniqid('page-guide-').'@example.com',
            'display_name' => 'Page Guide Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function guidePayload(string $html): array
    {
        $matched = preg_match('/<script type="application\/json" id="gf-page-guide">(.*?)<\/script>/s', $html, $matches);
        $this->assertSame(1, $matched);

        $payload = json_decode($matches[1], true);
        $this->assertIsArray($payload);

        return $payload;
    }
}
