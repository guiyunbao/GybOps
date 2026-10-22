<?php

namespace App\Services\GeoFlow;

use Illuminate\Support\Facades\File;

/**
 * 用封闭词表找出物流宣传里的时效、运价、货损和排名绝对化表述。
 */
final class ArticleLogisticsClaimScanner
{
    /** @var array{version: string, phrases: list<array{phrase: string, category: string, suggestion: string}>}|null */
    private ?array $lexicon = null;

    public function version(): string
    {
        return (string) ($this->lexicon()['version'] ?? 'logistics-claims-1.0.0');
    }

    /**
     * @param  array<string, mixed>  $article
     * @param  list<array<string, mixed>>  $existingIssues
     * @return list<array<string, mixed>>
     */
    public function issues(array $article, array $existingIssues = []): array
    {
        $covered = [];
        foreach ($existingIssues as $issue) {
            if (! is_array($issue)) {
                continue;
            }
            $quote = $this->normalize((string) ($issue['quote'] ?? ''));
            if ($quote !== '') {
                $covered[] = $quote;
            }
        }

        $found = [];
        foreach (['title', 'excerpt', 'content'] as $field) {
            foreach ($this->sentences((string) ($article[$field] ?? '')) as $sentence) {
                if ($this->negated($sentence)) {
                    continue;
                }
                foreach ($this->lexicon()['phrases'] as $entry) {
                    $phrase = $entry['phrase'];
                    if (! str_contains($sentence, $phrase) || $this->alreadyCovered($phrase, $covered)) {
                        continue;
                    }
                    $covered[] = $this->normalize($phrase);
                    $found[] = [
                        'code' => 'ad_absolute_claim',
                        'code_family' => 'advertising_compliance',
                        'severity' => 'critical',
                        'hard_blocker' => true,
                        'field' => $field,
                        'quote' => $phrase,
                        'paragraph_index' => 1,
                        'reason' => '物流宣传词表命中绝对化表述：'.$entry['category'].'。',
                        'suggestion' => $entry['suggestion'],
                        'legal_refs' => ['CN-AD-LAW-09'],
                        'location_status' => 'resolved',
                        'references_valid' => true,
                        'claim_source' => 'logistics_lexicon',
                        'claim_category' => $entry['category'],
                        'lexicon_version' => $this->version(),
                    ];
                }
            }
        }

        return $found;
    }

    /**
     * @return array{version: string, phrases: list<array{phrase: string, category: string, suggestion: string}>}
     */
    private function lexicon(): array
    {
        if ($this->lexicon !== null) {
            return $this->lexicon;
        }

        $decoded = json_decode((string) File::get(resource_path('rules/logistics-claims-v1.json')), true);
        $phrases = [];
        foreach (is_array($decoded['phrases'] ?? null) ? $decoded['phrases'] : [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $phrase = trim((string) ($entry['phrase'] ?? ''));
            if ($phrase === '') {
                continue;
            }
            $phrases[] = [
                'phrase' => $phrase,
                'category' => trim((string) ($entry['category'] ?? 'ranking_superlative')),
                'suggestion' => trim((string) ($entry['suggestion'] ?? '改成知识库能够证明的表述，去掉绝对化承诺。')),
            ];
        }
        usort($phrases, static fn (array $left, array $right): int => mb_strlen($right['phrase']) <=> mb_strlen($left['phrase']));
        $this->lexicon = [
            'version' => trim((string) ($decoded['version'] ?? 'logistics-claims-1.0.0')),
            'phrases' => $phrases,
        ];

        return $this->lexicon;
    }

    /** @return list<string> */
    private function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[。！？\n])/u', $text) ?: [];

        return array_values(array_filter(array_map(
            static fn (string $sentence): string => trim($sentence),
            $parts,
        ), static fn (string $sentence): bool => $sentence !== ''));
    }

    private function negated(string $sentence): bool
    {
        return str_contains($sentence, '不承诺')
            || str_contains($sentence, '无法保证')
            || str_contains($sentence, '不保证');
    }

    /** @param  list<string>  $covered */
    private function alreadyCovered(string $phrase, array $covered): bool
    {
        $needle = $this->normalize($phrase);
        foreach ($covered as $quote) {
            if ($quote !== '' && (str_contains($quote, $needle) || str_contains($needle, $quote))) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/\s+/u', '', $value) ?? $value;

        return mb_strtolower($value, 'UTF-8');
    }
}
