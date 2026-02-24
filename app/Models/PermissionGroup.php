<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermissionGroup extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'menu_a_create',
        'menu_a_read',
        'menu_a_update',
        'menu_a_delete',
        'menu_b_create',
        'menu_b_read',
        'menu_b_update',
        'menu_b_delete',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'menu_a_create' => 'boolean',
            'menu_a_read' => 'boolean',
            'menu_a_update' => 'boolean',
            'menu_a_delete' => 'boolean',
            'menu_b_create' => 'boolean',
            'menu_b_read' => 'boolean',
            'menu_b_update' => 'boolean',
            'menu_b_delete' => 'boolean',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
