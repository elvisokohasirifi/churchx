<?php

namespace App\Models;

class Fund extends UuidModel
{
    protected $fillable = ['name', 'description', 'restricted', 'is_active'];

    protected function casts(): array
    {
        return ['restricted' => 'boolean', 'is_active' => 'boolean'];
    }
}
