<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            // SQLite 测试库重建 articles 时不使用外键，避免影响已有触发器。
            $table->unsignedBigInteger('ai_quality_prompt_id')->nullable();
            $table->unsignedBigInteger('ai_quality_model_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn(['ai_quality_prompt_id', 'ai_quality_model_id']);
        });
    }
};
