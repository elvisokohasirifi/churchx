<?php

namespace App;

enum OfferingCollectionStatus: string
{
    case Draft = 'draft';
    case Counted = 'counted';
    case Verified = 'verified';
    case Posted = 'posted';
}
