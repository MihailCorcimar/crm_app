<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'price_cents',
        'billing_cycle_days',
        'max_users',
        'max_customers',
        'storage_limit_gb',
        'trial_days',
        'features',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'price_cents' => 'integer',
            'billing_cycle_days' => 'integer',
            'max_users' => 'integer',
            'max_customers' => 'integer',
            'storage_limit_gb' => 'decimal:2',
            'trial_days' => 'integer',
        ];
    }

    /**
     * @return HasMany<TenantSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    /**
     * @return HasMany<PlanChangeLog, $this>
     */
    public function fromPlanChanges(): HasMany
    {
        return $this->hasMany(PlanChangeLog::class, 'from_plan_id');
    }

    /**
     * @return HasMany<PlanChangeLog, $this>
     */
    public function toPlanChanges(): HasMany
    {
        return $this->hasMany(PlanChangeLog::class, 'to_plan_id');
    }
}
