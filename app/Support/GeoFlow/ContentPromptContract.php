<?php

namespace App\Support\GeoFlow;

use App\Exceptions\ContentPromptContractException;

/**
 * 检查正文提示词是否保留任务渲染器认识的变量和条件块。
 */
final class ContentPromptContract
{
    public const MAX_CONTENT_LENGTH = 20000;

    /**
     * @throws ContentPromptContractException
     */
    public function assert(string $name, string $content): void
    {
        $name = trim($name);
        $content = trim($content);
        $nameFits = $name !== '' && mb_strlen($name) <= 100;
        $contentFits = $content !== '' && mb_strlen($content) <= self::MAX_CONTENT_LENGTH;
        $hasVariables = $this->hasVariable($content, 'title')
            && $this->hasVariable($content, 'keyword')
            && $this->hasVariable($content, 'knowledge');
        $hasConditionals = $this->hasConditional($content, 'keyword')
            && $this->hasConditional($content, 'knowledge');
        $balanced = $this->conditionalsAreBalanced($content);
        $usesArticleVariable = preg_match('/\{\{\s*content\s*\}\}/iu', $content) === 1;

        if (! $nameFits || ! $contentFits || ! $hasVariables || ! $hasConditionals || ! $balanced || $usesArticleVariable) {
            throw new ContentPromptContractException;
        }
    }

    private function hasVariable(string $content, string $name): bool
    {
        return preg_match('/\{\{\s*'.$name.'\s*\}\}/iu', $content) === 1;
    }

    private function hasConditional(string $content, string $name): bool
    {
        return preg_match('/\{\{#if\s+'.$name.'\s*\}\}/iu', $content) === 1;
    }

    private function conditionalsAreBalanced(string $content): bool
    {
        preg_match_all('/\{\{#if\s+([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/u', $content, $opens);
        preg_match_all('/\{\{\/if\}\}/u', $content, $closes);
        $names = $opens[1] ?? [];
        if ($names === [] || count($names) !== count($closes[0] ?? [])) {
            return false;
        }

        foreach ($names as $name) {
            if (! in_array(mb_strtolower((string) $name, 'UTF-8'), ['title', 'keyword', 'knowledge'], true)) {
                return false;
            }
        }

        return true;
    }
}
