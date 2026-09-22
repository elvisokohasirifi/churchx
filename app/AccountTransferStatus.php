<?php

namespace App;

enum AccountTransferStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
