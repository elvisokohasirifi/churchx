<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

trait HasUuid
{
    use HasUuids, LogsModelActivity;

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }
}
