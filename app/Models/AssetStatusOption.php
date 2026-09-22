<?php

namespace App\Models;

class AssetStatusOption extends UuidModel
{
    protected $fillable = ['name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
