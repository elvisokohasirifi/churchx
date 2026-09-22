<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InitialRegistrationService
{
    public function __construct(private AuditLogService $audit, private RoleProvisioningService $roles) {}

    /** @param array<string, mixed> $data */
    public function register(array $data): User
    {
        return Cache::lock('church-initial-registration', 15)->block(5, function () use ($data): User {
            if (! $this->isOpen()) {
                throw new DomainException('Initial registration is closed.');
            }

            return DB::transaction(function () use ($data): User {
                $church = Church::query()->first() ?? new Church;
                $settings = $church->settings ?? [];
                $settings['initial_registration_completed'] = true;
                $church->fill([
                    'name' => $data['church_name'],
                    'email' => $data['church_email'] ?? null,
                    'phone' => $data['church_phone'] ?? null,
                    'website' => $data['church_website'] ?? null,
                    'address' => $data['church_address'] ?? null,
                    'country' => $data['church_country'],
                    'currency' => $data['church_currency'],
                    'timezone' => $data['church_timezone'],
                    'settings' => $settings,
                ])->save();

                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'password' => $data['password'],
                    'pin' => $data['pin'] ?? null,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);

                $role = $this->appAdministratorRole();
                UserRole::query()->create([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'branch_id' => null,
                    'is_active' => true,
                    'assigned_by' => $user->id,
                    'assigned_at' => now(),
                ]);

                $this->audit->record('church.initial_registration', $user, $church);

                return $user;
            });
        });
    }

    public function isOpen(): bool
    {
        $church = Church::query()->first();

        return ! User::withTrashed()->exists()
            && ! (bool) data_get($church?->settings, 'initial_registration_completed', false);
    }

    private function appAdministratorRole(): Role
    {
        return $this->roles->provision()->get('App Administrator');
    }
}
