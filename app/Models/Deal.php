<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
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
