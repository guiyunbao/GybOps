<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\Admin\AdminGettingStartedCatalog;
use App\Support\AdminUiRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class AdminGettingStartedPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_read_every_getting_started_section_without_signing_in(): void
    {
        $configuredSecret = 'initial-super-admin-secret';
        config([
            'geoflow.initial_admin_password' => $configuredSecret,
        ]);

        $catalog = app(AdminGettingStartedCatalog::class);
        $response = $this->get(route('admin.getting-started'));

        $response->assertOk()
            ->assertSee('新手帮助')
            ->assertSee('返回登录')
            ->assertSee(route('admin.login'), false)
            ->assertDontSee('返回后台')
            ->assertSee('作用')
            ->assertSee('从哪进')
            ->assertSee('使用流程')
            ->assertSee('和谁有关')
            ->assertSee('<svg', false)
            ->assertSee('data-getting-started-flow="pipeline"', false)
            ->assertDontSee($configuredSecret)
            ->assertDontSee('password')
            ->assertDontSee('sk-live')
            ->assertDontSee('这本手册使用中文');

        foreach ($catalog->sectionIds() as $sectionId) {
            $response->assertSee('id="'.$sectionId.'"', false);
        }

        $this->assertGreaterThanOrEqual(40, count($catalog->sectionIds()));
        $this->assertSame('special', app(AdminUiRegistry::class)->routeClassification('admin.getting-started'));
        $this->assertFalse(app(AdminUiRegistry::class)->shouldRememberRoute('admin.getting-started'));
    }

    public function test_signed_in_admin_can_open_getting_started_without_leaving_for_the_dashboard(): void
    {
        $admin = Admin::query()->create([
            'username' => 'guide-admin',
            'password' => 'guide-admin-secret',
            'email' => 'guide-admin@example.com',
            'display_name' => 'Guide Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.getting-started'))
            ->assertOk()
            ->assertSee('第一次闭环')
            ->assertSee('返回后台')
            ->assertSee(route('admin.dashboard'), false)
            ->assertDontSee('guide-admin-secret');
    }

    public function test_non_chinese_locale_keeps_the_chinese_handbook_and_shows_a_language_note(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get(route('admin.getting-started'))
            ->assertOk()
            ->assertSee('这本手册使用中文')
            ->assertSee('Back to sign in')
            ->assertSee('AI 工作台');
    }

    public function test_login_page_links_to_getting_started(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee(route('admin.getting-started'), false)
            ->assertSee('新手帮助');
    }

    public function test_signed_in_menus_link_to_getting_started(): void
    {
        $admin = Admin::query()->create([
            'username' => 'menu-admin',
            'password' => 'menu-admin-secret',
            'email' => 'menu-admin@example.com',
            'display_name' => 'Menu Admin',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin');

        $topbar = Blade::render('<x-admin.v3.topbar :admin="$admin" />', ['admin' => $admin]);
        $header = view('admin.partials.header', ['activeMenu' => ''])->render();

        $helpPath = (string) parse_url(route('admin.getting-started'), PHP_URL_PATH);

        $this->assertStringContainsString($helpPath, $topbar);
        $this->assertStringContainsString(__('admin.getting_started.link'), $topbar);
        $this->assertStringContainsString($helpPath, $header);
        $this->assertStringContainsString(__('admin.getting_started.link'), $header);
    }
}
