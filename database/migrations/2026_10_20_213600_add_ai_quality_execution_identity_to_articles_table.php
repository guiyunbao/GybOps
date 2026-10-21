<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            // SQLite test schemas rebuild articles through temporary tables; avoid a hard
            // foreign key here so existing URL-revision triggers keep working.
            $table->unsignedBigInteger('ai_quality_execution_admin_id')->nullable();
            $table->string('ai_quality_execution_admin_role', 32)->nullable();
            $table->unsignedBigInteger('ai_quality_execution_policy_version')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn([
                'ai_quality_execution_admin_id',
                'ai_quality_execution_admin_role',
                'ai_quality_execution_policy_version',
            ]);
        });
    }
};
