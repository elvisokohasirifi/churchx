<?php

namespace App\Console\Commands;

use App\BranchStatus;
use App\Models\Branch;
use App\Models\Church;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('church:setup {--no-migrate : Skip running migrations} {--force : Run in production}')]
#[Description('Configure the church, first branch, and initial application administrator')]
class ChurchSetupCommand extends Command
{
    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Use --force to run setup in production.');

            return self::FAILURE;
        }

        if (! $this->option('no-migrate')) {
            Artisan::call('migrate', ['--force' => true]);
            $this->line(Artisan::output());
        }

        $this->call(DatabaseSeeder::class);
        $password = $this->secret('Administrator password');

        if (! is_string($password) || strlen($password) < 12) {
            $this->error('The administrator password must contain at least 12 characters.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($password): void {
            Church::query()->updateOrCreate(
                ['id' => Church::query()->value('id') ?? (string) Str::uuid7()],
                ['name' => $this->ask('Church name', 'My Church'), 'email' => $this->ask('Church email'), 'phone' => $this->ask('Church phone'), 'country' => $this->ask('Country', 'Ghana'), 'currency' => strtoupper($this->ask('Currency', 'GHS')), 'timezone' => $this->ask('Timezone', 'Africa/Accra')],
            );
            $branch = Branch::query()->firstOrCreate(
                ['code' => strtoupper($this->ask('First branch code', 'HQ'))],
                ['name' => $this->ask('First branch name', 'Head Office'), 'status' => BranchStatus::Active],
            );
            $admin = User::query()->create(['name' => $this->ask('Administrator name'), 'email' => $this->ask('Administrator email'), 'phone' => $this->ask('Administrator phone'), 'password' => Hash::make($password), 'is_active' => true]);
            UserRole::query()->create(['user_id' => $admin->id, 'role_id' => Role::query()->where('name', 'App Administrator')->firstOrFail()->id, 'branch_id' => null, 'is_active' => true, 'assigned_by' => $admin->id]);
            $this->info('Setup complete for '.$branch->name.'.');
        });

        return self::SUCCESS;
    }
}
