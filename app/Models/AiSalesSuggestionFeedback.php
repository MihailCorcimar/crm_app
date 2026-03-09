<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSalesSuggestionFeedback extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $table = 'ai_sales_suggestion_feedback';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'ai_sales_suggestion_id',
        'action_type',
        'decision',
    ];

    /**
     * @return BelongsTo<AiSalesSuggestion, $this>
     */
    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(AiSalesSuggestion::class, 'ai_sales_suggestion_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
