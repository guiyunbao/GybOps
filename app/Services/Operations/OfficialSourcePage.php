<?php

namespace App\Services\Operations;

use DOMDocument;
use DOMElement;

/**
 * 从官方列表页和稿件页抽出同源链接、标题和短摘录。
 */
final class OfficialSourcePage
{
    /**
     * @return list<array{url:string,title:string}>
     */
    public function links(string $html, string $baseUrl, string $host, string $pattern, ?string $titleContains): array
    {
        $document = $this->document($html);
        $found = [];
        foreach ($document->getElementsByTagName('a') as $anchor) {
            if (! $anchor instanceof DOMElement) {
                continue;
            }
            $href = trim($anchor->getAttribute('href'));
            $url = $this->absolute($baseUrl, $href);
            if ($url === null || ! $this->sameHost($url, $host)) {
                continue;
            }
            $path = (string) parse_url($url, PHP_URL_PATH);
            if (preg_match($pattern, $path) !== 1) {
                continue;
            }
            $title = trim($anchor->getAttribute('title'));
            if ($title === '') {
                $title = trim($anchor->textContent);
            }
            if ($titleContains !== null && $titleContains !== '' && ! str_contains($title, $titleContains)) {
                continue;
            }
            $found[$url] ??= ['url' => $url, 'title' => $title];
        }

        return array_values($found);
    }

    /**
     * @return array{title:string,excerpt:string}
     */
    public function article(string $html): array
    {
        $withoutScripts = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $title = '';
        if (preg_match('#<title\b[^>]*>(.*?)</title>#is', $withoutScripts, $match) === 1) {
            $title = $this->plain($match[1]);
        }
        $title = trim((string) preg_replace('/\s*[-_|｜]\s*.{0,20}$/u', '', $title));
        $text = $this->plain($withoutScripts);

        return [
            'title' => mb_substr($title !== '' ? $title : '未命名官方稿件', 0, 80),
            'excerpt' => mb_substr($text, 0, 500),
        ];
    }

    public function marker(string $url): string
    {
        return 'official-source:'.hash('sha256', $url);
    }

    private function document(string $html): DOMDocument
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }

    private function absolute(string $baseUrl, string $href): ?string
    {
        if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:')) {
            return null;
        }
        $parts = parse_url($baseUrl);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        if (str_starts_with($href, '//')) {
            $absolute = $parts['scheme'].':'.$href;
        } elseif (preg_match('#^https?://#i', $href) === 1) {
            $absolute = $href;
        } elseif (str_starts_with($href, '/')) {
            $absolute = $parts['scheme'].'://'.$parts['host'].$href;
        } else {
            $path = $parts['path'] ?? '/';
            $directory = str_ends_with($path, '/') ? $path : (preg_replace('#/[^/]*$#', '/', $path) ?? '/');
            $absolute = $parts['scheme'].'://'.$parts['host'].$directory.$href;
        }
        $absolute = preg_replace('#/\./#', '/', $absolute) ?? $absolute;
        $fragmentless = preg_replace('/#.*$/', '', $absolute) ?? $absolute;

        return rtrim($fragmentless, '/');
    }

    private function sameHost(string $url, string $host): bool
    {
        return strcasecmp((string) parse_url($url, PHP_URL_HOST), $host) === 0;
    }

    private function plain(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $collapsed = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($collapsed);
    }
}
