<?php

namespace App\Models;

class VisitorAttendance extends UuidModel
{
    protected $fillable = ['service_id', 'visitor_id'];
}
