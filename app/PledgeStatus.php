<?php

namespace App;

enum PledgeStatus: string
{
    case Active = 'active';
    case Fulfilled = 'fulfilled';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';
}
