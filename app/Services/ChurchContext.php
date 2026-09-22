<?php

namespace App\Services;

use App\Models\Church;
use Illuminate\Support\Str;

class ChurchContext
{
    public function currency(): string
    {
        $currency = Church::query()->value('currency') ?? config('app.currency', 'USD');

        return Str::upper((string) $currency);
    }

    public function address(): ?string
    {
        return Church::query()->value('address');
    }
}
