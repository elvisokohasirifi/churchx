<?php

namespace App;

enum AssetStatus: string
{
    case Active = 'active';
    case UnderRepair = 'under_repair';
    case InStorage = 'in_storage';
    case Lost = 'lost';
    case Disposed = 'disposed';
}
