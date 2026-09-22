<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadershipTitle extends Model
{
    use CrudTrait, HasUuid;

    protected $fillable = ['name', 'description'];

    public function branchLeaders(): HasMany
    {
        return $this->hasMany(BranchLeader::class);
    }
}
