<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class SupplierOrder extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'number',
        'order_id',
        'supplier_id',
        'order_date',
        'total',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SupplierOrder $supplierOrder): void {
            if (! empty($supplierOrder->number)) {
                return;
            }

            DB::transaction(function () use ($supplierOrder): void {
                $maxNumber = static::query()
                    ->withTrashed()
                    ->lockForUpdate()
                    ->max('number');

                $supplierOrder->number = ((int) $maxNumber) + 1;
            });
        });
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'supplier_id');
    }

    /**
     * @return HasMany<SupplierOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SupplierOrderLine::class);
    }

    /**
     * @return HasMany<SupplierInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }
}
