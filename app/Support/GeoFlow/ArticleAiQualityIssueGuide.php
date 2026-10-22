<?php

namespace App\Support\GeoFlow;

/**
 * 把质检问题码翻成运营能执行的名称、处理人和默认动作。
 */
final class ArticleAiQualityIssueGuide
{
    /** @var list<string> */
    public const CODES = [
        'knowledge_contradiction',
        'data_mismatch',
        'unsupported_claim',
        'citation_missing',
        'citation_scope_mismatch',
        'ad_absolute_claim',
        'ad_false_or_misleading',
        'ad_industry_specific',
        'ad_identifiability',
        'content_integrity',
        'source_declared_unverified',
    ];

    /**
     * @return array{code: string, label: string, owner: string, action: string}
     */
    public function forCode(string $code): array
    {
        $known = in_array($code, self::CODES, true);

        return [
            'code' => $code,
            'label' => $known
                ? __('admin.articles.ai_quality.issue_guide.'.$code.'.label')
                : $code,
            'owner' => $known
                ? __('admin.articles.ai_quality.issue_guide.'.$code.'.owner')
                : __('admin.articles.ai_quality.issue_guide_unknown_owner'),
            'action' => $known
                ? __('admin.articles.ai_quality.issue_guide.'.$code.'.action')
                : __('admin.articles.ai_quality.issue_guide_unknown_action'),
        ];
    }
}
