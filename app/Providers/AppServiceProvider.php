<?php

namespace App\Providers;

use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\Entity;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\CalendarEventPolicy;
use App\Policies\ContactPolicy;
use App\Policies\EntityPolicy;
use App\Policies\TenantPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(Entity::class, EntityPolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(CalendarEvent::class, CalendarEventPolicy::class);

        Gate::define('tenant.active', function (User $user): bool {
            if (! is_numeric($user->current_tenant_id)) {
                return false;
            }

            return $user->tenants()
                ->where('tenants.id', (int) $user->current_tenant_id)
                ->exists();
        });

        Gate::define('tenant.member', function (User $user, ?int $tenantId = null): bool {
            $resolvedTenantId = $tenantId;

            if ($resolvedTenantId === null && is_numeric($user->current_tenant_id)) {
                $resolvedTenantId = (int) $user->current_tenant_id;
            }

            if (! is_int($resolvedTenantId) || $resolvedTenantId <= 0) {
                return false;
            }

            return $user->tenants()
                ->where('tenants.id', $resolvedTenantId)
                ->exists();
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
