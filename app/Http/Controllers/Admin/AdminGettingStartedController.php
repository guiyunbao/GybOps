<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminGettingStartedCatalog;
use App\Support\AdminWeb;
use Illuminate\View\View;

/**
 * 展示免登录的后台新手帮助页。
 */
class AdminGettingStartedController extends Controller
{
    /**
     * 返回中文帮助页。未登录和已登录管理员都可以打开。
     */
    public function show(AdminGettingStartedCatalog $catalog): View
    {
        $locale = app()->getLocale();

        return view('admin.getting-started.show', [
            'adminSiteName' => AdminWeb::siteName(),
            'document' => $catalog->document(),
            'showLanguageNote' => ! in_array($locale, ['zh_CN', 'zh_TW'], true),
        ]);
    }
}
