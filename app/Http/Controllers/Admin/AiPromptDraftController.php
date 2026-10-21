<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AiModelAccessException;
use App\Exceptions\AiModelRuntimeEligibilityException;
use App\Exceptions\ContentPromptContractException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContentPromptDraftRequest;
use App\Models\Admin;
use App\Services\AiWorkspace\AiWorkspaceModelUnavailableException;
use App\Services\GeoFlow\ContentPromptDraftService;
use Illuminate\Http\JsonResponse;

/**
 * 物流正文提示词初版接口。结果只返回给编辑框，不写入提示词库。
 */
class AiPromptDraftController extends Controller
{
    public function __construct(private readonly ContentPromptDraftService $drafts) {}

    public function store(StoreContentPromptDraftRequest $request): JsonResponse
    {
        return response()->json($this->drafts->draft($request->string('scenario')->toString(), $request->brief()));
    }

    public function rewrite(StoreContentPromptDraftRequest $request): JsonResponse
    {
        $admin = $request->user('admin');
        if (! $admin instanceof Admin) {
            abort(401);
        }

        try {
            return response()->json($this->drafts->rewrite(
                $admin,
                $request->string('scenario')->toString(),
                $request->brief(),
                $request->integer('model_id'),
            ));
        } catch (ContentPromptContractException) {
            return response()->json(['message' => __('admin.ai_prompts.draft.invalid_contract')], 422);
        } catch (AiModelAccessException|AiModelRuntimeEligibilityException|AiWorkspaceModelUnavailableException) {
            return response()->json(['message' => __('admin.ai_prompts.draft.model_unavailable')], 422);
        }
    }
}
