<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'owner_user_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'can_create_tenants'])
            ->withTimestamps();
    }

    /**
     * @return HasOne<TenantSetting, $this>
     */
    public function setting(): HasOne
    {
        return $this->hasOne(TenantSetting::class);
    }

    /**
     * @return HasOne<TenantSubscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class);
    }

    /**
     * @return HasMany<PlanChangeLog, $this>
     */
    public function planChangeLogs(): HasMany
    {
        return $this->hasMany(PlanChangeLog::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isOwner(User $user): bool
    {
        return $this->owner_user_id === $user->id;
    }
}
