<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiSalesSuggestion extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DEFERRED = 'deferred';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'deal_id',
        'contact_id',
        'source_type',
        'action_type',
        'title',
        'reason',
        'next_step',
        'status',
        'priority_score',
        'suggested_for_at',
        'deferred_until',
        'fingerprint',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority_score' => 'integer',
            'suggested_for_at' => 'datetime',
            'deferred_until' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return HasMany<AiSalesSuggestionFeedback, $this>
     */
    public function feedback(): HasMany
    {
        return $this->hasMany(AiSalesSuggestionFeedback::class);
    }
}
