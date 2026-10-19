<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_source_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('library_key', 64);
            $table->string('source_key', 64);
            $table->string('url_hash', 64)->unique();
            $table->string('url', 500);
            $table->string('title', 200);
            $table->string('status', 20);
            $table->foreignId('knowledge_base_id')->nullable()->constrained('knowledge_bases')->nullOnDelete();
            $table->timestamps();

            $table->index(['library_key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_source_documents');
    }
};
