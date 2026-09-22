<?php

namespace App\Models;

class PaymentMethod extends UuidModel
{
    protected $fillable = ['name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
