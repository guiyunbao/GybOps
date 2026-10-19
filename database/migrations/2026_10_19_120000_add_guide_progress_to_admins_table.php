<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 按管理员记住后台分步引导的完成时间。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table): void {
            if (! Schema::hasColumn('admins', 'guide_progress')) {
                $table->json('guide_progress')->nullable()->after('welcome_dismissed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table): void {
            if (Schema::hasColumn('admins', 'guide_progress')) {
                $table->dropColumn('guide_progress');
            }
        });
    }
};
