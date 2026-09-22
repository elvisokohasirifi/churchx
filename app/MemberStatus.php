<?php

namespace App;

enum MemberStatus: string
{
    case NewConvert = 'new_convert';
    case Member = 'member';
    case Worker = 'worker';
    case Leader = 'leader';
    case Inactive = 'inactive';
    case Deceased = 'deceased';
    case Relocated = 'relocated';
}
