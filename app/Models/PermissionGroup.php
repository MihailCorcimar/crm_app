<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermissionGroup extends Model
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    public const MODULES = [
        'entities' => 'Entidades',
        'people' => 'Pessoas',
        'deals' => 'Negócios',
        'calendar' => 'Calendário',
        'products' => 'Produtos',
        'forms' => 'Formulários',
        'automations' => 'Automações',
        'chat' => 'Chat IA',
        'access' => 'Gestão de acessos',
        'tenants' => 'Tenants',
        'settings' => 'Configurações',
        'logs' => 'Logs',
    ];

    /**
     * @var array<string, string>
     */
    public const ACTIONS = [
        'create' => 'Criar',
        'read' => 'Ler',
        'update' => 'Editar',
        'delete' => 'Eliminar',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'status',
        ...self::PERMISSION_COLUMNS,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...self::PERMISSION_CASTS,
        ];
    }

    /**
     * @var list<string>
     */
    private const PERMISSION_COLUMNS = [
        'entities_create', 'entities_read', 'entities_update', 'entities_delete',
        'people_create', 'people_read', 'people_update', 'people_delete',
        'deals_create', 'deals_read', 'deals_update', 'deals_delete',
        'calendar_create', 'calendar_read', 'calendar_update', 'calendar_delete',
        'products_create', 'products_read', 'products_update', 'products_delete',
        'forms_create', 'forms_read', 'forms_update', 'forms_delete',
        'automations_create', 'automations_read', 'automations_update', 'automations_delete',
        'chat_create', 'chat_read', 'chat_update', 'chat_delete',
        'access_create', 'access_read', 'access_update', 'access_delete',
        'tenants_create', 'tenants_read', 'tenants_update', 'tenants_delete',
        'settings_create', 'settings_read', 'settings_update', 'settings_delete',
        'logs_create', 'logs_read', 'logs_update', 'logs_delete',
    ];

    /**
     * @var array<string, string>
     */
    private const PERMISSION_CASTS = [
        'entities_create' => 'boolean', 'entities_read' => 'boolean', 'entities_update' => 'boolean', 'entities_delete' => 'boolean',
        'people_create' => 'boolean', 'people_read' => 'boolean', 'people_update' => 'boolean', 'people_delete' => 'boolean',
        'deals_create' => 'boolean', 'deals_read' => 'boolean', 'deals_update' => 'boolean', 'deals_delete' => 'boolean',
        'calendar_create' => 'boolean', 'calendar_read' => 'boolean', 'calendar_update' => 'boolean', 'calendar_delete' => 'boolean',
        'products_create' => 'boolean', 'products_read' => 'boolean', 'products_update' => 'boolean', 'products_delete' => 'boolean',
        'forms_create' => 'boolean', 'forms_read' => 'boolean', 'forms_update' => 'boolean', 'forms_delete' => 'boolean',
        'automations_create' => 'boolean', 'automations_read' => 'boolean', 'automations_update' => 'boolean', 'automations_delete' => 'boolean',
        'chat_create' => 'boolean', 'chat_read' => 'boolean', 'chat_update' => 'boolean', 'chat_delete' => 'boolean',
        'access_create' => 'boolean', 'access_read' => 'boolean', 'access_update' => 'boolean', 'access_delete' => 'boolean',
        'tenants_create' => 'boolean', 'tenants_read' => 'boolean', 'tenants_update' => 'boolean', 'tenants_delete' => 'boolean',
        'settings_create' => 'boolean', 'settings_read' => 'boolean', 'settings_update' => 'boolean', 'settings_delete' => 'boolean',
        'logs_create' => 'boolean', 'logs_read' => 'boolean', 'logs_update' => 'boolean', 'logs_delete' => 'boolean',
    ];

    /**
     * @return list<string>
     */
    public static function permissionColumns(): array
    {
        return self::PERMISSION_COLUMNS;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function permissionsMatrix(): array
    {
        $matrix = [];

        foreach (self::MODULES as $module => $_label) {
            $matrix[$module] = [];

            foreach (self::ACTIONS as $action => $_actionLabel) {
                $matrix[$module][$action] = (bool) $this->getAttribute("{$module}_{$action}");
            }
        }

        return $matrix;
    }

    public function allows(string $module, string $action): bool
    {
        if (! array_key_exists($module, self::MODULES) || ! array_key_exists($action, self::ACTIONS)) {
            return false;
        }

        if ($this->status !== 'active') {
            return false;
        }

        return (bool) $this->getAttribute("{$module}_{$action}");
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}


