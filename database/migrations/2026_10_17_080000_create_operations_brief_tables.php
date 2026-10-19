<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_dify_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('base_url');
            $table->text('api_key');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('operations_brief_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('request_id', 80)->unique();
            $table->string('status', 20);
            $table->string('region', 80)->nullable();
            $table->string('cargo_type', 80)->nullable();
            $table->timestamp('window_start')->nullable();
            $table->timestamp('window_end')->nullable();
            $table->longText('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('knowledge_base_id')->nullable()->constrained('knowledge_bases')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_brief_runs');
        Schema::dropIfExists('operations_dify_connections');
    }
};
