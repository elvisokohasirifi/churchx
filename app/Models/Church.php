<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Database\Factories\ChurchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Church extends Model
{
    /** @use HasFactory<ChurchFactory> */
    use HasFactory, HasUuid;

    protected $fillable = [
        'name', 'logo', 'email', 'phone', 'website', 'address', 'country', 'currency', 'timezone', 'settings',
    ];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }
}
