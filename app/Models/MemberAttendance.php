<?php

namespace App\Models;

class MemberAttendance extends UuidModel
{
    protected $fillable = ['service_id', 'member_id', 'status', 'checked_in_at'];

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime'];
    }
}
