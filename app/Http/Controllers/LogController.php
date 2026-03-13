<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ActivityLog::query()->with('user:id,name');
        $selectedMenu = $request->query('menu', '');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if (is_string($selectedMenu) && trim($selectedMenu) !== '') {
            $menuKey = ActivityLog::canonicalMenuKey($selectedMenu);
            $menuValues = ActivityLog::menuFilterValues($menuKey);

            $query->where(function ($subQuery) use ($menuValues): void {
                foreach ($menuValues as $index => $menuValue) {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $subQuery->{$method}('LOWER(menu) = ?', [mb_strtolower($menuValue)]);
                }
            });
        }

        $logs = $query
            ->orderByDesc('occurred_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ActivityLog $activityLog): array => [
                'id' => $activityLog->id,
                'date' => $activityLog->occurred_at?->format('Y-m-d'),
                'time' => $activityLog->occurred_at?->format('H:i:s'),
                'user' => $activityLog->user?->name ?? '-',
                'menu' => ActivityLog::menuLabel($activityLog->menu),
                'action' => $activityLog->action,
                'device' => $activityLog->device ?? '-',
                'ip_address' => $activityLog->ip_address ?? '-',
            ]);
        $menuOptions = ActivityLog::menuOptions();

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->all();

        return Inertia::render('logs/Index', [
            'logs' => $logs,
            'filters' => [
                'user_id' => $request->query('user_id', ''),
                'menu' => is_string($selectedMenu) && trim($selectedMenu) !== ''
                    ? ActivityLog::canonicalMenuKey($selectedMenu)
                    : '',
            ],
            'menuOptions' => $menuOptions,
            'users' => $users,
        ]);
    }
}
