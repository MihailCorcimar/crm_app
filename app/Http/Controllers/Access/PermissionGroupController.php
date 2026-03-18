<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\PermissionGroupRequest;
use App\Models\PermissionGroup;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PermissionGroupController extends Controller
{
    public function __construct()
    {
        $this->middleware('module.permission:access,read')->only(['index']);
        $this->middleware('module.permission:access,create')->only(['create', 'store']);
        $this->middleware('module.permission:access,update')->only(['edit', 'update']);
        $this->middleware('module.permission:access,delete')->only(['destroy']);
    }

    public function index(): Response
    {
        $permissionGroups = PermissionGroup::query()
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (PermissionGroup $permissionGroup): array => $this->groupPayload($permissionGroup, true))
            ->all();

        return Inertia::render('access/permission-groups/Index', [
            'permissionGroups' => $permissionGroups,
            'permissionModules' => PermissionGroup::MODULES,
            'permissionActions' => PermissionGroup::ACTIONS,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('access/permission-groups/Create', [
            'permissionModules' => PermissionGroup::MODULES,
            'permissionActions' => PermissionGroup::ACTIONS,
        ]);
    }

    public function store(PermissionGroupRequest $request): RedirectResponse
    {
        PermissionGroup::query()->create($request->validated());

        return to_route('access.permission-groups.index');
    }

    public function edit(PermissionGroup $permissionGroup): Response
    {
        return Inertia::render('access/permission-groups/Edit', [
            'permissionGroup' => $this->groupPayload($permissionGroup),
            'permissionModules' => PermissionGroup::MODULES,
            'permissionActions' => PermissionGroup::ACTIONS,
        ]);
    }

    public function update(PermissionGroupRequest $request, PermissionGroup $permissionGroup): RedirectResponse
    {
        $permissionGroup->update($request->validated());

        return to_route('access.permission-groups.index');
    }

    public function destroy(PermissionGroup $permissionGroup): RedirectResponse
    {
        if ($permissionGroup->users()->exists()) {
            return to_route('access.permission-groups.index')
                ->withErrors(['permissionGroup' => 'Nao podes eliminar um grupo associado a utilizadores.']);
        }

        $permissionGroup->delete();

        return to_route('access.permission-groups.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function groupPayload(PermissionGroup $permissionGroup, bool $withUsersCount = false): array
    {
        $payload = [
            'id' => $permissionGroup->id,
            'name' => $permissionGroup->name,
            'status' => $permissionGroup->status,
        ];

        foreach (PermissionGroup::permissionColumns() as $column) {
            $payload[$column] = (bool) $permissionGroup->getAttribute($column);
        }

        if ($withUsersCount) {
            $payload['users_count'] = (int) ($permissionGroup->users_count ?? 0);
        }

        return $payload;
    }
}
