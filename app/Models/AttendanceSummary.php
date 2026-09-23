<?php

namespace App\Models;

class AttendanceSummary extends UuidModel
{
    protected $fillable = ['service_id', 'branch_id', 'total_male', 'total_female', 'total_children', 'total_members', 'total_visitors', 'captured_by'];

    protected $appends = ['total_attendance'];

    public function getTotalAttendanceAttribute(): int
    {
        return $this->total_members + $this->total_visitors;
    }
}
