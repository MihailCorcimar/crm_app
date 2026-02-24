<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = TenantContext::id();

            if ($tenantId === null) {
                if (! app()->runningInConsole() || app()->runningUnitTests()) {
                    $builder->whereRaw('1 = 0');
                }

                return;
            }

            $builder->where(
                $builder->qualifyColumn('tenant_id'),
                $tenantId,
            );
        });

        static::creating(function (Model $model): void {
            if (! empty($model->tenant_id)) {
                return;
            }

            $tenantId = TenantContext::id();
            if ($tenantId !== null) {
                $model->tenant_id = $tenantId;
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
