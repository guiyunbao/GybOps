<?php

namespace Tests\Feature;

use App\Jobs\SyncOfficialSourcesJob;
use App\Models\OperationsSourceDocument;
use App\Services\Operations\OperationsKnowledgeCatalog;
use App\Services\Operations\OperationsKnowledgeProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OfficialSourceSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekday_sync_stores_same_host_excerpt_and_skips_it_on_the_next_run(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if ($url === 'https://www.gov.cn/zhengce/index.htm') {
                return Http::response(<<<'HTML'
                <html><body>
                <a href="/zhengce/202610/content_7082820.htm">关于公路运输的通知</a>
                <a href="https://evil.test/zhengce/202610/content_7082820.htm">外部站点</a>
                </body></html>
                HTML);
            }
            if ($url === 'https://www.gov.cn/zhengce/202610/content_7082820.htm') {
                return Http::response('<html><title>关于公路运输的通知_中国政府网</title><body><p>公路货运车辆应按公布的通行政策安排运输。</p></body></html>');
            }
            if ($url === 'http://www.gxzf.gov.cn/gxyw/') {
                return Http::response('<html><a href="./t28236848.shtml" title="前三季度北海港外贸集装箱进口增速创新高">重复标题</a></html>');
            }
            if ($url === 'http://www.gxzf.gov.cn/gxyw/t28236848.shtml') {
                return Http::response('<html><title>广西壮族自治区人民政府门户网站</title><body><p>北海港外贸集装箱进口保持增长。</p></body></html>');
            }

            return Http::response('<html></html>');
        });

        $this->artisan('geoflow:sync-official-sources', [
            '--cadence' => 'weekday',
            '--sync' => true,
        ])->assertSuccessful();
        $this->artisan('geoflow:sync-official-sources', [
            '--cadence' => 'weekday',
            '--sync' => true,
        ])->assertSuccessful();

        $policy = app(OperationsKnowledgeProvisioner::class)->find(OperationsKnowledgeCatalog::POLICY);
        $this->assertStringContainsString('关于公路运输的通知', (string) $policy->content);
        $this->assertStringContainsString('https://www.gov.cn/zhengce/202610/content_7082820.htm', (string) $policy->content);
        $this->assertStringContainsString('official-source:', (string) $policy->content);
        $this->assertStringNotContainsString('evil.test', (string) $policy->content);
        $corridor = (string) app(OperationsKnowledgeProvisioner::class)->find(OperationsKnowledgeCatalog::CORRIDOR)->content;
        $this->assertStringContainsString('前三季度北海港外贸集装箱进口增速创新高', $corridor);
        $this->assertStringNotContainsString('## 广西壮族自治区人民政府门户网站', $corridor);
        $this->assertSame(2, OperationsSourceDocument::query()->where('status', 'stored')->count());
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'evil.test'));
        Http::assertSent(fn ($request): bool => $request->url() === 'https://www.gov.cn/zhengce/202610/content_7082820.htm');
    }

    public function test_sensitive_article_is_rejected_and_weekly_index_keeps_freight_titles(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if ($url === 'http://jtt.gxzf.gov.cn/xwdt/') {
                return Http::response('<html><a href="/xwdt/jtxw/t28230193.shtml">含联系方式的稿件</a></html>');
            }
            if ($url === 'http://jtt.gxzf.gov.cn/xwdt/jtxw/t28230193.shtml') {
                return Http::response('<html><title>含联系方式的稿件</title><body>请联系 13812345678</body></html>');
            }
            if ($url === 'https://www.chinawuliu.com.cn/') {
                return Http::response(<<<'HTML'
                <html><body>
                <a href="/lhhzq/202610/15/669133.shtml" title="2026年9月公路运价指数显示：季节性因素减弱">运价指数</a>
                <a href="/lhhzq/202610/15/669999.shtml" title="行业会议召开">行业会议</a>
                </body></html>
                HTML);
            }
            if ($url === 'https://www.chinawuliu.com.cn/lhhzq/202610/15/669133.shtml') {
                return Http::response('<html><title>2026年9月公路运价指数</title><body><p>公路运价指数环比回升，摘录仅保留公开口径。</p></body></html>');
            }

            return Http::response('<html></html>');
        });

        $this->artisan('geoflow:sync-official-sources', [
            '--cadence' => 'weekday',
            '--sync' => true,
        ])->assertSuccessful();
        $this->artisan('geoflow:sync-official-sources', [
            '--cadence' => 'weekly',
            '--sync' => true,
        ])->assertSuccessful();

        $policy = (string) app(OperationsKnowledgeProvisioner::class)->find(OperationsKnowledgeCatalog::POLICY)->content;
        $market = (string) app(OperationsKnowledgeProvisioner::class)->find(OperationsKnowledgeCatalog::MARKET)->content;
        $this->assertStringNotContainsString('13812345678', $policy);
        $this->assertStringContainsString('公路运价指数环比回升', $market);
        $this->assertStringNotContainsString('669999', $market);
        $this->assertDatabaseHas('operations_source_documents', [
            'url' => 'http://jtt.gxzf.gov.cn/xwdt/jtxw/t28230193.shtml',
            'status' => 'rejected',
        ]);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '669999'));
    }

    public function test_command_queues_weekday_sources_and_rejects_an_unknown_cadence(): void
    {
        Queue::fake();

        $this->artisan('geoflow:sync-official-sources', ['--cadence' => 'weekday'])->assertSuccessful();
        $this->artisan('geoflow:sync-official-sources')->assertFailed();

        Queue::assertPushed(SyncOfficialSourcesJob::class, fn (SyncOfficialSourcesJob $job): bool => $job->cadence === 'weekday');
    }
}
