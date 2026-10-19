<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\Admin\AdminGuideCatalog;
use App\Support\AdminActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 记录当前管理员的分步引导完成状态。
 */
class AdminGuideController extends Controller
{
    /**
     * 完成或跳过一组引导后，按引导 id 写入完成时间。
     */
    public function complete(Request $request, AdminGuideCatalog $catalog): JsonResponse
    {
        /** @var Admin|null $admin */
        $admin = Auth::guard('admin')->user();
        if (! $admin instanceof Admin) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $guideId = $request->string('id')->toString();
        if (! $catalog->has($guideId)) {
            return response()->json(['success' => false, 'message' => 'Unknown guide'], 422);
        }

        $progress = is_array($admin->guide_progress) ? $admin->guide_progress : [];
        $progress[$guideId] = now()->toIso8601String();
        $admin->forceFill(['guide_progress' => $progress])->save();

        AdminActivityLogger::logFromRequest($request, $admin, 'guide:complete', [
            'admin_id' => (int) $admin->id,
            'guide_id' => $guideId,
        ]);

        return response()->json(['success' => true]);
    }
}
