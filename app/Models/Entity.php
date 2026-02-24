<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Entity extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'type',
        'number',
        'tax_id',
        'name',
        'address',
        'postal_code',
        'city',
        'country_id',
        'phone',
        'mobile',
        'website',
        'email',
        'gdpr_consent',
        'notes',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gdpr_consent' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Entity $entity): void {
            if (! empty($entity->number)) {
                return;
            }

            // Simple concurrency-safe approach for common workloads.
            DB::transaction(function () use ($entity): void {
                $maxNumber = static::query()
                    ->withTrashed()
                    ->lockForUpdate()
                    ->max('number');

                $entity->number = ((int) $maxNumber) + 1;
            });
        });
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * @return HasMany<Proposal, $this>
     */
    public function proposalsAsCustomer(): HasMany
    {
        return $this->hasMany(Proposal::class, 'customer_id');
    }

    /**
     * @return HasMany<ProposalLine, $this>
     */
    public function proposalLinesAsSupplier(): HasMany
    {
        return $this->hasMany(ProposalLine::class, 'supplier_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function ordersAsCustomer(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    /**
     * @return HasMany<SupplierOrder, $this>
     */
    public function supplierOrders(): HasMany
    {
        return $this->hasMany(SupplierOrder::class, 'supplier_id');
    }

    /**
     * @return HasMany<SupplierInvoice, $this>
     */
    public function supplierInvoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class, 'supplier_id');
    }

    /**
     * @return HasMany<CalendarEvent, $this>
     */
    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }
}
