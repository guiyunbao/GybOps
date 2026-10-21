<?php

namespace App\Services\GeoFlow;

use App\Ai\Agents\ContentPromptDraftAgent;
use App\Data\Ai\AiExecutionContext;
use App\Exceptions\AiModelAccessException;
use App\Exceptions\ContentPromptContractException;
use App\Models\Admin;
use App\Models\AiModel;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\ContentPromptContract;
use App\Support\GeoFlow\OpenAiRuntimeProvider;
use Closure;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * 生成物流正文提示词初版，或让已配置模型在同一契约下再写一版。
 */
final class ContentPromptDraftService
{
    public function __construct(
        private readonly LogisticsContentPromptComposer $composer,
        private readonly ContentPromptContract $contract,
        private readonly DirectAdminAiExecutionGuard $guard,
        private readonly DirectAdminAiModelInvocationGateway $gateway,
        private readonly AiUsageQuotaService $usageQuota,
        private readonly ApiKeyCrypto $crypto,
    ) {}

    /**
     * @param  array{brand?: string, services?: string, audience?: string, notes?: string}  $brief
     * @return array{name: string, content: string}
     */
    public function draft(string $scenario, array $brief): array
    {
        return $this->composer->compose($scenario, $brief);
    }

    /**
     * @param  array{brand?: string, services?: string, audience?: string, notes?: string}  $brief
     * @return array{name: string, content: string}
     */
    public function rewrite(Admin $admin, string $scenario, array $brief, int $modelId): array
    {
        $baseline = $this->composer->compose($scenario, $brief);
        $context = $this->guard->freeze(
            $admin,
            'content_prompt_draft',
            (int) $admin->getKey(),
            AiExecutionContext::CAPABILITY_CHAT,
            $modelId,
        );
        $invocation = null;
        $response = null;
        $attempted = false;

        try {
            $invocation = $this->gateway->acquire($context, $this->timeout() + 60);
            $input = $this->input($scenario, $brief, $baseline);
            $response = $this->request($invocation->model, $input, function () use ($invocation, $input, $admin, &$attempted): void {
                $invocation->beginUsageAttempt(
                    $input,
                    'content_prompt_draft.rewrite',
                    'content_prompt_draft',
                    Admin::class,
                    $admin->getKey(),
                );
                $attempted = true;
            });
            $draft = $this->draftFromResponse($response);
            $this->contract->assert($draft['name'], $draft['content']);
            $invocation->recordSuccess($response->usage);

            return $draft;
        } catch (ContentPromptContractException $exception) {
            $this->settleFailedAttempt($invocation, $attempted, $response);

            throw $exception;
        } catch (AiModelAccessException $exception) {
            $invocation?->recordRevoked($exception->getErrorCode(), $response?->usage);

            throw $exception;
        } catch (Throwable $exception) {
            if ($response instanceof StructuredAgentResponse) {
                $this->settleFailedAttempt($invocation, $attempted, $response);
            } elseif ($attempted) {
                $this->usageQuota->recordModelAttempt($invocation->reservation);
                $invocation?->recordProviderFailure('ai_provider_request_failed');
            }

            throw $exception;
        } finally {
            $invocation?->close();
        }
    }

    public function timeout(): int
    {
        return (int) config('geoflow.ai_quality_request_timeout_seconds', 160);
    }

    /**
     * @param  array{brand?: string, services?: string, audience?: string, notes?: string}  $brief
     * @param  array{name: string, content: string}  $baseline
     */
    private function input(string $scenario, array $brief, array $baseline): string
    {
        return json_encode([
            'scenario' => $scenario,
            'brief' => [
                'brand' => (string) ($brief['brand'] ?? ''),
                'services' => (string) ($brief['services'] ?? ''),
                'audience' => (string) ($brief['audience'] ?? ''),
                'notes' => (string) ($brief['notes'] ?? ''),
            ],
            'baseline_name' => $baseline['name'],
            'baseline_content' => $baseline['content'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function request(AiModel $model, string $input, ?Closure $beforeProvider = null): StructuredAgentResponse
    {
        $url = OpenAiRuntimeProvider::resolveChatBaseUrl((string) $model->api_url);
        $driver = OpenAiRuntimeProvider::resolveChatDriver($url, (string) $model->model_id);
        $key = $this->crypto->decrypt((string) $model->getRawOriginal('api_key'));
        $provider = OpenAiRuntimeProvider::registerProvider('content_prompt_draft', $driver, $url, $key);
        $agent = new ContentPromptDraftAgent((int) $model->max_tokens > 0 ? min(8192, (int) $model->max_tokens) : 8192);
        $beforeProvider?->__invoke();
        $response = $agent->prompt($input, [], $provider, (string) $model->model_id, $this->timeout());
        if (! $response instanceof StructuredAgentResponse || $response->steps->last()?->finishReason === FinishReason::Length) {
            throw new ContentPromptContractException;
        }

        return $response;
    }

    /**
     * @return array{name: string, content: string}
     */
    private function draftFromResponse(StructuredAgentResponse $response): array
    {
        $structured = $response->toArray();
        $name = trim((string) ($structured['name'] ?? ''));
        $content = trim((string) ($structured['content'] ?? ''));

        if ($name === '' || $content === '') {
            throw new ContentPromptContractException;
        }

        return ['name' => $name, 'content' => $content];
    }

    private function settleFailedAttempt(
        ?DirectAdminAiModelInvocation $invocation,
        bool $attempted,
        ?StructuredAgentResponse $response,
    ): void {
        if (! $attempted || ! $invocation instanceof DirectAdminAiModelInvocation) {
            return;
        }

        $this->usageQuota->recordModelAttempt($invocation->reservation);
        $invocation->recordDiscarded('invalid_prompt_contract', $response?->usage);
    }
}
