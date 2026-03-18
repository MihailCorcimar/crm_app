<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\UserManagementRequest;
use App\Models\PermissionGroup;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
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
        $users = User::query()
            ->with('permissionGroup:id,name')
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'permission_group' => $user->permissionGroup?->name,
                'status' => $user->status,
            ]);

        return Inertia::render('access/users/Index', [
            'users' => $users,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('access/users/Create', [
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function store(UserManagementRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile' => $validated['mobile'] ?? null,
            'permission_group_id' => $validated['permission_group_id'] ?? null,
            'status' => $validated['status'],
            // Temporary password; user can change it through the normal auth flows.
            'password' => Str::random(24),
        ]);

        return to_route('access.users.index');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('access/users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'permission_group_id' => $user->permission_group_id,
                'status' => $user->status,
            ],
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function update(UserManagementRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile' => $validated['mobile'] ?? null,
            'permission_group_id' => $validated['permission_group_id'] ?? null,
            'status' => $validated['status'],
        ];

        $user->update($payload);

        return to_route('access.users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->is(auth()->user())) {
            return to_route('access.users.index')
                ->withErrors(['user' => 'Nao podes eliminar o teu proprio utilizador.']);
        }

        $user->delete();

        return to_route('access.users.index');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function permissionGroups(): array
    {
        return PermissionGroup::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (PermissionGroup $permissionGroup): array => [
                'id' => $permissionGroup->id,
                'name' => $permissionGroup->name,
            ])
            ->all();
    }
}
