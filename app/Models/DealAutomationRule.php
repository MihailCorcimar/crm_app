<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DealAutomationRule extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'trigger_type',
        'inactivity_days',
        'action_type',
        'activity_type',
        'activity_due_in_days',
        'activity_priority',
        'activity_title_template',
        'activity_description_template',
        'notify_internal',
        'notification_message',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inactivity_days' => 'integer',
            'activity_due_in_days' => 'integer',
            'notify_internal' => 'boolean',
        ];
    }

    /**
     * @return HasMany<DealAutomationRuleExecution, $this>
     */
    public function executions(): HasMany
    {
        return $this->hasMany(DealAutomationRuleExecution::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
