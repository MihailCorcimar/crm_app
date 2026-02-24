<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Proposal extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'number',
        'proposal_date',
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
            'proposal_date' => 'date',
            'valid_until' => 'date',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Proposal $proposal): void {
            if (! empty($proposal->number)) {
                return;
            }

            DB::transaction(function () use ($proposal): void {
                $maxNumber = static::query()
                    ->withTrashed()
                    ->lockForUpdate()
                    ->max('number');

                $proposal->number = ((int) $maxNumber) + 1;
            });
        });
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'customer_id');
    }

    /**
     * @return HasMany<ProposalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ProposalLine::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
