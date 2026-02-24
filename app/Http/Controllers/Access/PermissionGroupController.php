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
    public function index(): Response
    {
        $permissionGroups = PermissionGroup::query()
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (PermissionGroup $permissionGroup): array => [
                'id' => $permissionGroup->id,
                'name' => $permissionGroup->name,
                'menu_a_create' => $permissionGroup->menu_a_create,
                'menu_a_read' => $permissionGroup->menu_a_read,
                'menu_a_update' => $permissionGroup->menu_a_update,
                'menu_a_delete' => $permissionGroup->menu_a_delete,
                'menu_b_create' => $permissionGroup->menu_b_create,
                'menu_b_read' => $permissionGroup->menu_b_read,
                'menu_b_update' => $permissionGroup->menu_b_update,
                'menu_b_delete' => $permissionGroup->menu_b_delete,
                'users_count' => $permissionGroup->users_count,
                'status' => $permissionGroup->status,
            ])
            ->all();

        return Inertia::render('access/permission-groups/Index', [
            'permissionGroups' => $permissionGroups,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('access/permission-groups/Create');
    }

    public function store(PermissionGroupRequest $request): RedirectResponse
    {
        PermissionGroup::query()->create($request->validated());

        return to_route('access.permission-groups.index');
    }

    public function edit(PermissionGroup $permissionGroup): Response
    {
        return Inertia::render('access/permission-groups/Edit', [
            'permissionGroup' => [
                'id' => $permissionGroup->id,
                'name' => $permissionGroup->name,
                'menu_a_create' => $permissionGroup->menu_a_create,
                'menu_a_read' => $permissionGroup->menu_a_read,
                'menu_a_update' => $permissionGroup->menu_a_update,
                'menu_a_delete' => $permissionGroup->menu_a_delete,
                'menu_b_create' => $permissionGroup->menu_b_create,
                'menu_b_read' => $permissionGroup->menu_b_read,
                'menu_b_update' => $permissionGroup->menu_b_update,
                'menu_b_delete' => $permissionGroup->menu_b_delete,
                'status' => $permissionGroup->status,
            ],
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
}
