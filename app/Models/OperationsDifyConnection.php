<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationsDifyConnection extends Model
{
    protected $fillable = [
        'base_url',
        'api_key',
        'enabled',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
