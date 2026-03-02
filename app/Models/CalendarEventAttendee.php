<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CalendarEventAttendee extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'calendar_event_id',
        'attendee_type',
        'attendee_id',
    ];

    /**
     * @return BelongsTo<CalendarEvent, $this>
     */
    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attendee(): MorphTo
    {
        return $this->morphTo();
    }
}
