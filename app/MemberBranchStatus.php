<?php

namespace App;

enum MemberBranchStatus: string
{
    case Active = 'active';
    case Transferred = 'transferred';
    case Inactive = 'inactive';
}
