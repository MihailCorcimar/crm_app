<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactRole extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'contact_roles';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
    ];

    /**
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'role_id');
    }
}
