<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AiModelAccessException;
use App\Exceptions\AiModelRuntimeEligibilityException;
use App\Exceptions\ArticleSeoMetaContractException;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\KnowledgeBase;
use App\Models\Prompt;
use App\Models\Title;
use App\Services\GeoFlow\ArticleCitationMarkerCleaner;
use App\Services\GeoFlow\ArticleContentGenerationService;
use App\Services\GeoFlow\ArticleContentPromptRenderer;
use App\Services\GeoFlow\ArticleRiskScanner;
use App\Services\GeoFlow\ArticleSeoMetaSuggestionService;
use App\Services\GeoFlow\DirectAdminAiExecutionGuard;
use App\Services\GeoFlow\DirectAdminAiModelInvocationGateway;
use App\Services\GeoFlow\KnowledgeRetrievalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ArticleEditorAssistantController extends Controller
{
    public function __construct(
        private readonly ArticleContentPromptRenderer $promptRenderer,
        private readonly ArticleContentGenerationService $generationService,
        private readonly KnowledgeRetrievalService $knowledgeRetrievalService,
        private readonly ArticleCitationMarkerCleaner $citationMarkerCleaner,
        private readonly DirectAdminAiExecutionGuard $executionGuard,
        private readonly DirectAdminAiModelInvocationGateway $invocationGateway,
        private readonly ArticleSeoMetaSuggestionService $seoMetaSuggestions,
    ) {}

    public function titles(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'library_id' => ['nullable', 'integer', 'min:1', 'exists:title_libraries,id'],
            'search' => ['nullable', 'string', 'max:200'],
            'usage' => ['nullable', Rule::in(['unused', 'all', 'used'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $usage = (string) ($validated['usage'] ?? 'unused');

        $titles = Title::query()
            ->select(['id', 'library_id', 'title', 'keyword', 'is_ai_generated', 'used_count', 'usage_count'])
            ->with('library:id,name')
            ->when(isset($validated['library_id']), fn (Builder $query): Builder => $query->where('library_id', (int) $validated['library_id']))
            ->when($usage === 'unused', fn (Builder $query): Builder => $query->where(function (Builder $query): void {
                $query->whereNull('used_count')->orWhere('used_count', '<=', 0);
            }))
            ->when($usage === 'used', fn (Builder $query): Builder => $query->where('used_count', '>', 0))
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query
                    ->whereLike('title', '%'.$search.'%')
                    ->orWhereLike('keyword', '%'.$search.'%')
                    ->orWhereHas('library', fn (Builder $libraryQuery): Builder => $libraryQuery->whereLike('name', '%'.$search.'%'));
            }))
            ->orderByRaw('COALESCE(used_count, 0) ASC')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return response()->json([
            'items' => collect($titles->items())->map(static fn (Title $title): array => [
                'id' => (int) $title->id,
                'title' => (string) $title->title,
                'keyword' => (string) ($title->keyword ?? ''),
                'library_id' => (int) $title->library_id,
                'library_name' => (string) ($title->library?->name ?? ''),
                'is_ai_generated' => (bool) $title->is_ai_generated,
                'used_count' => (int) ($title->used_count ?? 0),
            ])->values(),
            'pagination' => [
                'page' => $titles->currentPage(),
                'last_page' => $titles->lastPage(),
                'total' => $titles->total(),
            ],
        ]);
    }

    public function generate(Request $request): Response
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'keyword' => ['nullable', 'string', 'max:200'],
            'knowledge_base_id' => ['required', 'integer', 'min:1', Rule::exists('knowledge_bases', 'id')],
            'prompt_id' => [
                'required',
                'integer',
                Rule::exists('prompts', 'id')->where(fn ($query) => $query->where('type', 'content')),
            ],
            'ai_model_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ]);

        $knowledgeBase = KnowledgeBase::query()
            ->whereKey((int) $validated['knowledge_base_id'])
            ->firstOrFail(['id']);
        $prompt = Prompt::query()->whereKey((int) $validated['prompt_id'])->where('type', 'content')->firstOrFail();
        try {
            $executionContext = $this->executionGuard->freeze(
                $request->user('admin'),
                'article_editor',
                (int) $knowledgeBase->id,
                requestedModelId: isset($validated['ai_model_id']) ? (int) $validated['ai_model_id'] : null,
            );
            $selection = $this->executionGuard->resolveModel($executionContext);
            $aiModel = $selection['model'];
        } catch (AiModelAccessException $exception) {
            return response()->json([
                'message' => $exception->getErrorCode(),
                'error_code' => $exception->getErrorCode(),
            ], 404);
        } catch (AiModelRuntimeEligibilityException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error_code' => AiModelAccessException::AI_MODEL_UNAVAILABLE,
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable) {
            return response()->json(['message' => __('admin.article_assistant.generate.failed')], 500);
        }

        $knowledgeContext = $this->knowledgeRetrievalService->retrieveContext(
            (int) $knowledgeBase->id,
            implode("\n", array_filter([
                trim((string) $validated['title']),
                trim((string) ($validated['keyword'] ?? '')),
            ])),
            5,
            3200,
            $request->user('admin'),
            $executionContext->requestId,
        );
        if ($knowledgeContext === '') {
            return response()->json([
                'message' => __('admin.article_assistant.generate.knowledge_unavailable'),
            ], 422);
        }

        $contentPrompt = $this->promptRenderer->renderForEditor(
            trim((string) $validated['title']),
            trim((string) ($validated['keyword'] ?? '')),
            (string) $prompt->content,
            $knowledgeContext,
        );

        $response = response()->stream(function () use ($aiModel, $contentPrompt, $executionContext, $knowledgeBase): iterable {
            $invocation = null;
            $streamSession = null;
            $stream = null;
            $providerReturned = false;
            try {
                $invocation = $this->invocationGateway->acquire(
                    $executionContext,
                    $this->generationService->providerTimeoutSeconds() + 60,
                );
                $aiModel = $invocation->model;
                $streamSession = $this->generationService->deferredStreamWithReservation(
                    $aiModel,
                    $contentPrompt,
                    $invocation->reservation,
                    fn () => $invocation->beginUsageAttempt(
                        requestPayload: $contentPrompt,
                        operation: 'article_editor.generate',
                        businessSource: 'article_editor',
                        sourceType: KnowledgeBase::class,
                        sourceId: (int) $knowledgeBase->id,
                    ),
                );
                $stream = $streamSession->stream;
                foreach ($stream as $event) {
                    $this->executionGuard->assertModelCurrent($executionContext, $aiModel);
                    yield 'data: '.($event)."\n\n";
                }
                $providerReturned = true;

                $this->executionGuard->assertModelCurrent($executionContext, $aiModel);
                $content = $this->citationMarkerCleaner->cleanContent((string) $stream->text);
                $this->executionGuard->assertModelCurrent($executionContext, $aiModel);
                $payload = json_encode([
                    'type' => 'article_content_replacement',
                    'content' => $content,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (is_string($payload)) {
                    yield 'data: '.$payload."\n\n";
                }

                if ($content !== '') {
                    DB::transaction(function () use ($executionContext, $aiModel, $knowledgeBase, $streamSession): void {
                        $this->executionGuard->assertModelCurrent($executionContext, $aiModel);
                        KnowledgeBase::query()->whereKey((int) $knowledgeBase->id)->increment('usage_count');
                        $this->executionGuard->assertModelCurrent($executionContext, $aiModel);
                        $streamSession->complete();
                        $this->executionGuard->assertModelCurrent($executionContext, $aiModel);
                    });
                }
                $this->executionGuard->assertModelCurrent($executionContext, $aiModel);
                $invocation->recordDelivered($stream->usage ?? null);
                yield "data: [DONE]\n\n";
            } catch (AiModelAccessException $exception) {
                $invocation?->recordRevoked($exception->getErrorCode(), $stream?->usage ?? null);
                yield $this->safeSseError($exception->getErrorCode());
            } catch (Throwable) {
                if ($providerReturned) {
                    $invocation?->recordDiscarded('ai_result_persistence_failed', $stream?->usage ?? null);
                } else {
                    $invocation?->recordProviderFailure();
                }
                Log::warning('Article assistant stream stopped safely.', [
                    'execution' => $executionContext->toSafeArray(),
                    'ai_model_id' => (int) $aiModel->id,
                ]);
                yield $this->safeSseError('ai_model_unavailable');
            } finally {
                $streamSession?->abort();
                $invocation?->close();
            }
        }, headers: ['Content-Type' => 'text/event-stream']);
        $response->headers->set('Cache-Control', 'no-cache, no-transform');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    /**
     * 根据当前文章、知识库和系统关键词生成 SEO 建议。
     * 只返回可回填的字段，不写入文章。
     */
    public function optimizeSeo(Request $request): JsonResponse
    {
        $admin = $request->user('admin');
        if (! $admin instanceof Admin) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'excerpt' => ['nullable', 'string', 'max:'.ArticleRiskScanner::MAX_EXCERPT_CHARACTERS],
            'content' => ['required', 'string', 'max:'.ArticleRiskScanner::MAX_CONTENT_CHARACTERS],
            'keywords' => ['nullable', 'string', 'max:500'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'knowledge_base_id' => ['nullable', 'integer', 'min:1', Rule::exists('knowledge_bases', 'id')],
            'article_id' => ['nullable', 'integer', 'min:1', Rule::exists('articles', 'id')],
            'ai_model_id' => ['nullable', 'integer', 'min:1'],
        ], [
            'title.required' => __('admin.article_assistant.seo.title_required'),
            'content.required' => __('admin.article_assistant.seo.content_required'),
        ]);

        $title = trim((string) $validated['title']);
        $content = trim((string) $validated['content']);
        if ($title === '' || mb_strlen($content) < 8) {
            return response()->json([
                'message' => $title === ''
                    ? __('admin.article_assistant.seo.title_required')
                    : __('admin.article_assistant.seo.content_required'),
            ], 422);
        }

        try {
            $suggestion = $this->seoMetaSuggestions->suggest($admin, [
                'title' => $title,
                'excerpt' => trim((string) ($validated['excerpt'] ?? '')),
                'content' => $content,
                'keywords' => trim((string) ($validated['keywords'] ?? '')),
                'meta_description' => trim((string) ($validated['meta_description'] ?? '')),
                'knowledge_base_id' => isset($validated['knowledge_base_id']) ? (int) $validated['knowledge_base_id'] : null,
                'article_id' => isset($validated['article_id']) ? (int) $validated['article_id'] : null,
                'ai_model_id' => isset($validated['ai_model_id']) ? (int) $validated['ai_model_id'] : null,
            ]);
        } catch (ArticleSeoMetaContractException) {
            return response()->json(['message' => __('admin.article_assistant.seo.invalid')], 422);
        } catch (AiModelAccessException|AiModelRuntimeEligibilityException) {
            return response()->json(['message' => __('admin.article_assistant.seo.model_unavailable')], 422);
        } catch (Throwable $exception) {
            Log::warning('Article SEO suggestion stopped safely.', [
                'exception_type' => $exception::class,
                'admin_id' => (int) $admin->getKey(),
            ]);

            return response()->json(['message' => __('admin.article_assistant.seo.failed')], 422);
        }

        $notes = [__('admin.article_assistant.seo.filled')];
        if ($suggestion['knowledge_bases'] !== []) {
            $notes[] = __('admin.article_assistant.seo.used_knowledge', [
                'names' => implode('、', $suggestion['knowledge_bases']),
            ]);
        }
        if ($suggestion['system_keywords'] !== []) {
            $notes[] = __('admin.article_assistant.seo.used_keywords', [
                'keywords' => implode('、', array_slice($suggestion['system_keywords'], 0, 8)),
            ]);
        }

        return response()->json([
            'keywords' => $suggestion['keywords'],
            'meta_description' => $suggestion['meta_description'],
            'message' => implode('', $notes),
        ]);
    }

    private function safeSseError(string $errorCode): string
    {
        $payload = json_encode([
            'type' => 'error',
            'error_code' => $errorCode,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return 'data: '.(is_string($payload) ? $payload : '{"type":"error","error_code":"ai_model_unavailable"}')."\n\n";
    }
}
