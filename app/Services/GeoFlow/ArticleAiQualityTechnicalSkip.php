<?php

namespace App\Services\GeoFlow;

use App\Models\Admin;
use App\Models\ArticleAiQualityCheck;
use RuntimeException;

/**
 * 记录并判断一次没有内容分数的技术失败能否跳过。
 */
final class ArticleAiQualityTechnicalSkip
{
    public function __construct(private readonly AiQualityAuditService $audit) {}

    public function authorizes(?ArticleAiQualityCheck $check): bool
    {
        if (! $check instanceof ArticleAiQualityCheck || ! $this->isTechnicalFailure($check)) {
            return false;
        }

        $skip = data_get($check->execution_meta, 'technical_skip');
        if (! is_array($skip)) {
            return false;
        }
        $reason = trim((string) ($skip['reason'] ?? ''));
        $fingerprint = (string) ($skip['fingerprint'] ?? '');

        return mb_strlen($reason) >= 4
            && $fingerprint !== ''
            && hash_equals((string) $check->input_fingerprint, $fingerprint);
    }

    public function canRequest(?ArticleAiQualityCheck $check): bool
    {
        return $check instanceof ArticleAiQualityCheck
            && $this->isTechnicalFailure($check)
            && ! $this->authorizes($check);
    }

    public function record(ArticleAiQualityCheck $check, Admin $admin, string $reason): ArticleAiQualityCheck
    {
        $reason = trim($reason);
        if (! $this->canRequest($check) || mb_strlen($reason) < 4) {
            throw new RuntimeException('ai_quality_technical_skip_unavailable');
        }

        $fingerprint = (string) $check->input_fingerprint;
        $meta = is_array($check->execution_meta) ? $check->execution_meta : [];
        $meta['technical_skip'] = [
            'reason' => $reason,
            'admin_id' => (int) $admin->id,
            'admin_name' => (string) $admin->name,
            'fingerprint' => $fingerprint,
            'skipped_at' => now()->toIso8601String(),
        ];
        $check->forceFill(['execution_meta' => $meta])->save();
        $this->audit->record('article_quality_technical_skip', [
            'task_id' => $check->task_id ? (int) $check->task_id : null,
            'article_id' => (int) $check->article_id,
            'article_ai_quality_check_id' => (int) $check->id,
            'admin_id' => (int) $admin->id,
            'basis_hash' => $fingerprint,
            'reason_code' => 'technical_failure_skip',
            'metadata' => ['check_status' => (string) $check->status],
        ]);

        return $check->fresh() ?? $check;
    }

    private function isTechnicalFailure(ArticleAiQualityCheck $check): bool
    {
        if ($check->score !== null || in_array((string) $check->decision, ['blocked', 'needs_review', 'passed'], true)) {
            return false;
        }

        return (string) $check->status === 'failed' || (string) $check->decision === 'error';
    }
}
