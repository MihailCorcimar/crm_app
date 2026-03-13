<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ActivityLog extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * @var array<string, string>
     */
    public const MENU_LABELS = [
        'dashboard' => 'Dashboard',
        'home' => 'Home',
        'entities' => 'Entidades',
        'people' => 'Pessoas',
        'deals' => 'Negócios',
        'items' => 'Produtos',
        'calendar' => 'Calendário',
        'lead_forms' => 'Formulários',
        'ai' => 'Chat IA',
        'automations' => 'Automações',
        'access' => 'Gestão de Acessos',
        'tenants' => 'Tenants',
        'settings' => 'Configurações',
        'logs' => 'Logs',
        'auth' => 'Autenticação',
        'other' => 'Outros',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const MENU_ALIASES = [
        'dashboard' => ['dashboard'],
        'home' => ['home'],
        'entities' => ['entities', 'entidades'],
        'people' => ['people', 'contacts', 'pessoas'],
        'deals' => ['deals', 'negocios', 'negocios'],
        'items' => ['items', 'produtos'],
        'calendar' => ['calendar', 'calendario', 'calendario', 'calendario'],
        'lead_forms' => ['lead_forms', 'lead-forms', 'leads publicos', 'leads publicos', 'formularios', 'formularios'],
        'ai' => ['ai'],
        'automations' => ['automations', 'automacoes', 'automacoes'],
        'access' => ['access', 'gestao de acessos', 'gestao de acessos'],
        'tenants' => ['tenants'],
        'settings' => ['settings', 'configuracoes', 'configuracoes', 'configuracoes'],
        'logs' => ['logs'],
        'auth' => ['auth', 'login'],
        'other' => ['other', 'unknown', 'outros'],
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'occurred_at',
        'user_id',
        'menu',
        'action',
        'device',
        'ip_address',
        'method',
        'path',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function menuOptions(): array
    {
        return collect(self::MENU_LABELS)
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    public static function canonicalMenuKey(?string $menu): string
    {
        $normalizedInput = self::normalizeMenuToken((string) $menu);
        if ($normalizedInput === '') {
            return 'other';
        }

        foreach (self::MENU_ALIASES as $key => $aliases) {
            foreach ($aliases as $alias) {
                if ($normalizedInput === self::normalizeMenuToken($alias)) {
                    return $key;
                }
            }
        }

        return 'other';
    }

    public static function menuLabel(?string $menu): string
    {
        $key = self::canonicalMenuKey($menu);

        return self::MENU_LABELS[$key] ?? self::MENU_LABELS['other'];
    }

    /**
     * @return array<int, string>
     */
    public static function menuFilterValues(string $menuKey): array
    {
        $menuKey = self::canonicalMenuKey($menuKey);

        return collect(array_merge([$menuKey], self::MENU_ALIASES[$menuKey] ?? []))
            ->map(fn (string $value): string => trim($value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    private static function normalizeMenuToken(string $value): string
    {
        return (string) Str::of($value)
            ->lower()
            ->ascii()
            ->replace(['-', '_'], ' ')
            ->squish();
    }
}

