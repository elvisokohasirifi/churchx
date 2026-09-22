<?php

namespace App;

enum BroadcastStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
