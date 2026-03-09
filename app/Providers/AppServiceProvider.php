<?php

namespace App\Providers;

use App\Models\AiSalesSuggestion;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Entity;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Observers\CalendarEventObserver;
use App\Observers\ContactObserver;
use App\Observers\DealObserver;
use App\Policies\AiSalesSuggestionPolicy;
use App\Policies\CalendarEventPolicy;
use App\Policies\ContactPolicy;
use App\Policies\DealPolicy;
use App\Policies\EntityPolicy;
use App\Policies\ItemPolicy;
use App\Policies\TenantPolicy;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiters();

        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(Entity::class, EntityPolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(CalendarEvent::class, CalendarEventPolicy::class);
        Gate::policy(Deal::class, DealPolicy::class);
        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(AiSalesSuggestion::class, AiSalesSuggestionPolicy::class);

        Deal::observe(DealObserver::class);
        Contact::observe(ContactObserver::class);
        CalendarEvent::observe(CalendarEventObserver::class);

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

    protected function configureRateLimiters(): void
    {
        RateLimiter::for('ai-chat', function (Request $request): Limit {
            $limit = max(1, (int) config('services.openai.rate_limit_per_minute', 30));
            $tenantId = TenantContext::id($request) ?? 'no-tenant';
            $userId = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute($limit)
                ->by(sprintf('ai-chat:%s:%s', (string) $tenantId, (string) $userId));
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
