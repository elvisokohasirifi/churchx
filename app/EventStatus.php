<?php

namespace App;

enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
