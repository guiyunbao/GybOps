<?php

namespace Tests\Unit;

use App\Models\ArticleAiQualityCheck;
use App\Services\GeoFlow\ArticleAiQualityScorer;
use App\Services\GeoFlow\ArticleLogisticsClaimScanner;
use App\Support\GeoFlow\ArticleAiQualityDecisionPresenter;
use App\Support\GeoFlow\ArticleAiQualityIssueGuide;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArticleAiQualityOperatorCopyTest extends TestCase
{
    public function test_every_issue_code_has_an_operator_action(): void
    {
        $guide = app(ArticleAiQualityIssueGuide::class);

        foreach (ArticleAiQualityIssueGuide::CODES as $code) {
            $action = $guide->forCode($code);

            $this->assertNotSame($code, $action['label']);
            $this->assertNotSame('', $action['owner']);
            $this->assertNotSame('', $action['action']);
        }
    }

    #[DataProvider('logisticsClaims')]
    public function test_logistics_lexicon_marks_absolute_claims_and_ignores_negation(string $content, bool $matches): void
    {
        $issues = app(ArticleLogisticsClaimScanner::class)->issues([
            'title' => '',
            'excerpt' => '',
            'content' => $content,
        ]);

        $this->assertCount($matches ? 1 : 0, $issues);
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function logisticsClaims(): array
    {
        return [
            'lowest price' => ['全网最低运价。', true],
            'zero delay' => ['我们承诺零延误送达。', true],
            'negated' => ['我们不承诺零延误。', false],
            'ordinary timing' => ['时效取决于线路。', false],
        ];
    }

    public function test_logistics_claim_blocks_publication_and_does_not_duplicate_the_same_quote(): void
    {
        $scanner = app(ArticleLogisticsClaimScanner::class);
        $article = ['title' => '', 'excerpt' => '', 'content' => '全网最低，而且再次写全网最低。'];
        $issues = $scanner->issues($article);
        $issues = array_merge($issues, $scanner->issues($article, $issues));
        $score = app(ArticleAiQualityScorer::class)->score([
            'summary' => '命中绝对化运价。',
            'promotion_context' => 'promotional',
            'knowledge_coverage' => 'sufficient',
            'issues' => $issues,
            'uncertainties' => [],
        ]);

        $this->assertCount(1, $issues);
        $this->assertSame('blocked', $score['decision']);
        $this->assertTrue((bool) $issues[0]['hard_blocker']);
    }

    public function test_decision_sentence_separates_a_pass_from_a_technical_failure(): void
    {
        $presenter = app(ArticleAiQualityDecisionPresenter::class);
        $passed = new ArticleAiQualityCheck([
            'status' => 'completed',
            'decision' => 'passed',
            'inspection_scope' => 'full',
        ]);
        $failed = new ArticleAiQualityCheck([
            'status' => 'failed',
            'decision' => 'error',
            'score' => null,
            'inspection_scope' => 'full',
        ]);

        $this->assertSame(__('admin.articles.ai_quality.decision_passed'), $presenter->sentence($passed, true));
        $this->assertSame(__('admin.articles.ai_quality.decision_failed'), $presenter->sentence($failed, true));
        $this->assertNull($presenter->sentence($passed, false));
    }
}
