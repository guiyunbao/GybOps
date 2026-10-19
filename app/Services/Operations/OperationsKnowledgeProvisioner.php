<?php

namespace App\Services\Operations;

use App\Models\KnowledgeBase;
use App\Models\KnowledgeBaseRevision;
use App\Models\SystemKnowledgeBase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 创建并维护运营知识库。
 */
final class OperationsKnowledgeProvisioner
{
    public function __construct(
        private readonly OperationsKnowledgeCatalog $catalog,
        private readonly OperationsKnowledgeGuard $guard,
    ) {}

    /**
     * @return array<string, KnowledgeBase>
     */
    public function ensure(): array
    {
        $bases = [];
        foreach ($this->catalog->definitions() as $definition) {
            $bases[$definition['key']] = $this->ensureOne($definition);
        }

        return $bases;
    }

    public function find(string $systemKey): KnowledgeBase
    {
        $binding = SystemKnowledgeBase::query()->with('knowledgeBase')->whereKey($systemKey)->first();
        $knowledgeBase = $binding?->knowledgeBase;
        if (! $knowledgeBase instanceof KnowledgeBase) {
            throw new RuntimeException('operations_knowledge_missing:'.$systemKey);
        }

        return $knowledgeBase;
    }

    public function append(string $systemKey, string $section): KnowledgeBase
    {
        $this->guard->assertClean($section);
        $this->ensure();

        return DB::transaction(function () use ($systemKey, $section): KnowledgeBase {
            $knowledgeBase = KnowledgeBase::query()
                ->whereKey($this->find($systemKey)->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $content = trim((string) $knowledgeBase->content);
            if (! str_contains($content, $section)) {
                $content = trim($content."\n\n".$section);
            }
            $this->persist($knowledgeBase, $content);

            return $knowledgeBase->fresh();
        });
    }

    /**
     * @param  array{key:string,name:string,description:string,risk_level:string,content:string}  $definition
     */
    private function ensureOne(array $definition): KnowledgeBase
    {
        $this->guard->assertClean($definition['content']);

        return DB::transaction(function () use ($definition): KnowledgeBase {
            $binding = SystemKnowledgeBase::query()->whereKey($definition['key'])->lockForUpdate()->first();
            if ($binding instanceof SystemKnowledgeBase) {
                $knowledgeBase = $binding->knowledgeBase;
                if (! $knowledgeBase instanceof KnowledgeBase) {
                    throw new RuntimeException('operations_knowledge_missing:'.$definition['key']);
                }

                return $knowledgeBase;
            }

            $knowledgeBase = KnowledgeBase::query()->create($this->payload($definition, $definition['content']));
            SystemKnowledgeBase::query()->create([
                'system_key' => $definition['key'],
                'knowledge_base_id' => $knowledgeBase->getKey(),
                'official_version' => '1',
                'official_content_hash' => hash('sha256', $definition['content']),
                'last_synced_at' => now(),
            ]);
            $this->revision($knowledgeBase, $definition['content']);

            return $knowledgeBase;
        });
    }

    private function persist(KnowledgeBase $knowledgeBase, string $content): void
    {
        $knowledgeBase->forceFill($this->payload([
            'name' => (string) $knowledgeBase->name,
            'description' => (string) $knowledgeBase->description,
            'risk_level' => (string) $knowledgeBase->risk_level,
        ], $content))->save();
        $binding = SystemKnowledgeBase::query()->where('knowledge_base_id', $knowledgeBase->getKey())->first();
        if ($binding instanceof SystemKnowledgeBase) {
            $binding->forceFill([
                'official_content_hash' => hash('sha256', $content),
                'last_synced_at' => now(),
            ])->save();
        }
        $this->revision($knowledgeBase, $content);
    }

    /**
     * @param  array{name:string,description:string,risk_level:string}  $definition
     * @return array<string, mixed>
     */
    private function payload(array $definition, string $content): array
    {
        return [
            'name' => $definition['name'],
            'description' => $definition['description'],
            'content' => $content,
            'character_count' => Str::length($content),
            'word_count' => Str::length(strip_tags($content)),
            'file_type' => 'markdown',
            'source_name' => '桂运宝运营知识',
            'source_type' => 'system',
            'business_line' => '桂运宝运营',
            'risk_level' => $definition['risk_level'],
            'review_status' => 'reviewed',
        ];
    }

    private function revision(KnowledgeBase $knowledgeBase, string $content): void
    {
        $next = (int) $knowledgeBase->revisions()->max('revision_number') + 1;
        KnowledgeBaseRevision::query()->create([
            'knowledge_base_id' => $knowledgeBase->getKey(),
            'revision_number' => $next,
            'content' => $content,
            'content_hash' => hash('sha256', $content),
            'source' => KnowledgeBaseRevision::SOURCE_OFFICIAL,
        ]);
    }
}
