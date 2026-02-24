<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'event_date',
        'event_time',
        'duration_minutes',
        'share',
        'knowledge',
        'entity_id',
        'user_id',
        'calendar_type_id',
        'calendar_action_id',
        'description',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'duration_minutes' => 'integer',
        ];
    }

    public function startAt(): CarbonImmutable
    {
        return CarbonImmutable::parse(sprintf(
            '%s %s',
            $this->event_date?->format('Y-m-d'),
            $this->event_time
        ));
    }

    public function endAt(): CarbonImmutable
    {
        return $this->startAt()->addMinutes($this->duration_minutes);
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
