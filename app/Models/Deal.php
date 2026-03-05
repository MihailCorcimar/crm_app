<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Deal extends Model
{
    use BelongsToTenant, HasFactory;

    public const STAGE_LEAD = 'lead';
    public const STAGE_PROPOSAL = 'proposal';
    public const STAGE_NEGOTIATION = 'negotiation';
    public const STAGE_FOLLOW_UP = 'follow_up';
    public const STAGE_WON = 'won';
    public const STAGE_LOST = 'lost';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'entity_id',
        'person_id',
        'title',
        'value',
        'stage',
        'probability',
        'expected_close_date',
        'owner_id',
        'proposal_path',
        'proposal_original_name',
        'proposal_mime_type',
        'proposal_size',
        'proposal_uploaded_at',
        'proposal_uploaded_by',
        'follow_up_active',
        'follow_up_started_at',
        'follow_up_next_send_at',
        'follow_up_last_sent_at',
        'follow_up_template_index',
        'follow_up_customer_replied_at',
        'follow_up_stopped_at',
        'follow_up_stop_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'probability' => 'integer',
            'expected_close_date' => 'date',
            'proposal_size' => 'integer',
            'proposal_uploaded_at' => 'datetime',
            'follow_up_active' => 'boolean',
            'follow_up_started_at' => 'datetime',
            'follow_up_next_send_at' => 'datetime',
            'follow_up_last_sent_at' => 'datetime',
            'follow_up_template_index' => 'integer',
            'follow_up_customer_replied_at' => 'datetime',
            'follow_up_stopped_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'person_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function proposalUploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposal_uploaded_by');
    }

    /**
     * @return HasMany<DealEmailLog, $this>
     */
    public function emailLogs(): HasMany
    {
        return $this->hasMany(DealEmailLog::class);
    }

    /**
     * @return HasMany<DealProduct, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(DealProduct::class);
    }

    /**
     * @return list<string>
     */
    public static function stages(): array
    {
        return [
            self::STAGE_LEAD,
            self::STAGE_PROPOSAL,
            self::STAGE_NEGOTIATION,
            self::STAGE_FOLLOW_UP,
            self::STAGE_WON,
            self::STAGE_LOST,
        ];
    }

    /**
     * @return MorphMany<CalendarEvent, $this>
     */
    public function linkedCalendarEvents(): MorphMany
    {
        return $this->morphMany(CalendarEvent::class, 'eventable');
    }

    /**
     * @return MorphMany<CalendarEventAttendee, $this>
     */
    public function calendarEventAttendances(): MorphMany
    {
        return $this->morphMany(CalendarEventAttendee::class, 'attendee');
    }
}
