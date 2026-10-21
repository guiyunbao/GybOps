<?php

use App\Support\GeoFlow\LogisticsContentPromptCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('prompts')) {
            return;
        }

        $now = now();
        $catalog = new LogisticsContentPromptCatalog;

        foreach (LogisticsContentPromptCatalog::KEYS as $scenario) {
            $prompt = $catalog->base($scenario);
            if (DB::table('prompts')->where('name', $prompt['name'])->exists()) {
                continue;
            }

            DB::table('prompts')->insert([
                'name' => $prompt['name'],
                'type' => 'content',
                'content' => $prompt['content'],
                'variables' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('prompts')) {
            return;
        }

        DB::table('prompts')
            ->whereIn('name', array_values(LogisticsContentPromptCatalog::NAMES))
            ->delete();
    }
};
