<?php

namespace App\Services\Operations;

use App\Exceptions\OperationsKnowledgeRejectedException;

/**
 * 拦截电话、证件号、订单号和禁止进入内容流水线的工具名。
 */
final class OperationsKnowledgeGuard
{
    /** @var list<string> */
    public const DENIED_TOOLS = [
        'upsert-cargo-owner-lead',
        'send-mobile-custom-push',
        'send-sms-template-push',
    ];

    /**
     * @return list<string>
     */
    public function violations(string $content): array
    {
        $found = [];
        if (preg_match('/(?<!\d)1[3-9]\d{9}(?!\d)/', $content) === 1) {
            $found[] = 'phone';
        }
        if (preg_match('/(?<!\d)\d{17}[\dXx](?!\d)/', $content) === 1) {
            $found[] = 'national_id';
        }
        if (preg_match('/订单号\s*[:：]?\s*[A-Za-z0-9-]{6,}/u', $content) === 1) {
            $found[] = 'order_number';
        }
        if (preg_match('/运单号\s*[:：]?\s*[A-Za-z0-9-]{6,}/u', $content) === 1) {
            $found[] = 'waybill_number';
        }
        foreach (self::DENIED_TOOLS as $tool) {
            if (str_contains($content, $tool)) {
                $found[] = 'denied_tool:'.$tool;
            }
        }

        return $found;
    }

    public function assertClean(string $content): void
    {
        $violations = $this->violations($content);
        if ($violations !== []) {
            throw new OperationsKnowledgeRejectedException($violations);
        }
    }
}
