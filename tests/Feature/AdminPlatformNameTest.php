<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\SiteSetting;
use App\Support\AdminWeb;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminPlatformNameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('admin_activity_logs')) {
            return;
        }

        Schema::create('admin_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->nullable();
            $table->string('admin_username', 50);
            $table->string('admin_role', 20)->default('admin');
            $table->string('action', 120);
            $table->string('request_method', 10)->default('POST');
            $table->string('page')->default('');
            $table->string('target_type', 50)->default('');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('ip_address', 64)->default('');
            $table->text('details')->default('');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function test_admin_can_rename_the_platform_and_the_change_is_audited(): void
    {
        $admin = $this->admin('admin');
        SiteSetting::query()->create(['setting_key' => 'site_name', 'setting_value' => '前台站点']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.site-settings.index'))
            ->assertOk()
            ->assertSee(__('admin.site_settings.platform_name.title'))
            ->assertSee(AdminWeb::DEFAULT_PLATFORM_NAME);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.site-settings.platform-name.update'), [
                'platform_name' => ' 桂运宝"运营平台 ',
            ])
            ->assertRedirect(route('admin.site-settings.index'))
            ->assertSessionHas('message', __('admin.site_settings.platform_name.saved'));

        $this->assertDatabaseHas('site_settings', [
            'setting_key' => AdminWeb::PLATFORM_NAME_SETTING_KEY,
            'setting_value' => '桂运宝"运营平台',
        ]);
        $this->assertDatabaseHas('site_settings', [
            'setting_key' => 'site_name',
            'setting_value' => '前台站点',
        ]);
        $this->assertSame('桂运宝"运营平台', AdminWeb::siteName());

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'));
        $response->assertOk()
            ->assertSee('桂运宝&quot;运营平台', false)
            ->assertDontSee('title="桂运宝"运营平台"', false);

        $log = AdminActivityLog::query()
            ->where('action', 'admin.site-settings.platform-name.update:platform_name')
            ->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertSame('admin', $log->admin_role);
        $details = json_decode((string) $log->details, true);
        $this->assertSame(AdminWeb::DEFAULT_PLATFORM_NAME, $details['previous_name']);
        $this->assertSame('桂运宝"运营平台', $details['platform_name']);
        $this->assertTrue($details['changed']);
    }

    public function test_super_admin_can_rename_the_platform(): void
    {
        $admin = $this->admin('super_admin');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.site-settings.platform-name.update'), [
                'platform_name' => '超级管理员名称',
            ])
            ->assertRedirect(route('admin.site-settings.index'));

        $this->assertSame('超级管理员名称', AdminWeb::siteName());
    }

    public function test_non_admin_role_cannot_rename_the_platform(): void
    {
        $editor = $this->admin('editor');

        $this->actingAs($editor, 'admin')
            ->get(route('admin.site-settings.index'))
            ->assertOk()
            ->assertSee(__('admin.site_settings.platform_name.forbidden'))
            ->assertDontSee('name="platform_name"', false);

        $this->actingAs($editor, 'admin')
            ->post(route('admin.site-settings.platform-name.update'), [
                'platform_name' => '越权名称',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('site_settings', [
            'setting_key' => AdminWeb::PLATFORM_NAME_SETTING_KEY,
        ]);
    }

    public function test_guest_is_redirected_from_platform_name_update(): void
    {
        $this->post(route('admin.site-settings.platform-name.update'), [
            'platform_name' => '未登录',
        ])->assertRedirect(route('admin.login'));
    }

    public function test_blank_or_markup_platform_name_is_rejected(): void
    {
        $admin = $this->admin('admin');

        $this->actingAs($admin, 'admin')
            ->from(route('admin.site-settings.index'))
            ->post(route('admin.site-settings.platform-name.update'), [
                'platform_name' => '   ',
            ])
            ->assertRedirect(route('admin.site-settings.index'))
            ->assertSessionHasErrors([
                'platform_name' => __('admin.site_settings.platform_name.required'),
            ]);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.site-settings.index'))
            ->post(route('admin.site-settings.platform-name.update'), [
                'platform_name' => '<script>alert(1)</script>',
            ])
            ->assertSessionHasErrors([
                'platform_name' => __('admin.site_settings.platform_name.invalid'),
            ]);

        $this->assertDatabaseMissing('site_settings', [
            'setting_key' => AdminWeb::PLATFORM_NAME_SETTING_KEY,
        ]);
    }

    private function admin(string $role): Admin
    {
        return Admin::query()->create([
            'username' => 'platform_'.$role,
            'password' => 'secret-123',
            'email' => $role.'@example.com',
            'display_name' => 'Platform '.$role,
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
