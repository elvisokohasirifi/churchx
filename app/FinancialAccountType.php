<?php

namespace App;

enum FinancialAccountType: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case MobileMoney = 'mobile_money';
    case PettyCash = 'petty_cash';
}
