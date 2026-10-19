<?php

namespace App\Services\Operations;

use App\Exceptions\OperationsKnowledgeRejectedException;
use App\Models\OperationsBriefRun;
use App\Models\OperationsDifyConnection;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 拉取 Dify 摘要并写入运营摘要知识库。
 */
final class OperationsBriefSync
{
    public function __construct(
        private readonly DifyOperationsBriefClient $client,
        private readonly OperationsKnowledgeProvisioner $knowledge,
        private readonly OperationsKnowledgeGuard $guard,
        private readonly ApiKeyCrypto $crypto,
    ) {}

    public function sync(string $requestId, ?string $region = null, ?string $cargoType = null, ?Carbon $windowEnd = null): OperationsBriefRun
    {
        $requestId = trim($requestId);
        if ($requestId === '') {
            throw new RuntimeException('operations_brief_request_id_required');
        }

        $existing = OperationsBriefRun::query()->where('request_id', $requestId)->first();
        if ($existing instanceof OperationsBriefRun && $existing->status === OperationsBriefRun::STATUS_COMPLETED) {
            return $existing;
        }

        $windowEnd ??= now();
        $windowStart = $windowEnd->copy()->subDay();
        $region = trim((string) ($region ?: config('geoflow.operations_dify.region')));
        $cargoType = trim((string) ($cargoType ?: config('geoflow.operations_dify.cargo_type')));
        $run = $existing ?? new OperationsBriefRun(['request_id' => $requestId]);
        $run->fill([
            'status' => OperationsBriefRun::STATUS_RUNNING,
            'region' => $region,
            'cargo_type' => $cargoType,
            'window_start' => $windowStart,
            'window_end' => $windowEnd,
            'error_message' => null,
        ])->save();

        try {
            $summary = $this->client->summarize($this->connection(), [
                'region' => $region,
                'cargo_type' => $cargoType,
                'window_start' => $windowStart->toDateTimeString(),
                'window_end' => $windowEnd->toDateTimeString(),
                'request_id' => $requestId,
            ]);
            $this->guard->assertClean($summary);
            $section = $this->section($requestId, $region, $cargoType, $windowStart, $windowEnd, $summary);
            $knowledgeBase = $this->knowledge->append(OperationsKnowledgeCatalog::OPERATIONS_BRIEF, $section);
            $run->forceFill([
                'status' => OperationsBriefRun::STATUS_COMPLETED,
                'summary' => $summary,
                'knowledge_base_id' => $knowledgeBase->getKey(),
                'error_message' => null,
            ])->save();
        } catch (OperationsKnowledgeRejectedException $exception) {
            $run->forceFill([
                'status' => OperationsBriefRun::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
            ])->save();
        }

        return $run->fresh();
    }

    public function connection(): OperationsDifyConnection
    {
        $existing = OperationsDifyConnection::query()->where('enabled', true)->first();
        if ($existing instanceof OperationsDifyConnection) {
            return $existing;
        }

        $baseUrl = rtrim((string) config('geoflow.operations_dify.base_url'), '/');
        $apiKey = trim((string) config('geoflow.operations_dify.api_key'));
        if ($baseUrl === '' || $apiKey === '') {
            throw new RuntimeException('operations_dify_unconfigured');
        }

        return DB::transaction(function () use ($baseUrl, $apiKey): OperationsDifyConnection {
            return OperationsDifyConnection::query()->create([
                'base_url' => $baseUrl,
                'api_key' => $this->crypto->encrypt($apiKey),
                'enabled' => true,
            ]);
        });
    }

    private function section(
        string $requestId,
        string $region,
        string $cargoType,
        Carbon $windowStart,
        Carbon $windowEnd,
        string $summary,
    ): string {
        return implode("\n", [
            '## 运营摘要 '.$requestId,
            '- 请求号：'.$requestId,
            '- 时间窗：'.$windowStart->toDateTimeString().' 至 '.$windowEnd->toDateTimeString(),
            '- 区域：'.$region,
            '- 货类：'.$cargoType,
            '- 来源：Dify 只读摘要',
            $summary,
        ]);
    }
}
