<?php

namespace App;

enum ServiceScope: string
{
    case Branch = 'branch';
    case Joint = 'joint';
    case ChurchWide = 'church_wide';
}
