<?php

use App\Models\Branch;
use App\Models\Church;
use App\Models\User;

it('onboards a church, branch, and administrator', function () {
    $this->artisan('church:setup', ['--no-migrate' => true])
        ->expectsQuestion('Administrator password', 'a-secure-password')
        ->expectsQuestion('Church name', 'Grace Church')
        ->expectsQuestion('Church email', 'office@example.test')
        ->expectsQuestion('Church phone', '+233200000000')
        ->expectsQuestion('Country', 'Ghana')
        ->expectsQuestion('Currency', 'GHS')
        ->expectsQuestion('Timezone', 'Africa/Accra')
        ->expectsQuestion('First branch code', 'HQ')
        ->expectsQuestion('First branch name', 'Head Office')
        ->expectsQuestion('Administrator name', 'Site Admin')
        ->expectsQuestion('Administrator email', 'admin@example.test')
        ->expectsQuestion('Administrator phone', '+233200000001')
        ->assertSuccessful();

    expect(Church::query()->where('name', 'Grace Church')->exists())->toBeTrue()
        ->and(Branch::query()->where('code', 'HQ')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'admin@example.test')->firstOrFail()->roles()->where('name', 'App Administrator')->exists())->toBeTrue();
});
