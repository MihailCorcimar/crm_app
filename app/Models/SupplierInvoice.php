<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class SupplierInvoice extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'number',
        'invoice_date',
        'due_date',
        'supplier_id',
        'supplier_order_id',
        'total',
        'document_path',
        'payment_proof_path',
        'status',
        'proof_emailed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'total' => 'decimal:2',
            'proof_emailed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SupplierInvoice $supplierInvoice): void {
            if (! empty($supplierInvoice->number)) {
                return;
            }

            DB::transaction(function () use ($supplierInvoice): void {
                $maxNumber = static::query()
                    ->withTrashed()
                    ->lockForUpdate()
                    ->max('number');

                $supplierInvoice->number = ((int) $maxNumber) + 1;
            });
        });
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'supplier_id');
    }

    /**
     * @return BelongsTo<SupplierOrder, $this>
     */
    public function supplierOrder(): BelongsTo
    {
        return $this->belongsTo(SupplierOrder::class);
    }
}
