<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealAutomationRuleExecution extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'deal_automation_rule_id',
        'deal_id',
        'owner_id',
        'calendar_event_id',
        'activity_anchor_at',
        'triggered_at',
        'status',
        'status_reason',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activity_anchor_at' => 'datetime',
            'triggered_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<DealAutomationRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(DealAutomationRule::class, 'deal_automation_rule_id');
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
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<CalendarEvent, $this>
     */
    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }
}
