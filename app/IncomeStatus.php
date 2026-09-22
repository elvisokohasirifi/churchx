<?php

namespace App;

enum IncomeStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Reversed = 'reversed';
    case Cancelled = 'cancelled';
}
