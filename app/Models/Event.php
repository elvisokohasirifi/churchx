<?php

namespace App\Models;

use App\EventStatus;
use App\ServiceScope;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends UuidModel
{
    use SoftDeletes;

    protected $fillable = ['branch_id', 'scope', 'name', 'description', 'flyer', 'start_date', 'end_date', 'start_time', 'end_time', 'location', 'status'];

    protected function casts(): array
    {
        return ['scope' => ServiceScope::class, 'status' => EventStatus::class, 'start_date' => 'date', 'end_date' => 'date'];
    }
}
