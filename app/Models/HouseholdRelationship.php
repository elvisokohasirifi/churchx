<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class HouseholdRelationship extends Model
{
    use CrudTrait, HasUuid;

    protected $fillable = ['name', 'description'];
}
