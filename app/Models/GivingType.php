<?php

namespace App\Models;

class GivingType extends UuidModel
{
    protected $fillable = ['name', 'description', 'requires_giver', 'default_fund_id', 'is_active'];

    protected function casts(): array
    {
        return ['requires_giver' => 'boolean', 'is_active' => 'boolean'];
    }
}
