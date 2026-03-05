<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'reference',
        'code',
        'name',
        'description',
        'price',
        'vat',
        'vat_rate_id',
        'photo_path',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'vat' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<VatRate, $this>
     */
    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }

    /**
     * @return HasMany<ProposalLine, $this>
     */
    public function proposalLines(): HasMany
    {
        return $this->hasMany(ProposalLine::class);
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function orderLines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /**
     * @return HasMany<SupplierOrderLine, $this>
     */
    public function supplierOrderLines(): HasMany
    {
        return $this->hasMany(SupplierOrderLine::class);
    }

    /**
     * @return HasMany<DealProduct, $this>
     */
    public function dealProducts(): HasMany
    {
        return $this->hasMany(DealProduct::class);
    }
}
