<?php

namespace App\Providers;

use App\Contracts\SmsProviderInterface;
use App\Contracts\WhatsAppProviderInterface;
use App\Models\Church;
use App\PermissionCode;
use App\Services\BmsSmsProvider;
use App\Services\BranchAccessService;
use App\Services\LoggingMessageProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsProviderInterface::class, BmsSmsProvider::class);
        $this->app->bind(WhatsAppProviderInterface::class, LoggingMessageProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        $access = $this->app->make(BranchAccessService::class);

        foreach (PermissionCode::cases() as $permission) {
            Gate::define($permission->value, fn ($user, $branch = null): bool => $access->allows($user, $permission, $branch));
        }

        if (! app()->runningInConsole() && Schema::hasTable('churches')) {
            $church = Church::query()->first();

            if ($church !== null) {
                config([
                    'app.name' => $church->name,
                    'app.timezone' => $church->timezone,
                    'app.currency' => $church->currency,
                    'backpack.base.project_name' => $church->name,
                    'backpack.theme-tabler.project_logo' => '<strong>'.e($church->name).'</strong>',
                ]);
            }
        }
    }
}
