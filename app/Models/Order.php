<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'number',
        'proposal_id',
        'order_date',
        'valid_until',
        'customer_id',
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
            'valid_until' => 'date',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if (! empty($order->number)) {
                return;
            }

            DB::transaction(function () use ($order): void {
                $maxNumber = static::query()
                    ->withTrashed()
                    ->lockForUpdate()
                    ->max('number');

                $order->number = ((int) $maxNumber) + 1;
            });
        });
    }

    /**
     * @return BelongsTo<Proposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'customer_id');
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /**
     * @return HasMany<SupplierOrder, $this>
     */
    public function supplierOrders(): HasMany
    {
        return $this->hasMany(SupplierOrder::class);
    }
}
