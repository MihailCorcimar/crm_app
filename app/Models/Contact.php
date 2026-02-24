<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Contact extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'number',
        'entity_id',
        'first_name',
        'last_name',
        'role_id',
        'phone',
        'mobile',
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
        static::creating(function (Contact $contact): void {
            if (! empty($contact->number)) {
                return;
            }

            // Simple concurrency-safe approach for common workloads.
            DB::transaction(function () use ($contact): void {
                $maxNumber = static::query()
                    ->withTrashed()
                    ->lockForUpdate()
                    ->max('number');

                $contact->number = ((int) $maxNumber) + 1;
            });
        });
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * @return BelongsTo<ContactRole, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(ContactRole::class, 'role_id');
    }
}
