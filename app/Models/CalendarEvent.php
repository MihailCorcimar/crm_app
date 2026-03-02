<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CalendarEvent extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'start_at',
        'end_at',
        'location',
        'owner_id',
        'eventable_type',
        'eventable_id',
        'status',
        'event_date',
        'event_time',
        'duration_minutes',
        'share',
        'knowledge',
        'user_id',
        'calendar_type_id',
        'calendar_action_id',
        'entity_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'event_date' => 'date',
            'duration_minutes' => 'integer',
        ];
    }

    public function startAt(): CarbonImmutable
    {
        if ($this->start_at !== null) {
            return CarbonImmutable::instance($this->start_at);
        }

        return CarbonImmutable::parse(sprintf(
            '%s %s',
            $this->event_date?->format('Y-m-d'),
            $this->event_time
        ));
    }

    public function endAt(): CarbonImmutable
    {
        if ($this->end_at !== null) {
            return CarbonImmutable::instance($this->end_at);
        }

        return $this->startAt()->addMinutes($this->duration_minutes);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function eventable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<CalendarEventAttendee, $this>
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(CalendarEventAttendee::class);
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CalendarType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(CalendarType::class, 'calendar_type_id');
    }

    /**
     * @return BelongsTo<CalendarAction, $this>
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(CalendarAction::class, 'calendar_action_id');
    }
}
