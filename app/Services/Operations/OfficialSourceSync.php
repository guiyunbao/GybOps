<?php

namespace App\Services\Operations;

use App\Exceptions\OperationsKnowledgeRejectedException;
use App\Models\OperationsSourceDocument;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * 按固定官方列表抓取稿件摘录，写入对应知识库。
 */
final class OfficialSourceSync
{
    private const ITEM_LIMIT = 2;

    public function __construct(
        private readonly OfficialSourceCatalog $catalog,
        private readonly OfficialSourcePage $pages,
        private readonly OperationsKnowledgeProvisioner $knowledge,
        private readonly OperationsKnowledgeGuard $guard,
    ) {}

    /**
     * @return array{stored:int,skipped:int,rejected:int,failed:list<string>,notes:list<string>}
     */
    public function sync(string $cadence): array
    {
        if (! in_array($cadence, [OfficialSourceCatalog::CADENCE_WEEKDAY, OfficialSourceCatalog::CADENCE_WEEKLY], true)) {
            throw new RuntimeException('official_source_cadence_invalid');
        }

        $result = [
            'stored' => 0,
            'skipped' => 0,
            'rejected' => 0,
            'failed' => [],
            'notes' => [],
        ];
        foreach ($this->catalog->sources() as $source) {
            if ($source['list_url'] === '' && $source['cadence'] === $cadence) {
                $result['notes'][] = $source['name'].'：'.$source['note'];
            }
        }
        foreach ($this->catalog->automatic($cadence) as $source) {
            $this->syncSource($source, $result);
        }

        return $result;
    }

    /**
     * @param  array{key:string,library:string,name:string,cadence:string,list_url:string,host:string,link_pattern:string,title_contains:?string,note:string}  $source
     * @param  array{stored:int,skipped:int,rejected:int,failed:list<string>,notes:list<string>}  $result
     */
    private function syncSource(array $source, array &$result): void
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'GEOFlowOfficialSource/1.0'])
                ->connectTimeout(5)
                ->timeout(15)
                ->get($source['list_url']);
            $response->throw();
        } catch (Throwable $exception) {
            $result['failed'][] = $source['key'].':'.$this->failure($exception);

            return;
        }

        $links = $this->pages->links(
            $response->body(),
            $source['list_url'],
            $source['host'],
            $source['link_pattern'],
            $source['title_contains'],
        );
        $storedForSource = 0;
        foreach ($links as $link) {
            if ($storedForSource >= self::ITEM_LIMIT) {
                break;
            }
            $outcome = $this->storeLink($source, $link['url'], $link['title']);
            $result[$outcome]++;
            if ($outcome === 'stored') {
                $storedForSource++;
            }
        }
    }

    /**
     * @param  array{key:string,library:string,name:string,list_url:string,host:string}  $source
     */
    private function storeLink(array $source, string $url, string $linkTitle): string
    {
        $hash = hash('sha256', $url);
        if (OperationsSourceDocument::query()->where('url_hash', $hash)->exists()) {
            return 'skipped';
        }
        if (parse_url($url, PHP_URL_HOST) !== $source['host']) {
            return 'skipped';
        }

        try {
            $response = Http::withHeaders(['User-Agent' => 'GEOFlowOfficialSource/1.0'])
                ->connectTimeout(5)
                ->timeout(15)
                ->get($url);
            $response->throw();
            $article = $this->pages->article($response->body());
            if ($linkTitle !== '' && (str_contains($article['title'], '门户网站') || $article['title'] === '未命名官方稿件')) {
                $article['title'] = mb_substr($linkTitle, 0, 80);
            }
            if ($article['excerpt'] === '') {
                return 'skipped';
            }
            $marker = $this->pages->marker($url);
            $section = implode("\n", [
                '## '.$article['title'],
                '- 信源：'.$source['name'],
                '- 原文：'.$url,
                '- 抓取：'.now()->toDateString(),
                '<!-- '.$marker.' -->',
                $article['excerpt'],
            ]);
            $this->guard->assertClean($section);
            $knowledgeBase = $this->knowledge->append($source['library'], $section);
            OperationsSourceDocument::query()->create([
                'library_key' => $source['library'],
                'source_key' => $source['key'],
                'url_hash' => $hash,
                'url' => $url,
                'title' => $article['title'],
                'status' => OperationsSourceDocument::STATUS_STORED,
                'knowledge_base_id' => $knowledgeBase->getKey(),
            ]);

            return 'stored';
        } catch (OperationsKnowledgeRejectedException) {
            OperationsSourceDocument::query()->create([
                'library_key' => $source['library'],
                'source_key' => $source['key'],
                'url_hash' => $hash,
                'url' => $url,
                'title' => '已拦截',
                'status' => OperationsSourceDocument::STATUS_REJECTED,
                'knowledge_base_id' => null,
            ]);

            return 'rejected';
        } catch (ConnectionException|RequestException) {
            return 'skipped';
        }
    }

    private function failure(Throwable $exception): string
    {
        if ($exception instanceof RequestException) {
            return 'http_'.$exception->response->status();
        }

        return 'unavailable';
    }
}
