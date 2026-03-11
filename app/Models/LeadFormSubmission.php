<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadFormSubmission extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lead_form_id',
        'tenant_id',
        'contact_id',
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
}

