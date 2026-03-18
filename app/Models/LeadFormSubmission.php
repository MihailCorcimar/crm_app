<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadFormSubmission extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_IGNORED = 'ignored';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lead_form_id',
        'tenant_id',
        'contact_id',
        'status',
        'entity_id',
        'deal_id',
        'converted_at',
        'converted_by',
        'ignored_at',
        'ignored_by',
        'source_type',
        'source_url',
        'source_origin',
        'ip_address',
        'user_agent',
        'captcha_passed',
        'payload',
        'submitted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'captcha_passed' => 'boolean',
            'payload' => 'array',
            'submitted_at' => 'datetime',
            'converted_at' => 'datetime',
            'ignored_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LeadForm, $this>
     */
    public function leadForm(): BelongsTo
    {
        return $this->belongsTo(LeadForm::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function convertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function ignoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ignored_by');
    }
}
