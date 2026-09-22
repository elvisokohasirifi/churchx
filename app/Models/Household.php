<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Household extends Model
{
    use CrudTrait, HasUuid;

    protected $fillable = ['family_name', 'address'];

    public function members(): HasMany
    {
        return $this->hasMany(HouseholdMember::class);
    }
}
