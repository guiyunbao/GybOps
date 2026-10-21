<?php

namespace Tests\Unit;

use App\Services\GeoFlow\LogisticsContentPromptComposer;
use App\Support\GeoFlow\ContentPromptContract;
use App\Support\GeoFlow\LogisticsContentPromptCatalog;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LogisticsContentPromptComposerTest extends TestCase
{
    #[DataProvider('scenarios')]
    public function test_each_logistics_scenario_keeps_the_body_prompt_contract(string $scenario): void
    {
        $catalog = new LogisticsContentPromptCatalog;
        $contract = new ContentPromptContract;
        $base = $catalog->base($scenario);
        $contract->assert($base['name'], $base['content']);

        $this->assertSame(1, substr_count($base['content'], '【正文引用标注约束】'));
        $this->assertStringNotContainsString('[[LOGISTICS_SUBJECT]]', $base['content']);
        $this->assertStringNotContainsString('{{content}}', $base['content']);
        $this->assertStringNotContainsString('示例物流', $base['content']);

        $empty = app(LogisticsContentPromptComposer::class)->compose($scenario, []);
        $contract->assert($empty['name'], $empty['content']);
        $this->assertStringContainsString('【推广主体】', $empty['content']);
        $this->assertStringContainsString('未单独填写公司或品牌', $empty['content']);
        $this->assertStringContainsString('只能来自 {{Knowledge}}', $empty['content']);
    }

    public function test_a_brief_is_inserted_as_direction_without_template_variables(): void
    {
        $draft = app(LogisticsContentPromptComposer::class)->compose('solution_match', [
            'brand' => '示例物流',
            'services' => '仓配 {{content}}',
            'audience' => '货主',
            'notes' => '不要写运价',
        ]);

        $this->assertSame('物流方案选型·决策指南', $draft['name']);
        $this->assertStringContainsString('公司或品牌：示例物流', $draft['content']);
        $this->assertStringContainsString('核心业务：仓配', $draft['content']);
        $this->assertStringContainsString('目标读者：货主', $draft['content']);
        $this->assertStringContainsString('补充要求：不要写运价', $draft['content']);
        $this->assertStringNotContainsString('{{content}}', $draft['content']);
        $this->assertStringContainsString('仍只能来自 {{Knowledge}}', $draft['content']);
        (new ContentPromptContract)->assert($draft['name'], $draft['content']);
    }

    public function test_unknown_scenario_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(LogisticsContentPromptComposer::class)->compose('quality_check', []);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function scenarios(): array
    {
        return array_map(static fn (string $scenario): array => [$scenario], LogisticsContentPromptCatalog::KEYS);
    }
}
