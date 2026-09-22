<?php

namespace App;

enum ExpenseStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
