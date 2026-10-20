<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 主站关于页：未配置时展示 GEOFlow 介绍；配置 about_content 后展示自定义内容。
 */
class SiteAboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_about_page_shows_geoflow_content_by_default(): void
    {
        $this->get(route('site.about'))
            ->assertOk()
            ->assertSee('GEOFlow')
            ->assertSee('生成式引擎优化')
            ->assertSee('GitHub 仓库');
    }

    public function test_primary_about_page_uses_custom_content_when_configured(): void
    {
        SiteSetting::query()->updateOrCreate(
            ['setting_key' => 'site_name'],
            ['setting_value' => '桂运宝'],
        );
        SiteSetting::query()->updateOrCreate(
            ['setting_key' => 'about_title'],
            ['setting_value' => '关于桂运宝'],
        );
        SiteSetting::query()->updateOrCreate(
            ['setting_key' => 'about_content'],
            ['setting_value' => '广西桂运宝科技有限公司总部在柳州。'],
        );
        SiteSetting::query()->updateOrCreate(
            ['setting_key' => 'contact_email'],
            ['setting_value' => 'contact@example.com'],
        );

        $this->get(route('site.about'))
            ->assertOk()
            ->assertSee('关于桂运宝')
            ->assertSee('广西桂运宝科技有限公司总部在柳州。')
            ->assertSee('mailto:contact@example.com', false)
            ->assertDontSee('GitHub 仓库')
            ->assertDontSee('开源项目');
    }
}
