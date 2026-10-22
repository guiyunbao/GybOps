<?php

namespace App\Support\GeoFlow;

use App\Models\ArticleAiQualityCheck;
use App\Services\GeoFlow\ArticleAiQualityTechnicalSkip;

/**
 * 用一句话说明当前 AI 质检对发布意味着什么。
 */
final class ArticleAiQualityDecisionPresenter
{
    public function __construct(private readonly ArticleAiQualityTechnicalSkip $technicalSkip) {}

    public function sentence(?ArticleAiQualityCheck $check, bool $enabled): ?string
    {
        if (! $enabled || ! $check instanceof ArticleAiQualityCheck) {
            return null;
        }

        $status = (string) $check->status;
        $decision = (string) $check->decision;
        if (in_array($status, ['queued', 'running'], true) || ! in_array($status, ['completed', 'failed', 'stale'], true)) {
            return __('admin.articles.ai_quality.decision_pending');
        }
        if ($status === 'stale') {
            return __('admin.articles.ai_quality.decision_stale');
        }
        if ($status === 'failed' || $decision === 'error') {
            return $this->technicalSkip->authorizes($check)
                ? __('admin.articles.ai_quality.decision_technical_skip')
                : __('admin.articles.ai_quality.decision_failed');
        }
        if ((string) $check->inspection_scope === 'fallback_sampled') {
            return __('admin.articles.ai_quality.decision_sampled');
        }

        return match ($decision) {
            'passed' => __('admin.articles.ai_quality.decision_passed'),
            'needs_review' => $check->is_overridden
                ? __('admin.articles.ai_quality.decision_overridden')
                : __('admin.articles.ai_quality.decision_needs_review'),
            default => __('admin.articles.ai_quality.decision_blocked'),
        };
    }
}
