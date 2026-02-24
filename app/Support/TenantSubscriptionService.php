<?php

namespace App\Support;

use App\Models\CompanySetting;
use App\Models\Entity;
use App\Models\Item;
use App\Models\Plan;
use App\Models\PlanChangeLog;
use App\Models\SupplierInvoice;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TenantSubscriptionService
{
    public function ensureDefaultPlans(): void
    {
        $defaultTrialDays = max(0, (int) config('tenancy.default_trial_days', 14));

        Plan::query()->updateOrCreate(
            ['code' => 'starter'],
            [
                'name' => 'Starter',
                'description' => 'Essential tenant plan',
                'price_cents' => 0,
                'billing_cycle_days' => 30,
                'max_users' => 3,
                'max_customers' => 100,
                'storage_limit_gb' => 2,
                'trial_days' => $defaultTrialDays,
                'features' => ['core_crm'],
                'status' => 'active',
            ]
        );

        Plan::query()->updateOrCreate(
            ['code' => 'growth'],
            [
                'name' => 'Growth',
                'description' => 'Advanced tenant plan',
                'price_cents' => 5900,
                'billing_cycle_days' => 30,
                'max_users' => 50,
                'max_customers' => 5000,
                'storage_limit_gb' => 20,
                'trial_days' => $defaultTrialDays,
                'features' => ['core_crm', 'premium_reports', 'priority_support'],
                'status' => 'active',
            ]
        );
    }

    public function ensureSubscription(Tenant $tenant, ?User $actor = null): TenantSubscription
    {
        $this->ensureDefaultPlans();

        $starterPlan = Plan::query()
            ->where('code', 'starter')
            ->firstOrFail();

        $subscription = TenantSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($subscription === null) {
            $now = CarbonImmutable::now();
            $subscription = TenantSubscription::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $starterPlan->id,
                'status' => 'trialing',
                'trial_ends_at' => $now->addDays(max(0, (int) $starterPlan->trial_days)),
                'current_period_start_at' => $now,
                'current_period_end_at' => $now->addDays(max(1, (int) $starterPlan->billing_cycle_days)),
                'cancel_at_period_end' => false,
                'last_proration_amount_cents' => 0,
            ]);

            $this->logPlanChange(
                tenantId: $tenant->id,
                actor: $actor,
                fromPlanId: null,
                toPlanId: $starterPlan->id,
                changeType: 'trial_started',
                effectiveAt: $now,
                prorationAmountCents: 0,
                metadata: [
                    'trial_days' => (int) $starterPlan->trial_days,
                ],
            );
        }

        return $this->syncLifecycle($subscription, $actor);
    }

    public function syncLifecycle(TenantSubscription $subscription, ?User $actor = null): TenantSubscription
    {
        $subscription->loadMissing(['plan', 'pendingPlan']);

        $now = CarbonImmutable::now();
        $dirty = false;

        if (
            $subscription->status === 'trialing'
            && (int) ($subscription->plan?->price_cents ?? 0) > 0
        ) {
            $cycleDays = max(1, (int) ($subscription->plan?->billing_cycle_days ?? 30));

            $subscription->status = 'active';
            $subscription->trial_ends_at = null;

            if (
                $subscription->current_period_start_at === null
                || $subscription->current_period_end_at === null
                || $now->greaterThanOrEqualTo(CarbonImmutable::parse((string) $subscription->current_period_end_at))
            ) {
                $subscription->current_period_start_at = $now;
                $subscription->current_period_end_at = $now->addDays($cycleDays);
            }

            $dirty = true;
        }

        if (
            $subscription->status === 'trialing'
            && $subscription->trial_ends_at !== null
            && $now->greaterThanOrEqualTo(CarbonImmutable::parse((string) $subscription->trial_ends_at))
        ) {
            $trialEnd = CarbonImmutable::parse((string) $subscription->trial_ends_at);
            $cycleDays = max(1, (int) ($subscription->plan?->billing_cycle_days ?? 30));

            $subscription->status = 'active';
            $subscription->current_period_start_at = $trialEnd;
            $subscription->current_period_end_at = $trialEnd->addDays($cycleDays);
            $dirty = true;

            $this->logPlanChange(
                tenantId: $subscription->tenant_id,
                actor: $actor,
                fromPlanId: $subscription->plan_id,
                toPlanId: $subscription->plan_id,
                changeType: 'trial_ended',
                effectiveAt: $trialEnd,
            );
        }

        if (
            $subscription->pending_plan_id !== null
            && $subscription->pending_plan_effective_at !== null
            && $now->greaterThanOrEqualTo(CarbonImmutable::parse((string) $subscription->pending_plan_effective_at))
        ) {
            $fromPlanId = $subscription->plan_id;
            $toPlanId = (int) $subscription->pending_plan_id;
            $targetPlan = Plan::query()->find($toPlanId);

            if ($targetPlan !== null) {
                $subscription->plan_id = $targetPlan->id;
                $subscription->pending_plan_id = null;
                $subscription->pending_plan_effective_at = null;
                $subscription->status = 'active';
                $subscription->last_proration_amount_cents = 0;
                $subscription->current_period_start_at = $now;
                $subscription->current_period_end_at = $now->addDays(max(1, (int) $targetPlan->billing_cycle_days));
                $dirty = true;

                $this->logPlanChange(
                    tenantId: $subscription->tenant_id,
                    actor: $actor,
                    fromPlanId: $fromPlanId,
                    toPlanId: $targetPlan->id,
                    changeType: 'downgrade_applied',
                    effectiveAt: $now,
                );
            }
        }

        if (
            $subscription->cancel_at_period_end
            && $subscription->current_period_end_at !== null
            && $now->greaterThanOrEqualTo(CarbonImmutable::parse((string) $subscription->current_period_end_at))
            && $subscription->status !== 'canceled'
        ) {
            $subscription->status = 'canceled';
            $subscription->cancel_at_period_end = false;
            $dirty = true;

            $this->logPlanChange(
                tenantId: $subscription->tenant_id,
                actor: $actor,
                fromPlanId: $subscription->plan_id,
                toPlanId: $subscription->plan_id,
                changeType: 'canceled',
                effectiveAt: $now,
                metadata: [
                    'billing_rule' => 'access_until_period_end_no_refund',
                ],
            );
        }

        if (
            $subscription->status !== 'canceled'
            && $subscription->current_period_end_at !== null
            && $now->greaterThanOrEqualTo(CarbonImmutable::parse((string) $subscription->current_period_end_at))
        ) {
            $cycleDays = max(1, (int) ($subscription->plan?->billing_cycle_days ?? 30));
            $periodStart = CarbonImmutable::parse((string) $subscription->current_period_end_at);
            $periodEnd = $periodStart->addDays($cycleDays);

            while ($now->greaterThanOrEqualTo($periodEnd)) {
                $periodStart = $periodEnd;
                $periodEnd = $periodStart->addDays($cycleDays);
            }

            $subscription->current_period_start_at = $periodStart;
            $subscription->current_period_end_at = $periodEnd;
            $dirty = true;
        }

        if ($dirty) {
            $subscription->save();
        }

        return $subscription->fresh(['plan', 'pendingPlan']);
    }

    public function dashboard(Tenant $tenant, ?User $actor = null): array
    {
        $subscription = $this->ensureSubscription($tenant, $actor);

        $plans = Plan::query()
            ->where('status', 'active')
            ->orderBy('price_cents')
            ->get();

        $memberCount = $tenant->members()->count();
        $maxUsers = $subscription->plan?->max_users;
        $remainingSlots = $maxUsers === null ? null : max(0, (int) $maxUsers - $memberCount);
        $customerCount = Entity::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('type', ['customer', 'both'])
            ->count();
        $maxCustomers = $subscription->plan?->max_customers;
        $remainingCustomerSlots = $maxCustomers === null
            ? null
            : max(0, (int) $maxCustomers - $customerCount);
        $storageLimitGb = $subscription->plan?->storage_limit_gb !== null
            ? (float) $subscription->plan->storage_limit_gb
            : null;
        $storageUsedBytes = $this->storageUsageBytes($tenant);
        $storageUsedGb = $this->bytesToGb($storageUsedBytes);
        $storageLimitBytes = $storageLimitGb === null
            ? null
            : (int) round($storageLimitGb * 1024 * 1024 * 1024);
        $remainingStorageGb = $storageLimitGb === null
            ? null
            : max(0.0, round($storageLimitGb - $storageUsedGb, 2));

        $logs = PlanChangeLog::query()
            ->where('tenant_id', $tenant->id)
            ->with(['user:id,name,email', 'fromPlan:id,name,code', 'toPlan:id,name,code'])
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return [
            'subscription' => [
                'status' => $subscription->status,
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'current_period_start_at' => $subscription->current_period_start_at?->toIso8601String(),
                'current_period_end_at' => $subscription->current_period_end_at?->toIso8601String(),
                'cancel_at_period_end' => (bool) $subscription->cancel_at_period_end,
                'pending_plan' => $subscription->pendingPlan?->only(['id', 'code', 'name']),
                'pending_plan_effective_at' => $subscription->pending_plan_effective_at?->toIso8601String(),
                'last_proration_amount_cents' => (int) $subscription->last_proration_amount_cents,
                'plan' => $subscription->plan !== null
                    ? $this->planPayload($subscription->plan)
                    : null,
            ],
            'plans' => $plans->map(fn (Plan $plan): array => $this->planPayload($plan))->values()->all(),
            'usage' => [
                'current_users' => $memberCount,
                'max_users' => $maxUsers === null ? null : (int) $maxUsers,
                'remaining_slots' => $remainingSlots,
                'is_limit_reached' => $maxUsers !== null && $memberCount >= (int) $maxUsers,
                'current_customers' => $customerCount,
                'max_customers' => $maxCustomers === null ? null : (int) $maxCustomers,
                'remaining_customer_slots' => $remainingCustomerSlots,
                'is_customer_limit_reached' => $maxCustomers !== null && $customerCount >= (int) $maxCustomers,
                'storage_used_bytes' => $storageUsedBytes,
                'storage_used_gb' => $storageUsedGb,
                'storage_limit_gb' => $storageLimitGb,
                'storage_remaining_gb' => $remainingStorageGb,
                'is_storage_limit_reached' => $storageLimitBytes !== null && $storageUsedBytes >= $storageLimitBytes,
            ],
            'notifications' => $this->trialNotifications($subscription),
            'feature_access' => [
                'premium_reports' => $this->isFeatureEnabledForSubscription($subscription, 'premium_reports'),
                'priority_support' => $this->isFeatureEnabledForSubscription($subscription, 'priority_support'),
            ],
            'audit_logs' => $logs->map(fn (PlanChangeLog $log): array => [
                'id' => $log->id,
                'change_type' => $log->change_type,
                'effective_at' => $log->effective_at?->toIso8601String(),
                'proration_amount_cents' => (int) $log->proration_amount_cents,
                'actor' => $log->user?->only(['id', 'name', 'email']),
                'from_plan' => $log->fromPlan?->only(['id', 'code', 'name']),
                'to_plan' => $log->toPlan?->only(['id', 'code', 'name']),
                'metadata' => $log->metadata ?? [],
            ])->values()->all(),
        ];
    }

    public function changePlan(Tenant $tenant, Plan $targetPlan, User $actor): TenantSubscription
    {
        return DB::transaction(function () use ($tenant, $targetPlan, $actor): TenantSubscription {
            $subscription = $this->ensureSubscription($tenant, $actor);
            $subscription->loadMissing('plan');

            $currentPlan = $subscription->plan;
            if ($currentPlan === null || $currentPlan->id === $targetPlan->id) {
                return $subscription;
            }

            $now = CarbonImmutable::now();
            $currentPrice = (int) $currentPlan->price_cents;
            $targetPrice = (int) $targetPlan->price_cents;

            if ($targetPrice > $currentPrice) {
                $proration = $this->calculateProrationAmountCents($subscription, $currentPlan, $targetPlan, $now);
                $wasCanceled = $subscription->status === 'canceled';

                $subscription->plan_id = $targetPlan->id;
                $subscription->pending_plan_id = null;
                $subscription->pending_plan_effective_at = null;
                $subscription->cancel_at_period_end = false;
                $subscription->canceled_at = null;
                $subscription->last_proration_amount_cents = $proration;

                if ($targetPrice > 0) {
                    $subscription->status = 'active';
                    $subscription->trial_ends_at = null;
                }

                if ($wasCanceled) {
                    $subscription->status = 'active';
                    $subscription->current_period_start_at = $now;
                    $subscription->current_period_end_at = $now->addDays(max(1, (int) $targetPlan->billing_cycle_days));
                }

                $subscription->save();

                $this->logPlanChange(
                    tenantId: $tenant->id,
                    actor: $actor,
                    fromPlanId: $currentPlan->id,
                    toPlanId: $targetPlan->id,
                    changeType: 'upgrade',
                    effectiveAt: $now,
                    prorationAmountCents: $proration,
                );
            } else {
                $effectiveAt = $subscription->current_period_end_at !== null
                    ? CarbonImmutable::parse((string) $subscription->current_period_end_at)
                    : $now;

                $subscription->pending_plan_id = $targetPlan->id;
                $subscription->pending_plan_effective_at = $effectiveAt;
                $subscription->last_proration_amount_cents = 0;
                $subscription->save();

                $this->logPlanChange(
                    tenantId: $tenant->id,
                    actor: $actor,
                    fromPlanId: $currentPlan->id,
                    toPlanId: $targetPlan->id,
                    changeType: 'downgrade_scheduled',
                    effectiveAt: $effectiveAt,
                );
            }

            return $subscription->fresh(['plan', 'pendingPlan']);
        });
    }

    public function cancelAtPeriodEnd(Tenant $tenant, User $actor): TenantSubscription
    {
        return DB::transaction(function () use ($tenant, $actor): TenantSubscription {
            $subscription = $this->ensureSubscription($tenant, $actor);
            $subscription = TenantSubscription::query()
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();
            $now = CarbonImmutable::now();

            if (! $subscription->cancel_at_period_end) {
                $subscription->cancel_at_period_end = true;
                $subscription->canceled_at = $now;
                $subscription->save();

                $this->logPlanChange(
                    tenantId: $tenant->id,
                    actor: $actor,
                    fromPlanId: $subscription->plan_id,
                    toPlanId: $subscription->plan_id,
                    changeType: 'cancel_scheduled',
                    effectiveAt: $subscription->current_period_end_at !== null
                        ? CarbonImmutable::parse((string) $subscription->current_period_end_at)
                        : $now,
                    metadata: [
                        'billing_rule' => 'access_until_period_end_no_refund',
                    ],
                );
            }

            return $subscription->fresh(['plan', 'pendingPlan']);
        });
    }

    public function resume(Tenant $tenant, User $actor): TenantSubscription
    {
        return DB::transaction(function () use ($tenant, $actor): TenantSubscription {
            $subscription = $this->ensureSubscription($tenant, $actor);
            $subscription = TenantSubscription::query()
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();
            $subscription->loadMissing('plan');
            $now = CarbonImmutable::now();

            if ($subscription->status === 'canceled') {
                $cycleDays = max(1, (int) ($subscription->plan?->billing_cycle_days ?? 30));
                $subscription->status = 'active';
                $subscription->current_period_start_at = $now;
                $subscription->current_period_end_at = $now->addDays($cycleDays);
            }

            if ($subscription->cancel_at_period_end || $subscription->canceled_at !== null) {
                $subscription->cancel_at_period_end = false;
                $subscription->canceled_at = null;
                $subscription->save();

                $this->logPlanChange(
                    tenantId: $tenant->id,
                    actor: $actor,
                    fromPlanId: $subscription->plan_id,
                    toPlanId: $subscription->plan_id,
                    changeType: 'resume',
                    effectiveAt: $now,
                );
            }

            return $subscription->fresh(['plan', 'pendingPlan']);
        });
    }

    public function hasFeature(Tenant $tenant, string $feature, ?User $actor = null): bool
    {
        $subscription = $this->ensureSubscription($tenant, $actor);

        return $this->isFeatureEnabledForSubscription($subscription, $feature);
    }

    /**
     * @return array<int, array{code: string, severity: string, days_left: int}>
     */
    private function trialNotifications(TenantSubscription $subscription): array
    {
        if ($subscription->status !== 'trialing' || $subscription->trial_ends_at === null) {
            return [];
        }

        $now = CarbonImmutable::now();
        $trialEnd = CarbonImmutable::parse((string) $subscription->trial_ends_at);
        $daysLeft = (int) $now->diffInDays($trialEnd, false);

        if ($daysLeft > 7) {
            return [];
        }

        if ($daysLeft <= 0) {
            return [[
                'code' => 'trial_ended',
                'severity' => 'critical',
                'days_left' => 0,
            ]];
        }

        if ($daysLeft <= 1) {
            return [[
                'code' => 'trial_ending_today',
                'severity' => 'critical',
                'days_left' => $daysLeft,
            ]];
        }

        if ($daysLeft <= 3) {
            return [[
                'code' => 'trial_ending_very_soon',
                'severity' => 'warning',
                'days_left' => $daysLeft,
            ]];
        }

        return [[
            'code' => 'trial_ending_soon',
            'severity' => 'info',
            'days_left' => $daysLeft,
        ]];
    }

    private function isFeatureEnabledForSubscription(TenantSubscription $subscription, string $feature): bool
    {
        $now = CarbonImmutable::now();
        $periodEnd = $subscription->current_period_end_at !== null
            ? CarbonImmutable::parse((string) $subscription->current_period_end_at)
            : null;

        if ($subscription->status === 'canceled' && ($periodEnd === null || $now->greaterThanOrEqualTo($periodEnd))) {
            return false;
        }

        $features = collect($subscription->plan?->features ?? [])
            ->map(fn ($item): string => (string) $item)
            ->filter()
            ->values();

        return $features->contains($feature);
    }

    private function calculateProrationAmountCents(
        TenantSubscription $subscription,
        Plan $currentPlan,
        Plan $targetPlan,
        CarbonImmutable $at
    ): int {
        if ($subscription->current_period_start_at === null || $subscription->current_period_end_at === null) {
            return 0;
        }

        $periodStart = CarbonImmutable::parse((string) $subscription->current_period_start_at);
        $periodEnd = CarbonImmutable::parse((string) $subscription->current_period_end_at);
        $periodSeconds = max(1, $periodStart->diffInSeconds($periodEnd, false));

        if ($at->greaterThanOrEqualTo($periodEnd)) {
            return 0;
        }

        $remainingSeconds = max(0, $at->diffInSeconds($periodEnd, false));
        $priceDelta = max(0, (int) $targetPlan->price_cents - (int) $currentPlan->price_cents);

        if ($priceDelta === 0 || $remainingSeconds === 0) {
            return 0;
        }

        return (int) round(($priceDelta * $remainingSeconds) / $periodSeconds);
    }

    private function storageUsageBytes(Tenant $tenant): int
    {
        $paths = [];

        $companyLogoPath = CompanySetting::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->value('logo_path');
        if (is_string($companyLogoPath) && trim($companyLogoPath) !== '') {
            $paths[] = trim($companyLogoPath);
        }

        $itemPhotoPaths = Item::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('photo_path')
            ->pluck('photo_path')
            ->all();

        foreach ($itemPhotoPaths as $path) {
            if (is_string($path) && trim($path) !== '') {
                $paths[] = trim($path);
            }
        }

        $invoiceFiles = SupplierInvoice::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->get(['document_path', 'payment_proof_path']);

        foreach ($invoiceFiles as $invoiceFile) {
            foreach (['document_path', 'payment_proof_path'] as $column) {
                $path = $invoiceFile->{$column};
                if (is_string($path) && trim($path) !== '') {
                    $paths[] = trim($path);
                }
            }
        }

        $paths = array_values(array_unique($paths));
        $totalBytes = 0;

        foreach ($paths as $path) {
            try {
                if (Storage::disk('local')->exists($path)) {
                    $totalBytes += max(0, (int) Storage::disk('local')->size($path));
                }
            } catch (\Throwable) {
                // Ignore broken references and continue with available files.
            }
        }

        return $totalBytes;
    }

    private function bytesToGb(int $bytes): float
    {
        if ($bytes <= 0) {
            return 0.0;
        }

        return round($bytes / (1024 * 1024 * 1024), 2);
    }

    /**
     * @return array{
     *   id: int,
     *   code: string,
     *   name: string,
     *   description: string|null,
     *   price_cents: int,
     *   billing_cycle_days: int,
     *   max_users: int|null,
     *   max_customers: int|null,
     *   storage_limit_gb: float|null,
     *   trial_days: int,
     *   features: array<int, string>
     * }
     */
    private function planPayload(Plan $plan): array
    {
        return [
            'id' => (int) $plan->id,
            'code' => (string) $plan->code,
            'name' => (string) $plan->name,
            'description' => $plan->description,
            'price_cents' => (int) $plan->price_cents,
            'billing_cycle_days' => (int) $plan->billing_cycle_days,
            'max_users' => $plan->max_users === null ? null : (int) $plan->max_users,
            'max_customers' => $plan->max_customers === null ? null : (int) $plan->max_customers,
            'storage_limit_gb' => $plan->storage_limit_gb === null ? null : (float) $plan->storage_limit_gb,
            'trial_days' => (int) $plan->trial_days,
            'features' => collect($plan->features ?? [])
                ->map(fn ($feature): string => (string) $feature)
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function logPlanChange(
        int $tenantId,
        ?User $actor,
        ?int $fromPlanId,
        ?int $toPlanId,
        string $changeType,
        CarbonImmutable $effectiveAt,
        int $prorationAmountCents = 0,
        array $metadata = []
    ): void {
        $now = CarbonImmutable::now();
        $duplicateExists = PlanChangeLog::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $actor?->id)
            ->where('from_plan_id', $fromPlanId)
            ->where('to_plan_id', $toPlanId)
            ->where('change_type', $changeType)
            ->where('effective_at', $effectiveAt)
            ->where('proration_amount_cents', $prorationAmountCents)
            ->where('created_at', '>=', $now->subSeconds(10))
            ->exists();

        if ($duplicateExists) {
            return;
        }

        PlanChangeLog::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $actor?->id,
            'from_plan_id' => $fromPlanId,
            'to_plan_id' => $toPlanId,
            'change_type' => $changeType,
            'effective_at' => $effectiveAt,
            'proration_amount_cents' => $prorationAmountCents,
            'metadata' => $metadata,
        ]);
    }
}
