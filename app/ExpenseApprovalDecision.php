<?php

namespace App;

enum ExpenseApprovalDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
}
