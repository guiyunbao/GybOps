<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationsSourceDocument extends Model
{
    public const STATUS_STORED = 'stored';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'library_key',
        'source_key',
        'url_hash',
        'url',
        'title',
        'status',
        'knowledge_base_id',
    ];

    protected function casts(): array
    {
        return [
            'knowledge_base_id' => 'integer',
        ];
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }
}
