<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\ZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Zone extends Model
{
    /** @use HasFactory<ZoneFactory> */
    use CrudTrait, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = ['name', 'code', 'description', 'is_active'];

    protected $attributes = ['is_active' => true];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function leaders(): HasMany
    {
        return $this->hasMany(ZoneLeader::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
