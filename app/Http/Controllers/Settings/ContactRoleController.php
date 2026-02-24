<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ContactRoleRequest;
use App\Models\ContactRole;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ContactRoleController extends Controller
{
    public function index(): Response
    {
        $roles = ContactRole::query()
            ->withCount('contacts')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ContactRole $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'contacts_count' => $role->contacts_count,
            ])
            ->all();

        return Inertia::render('settings/ContactRoles', [
            'roles' => $roles,
        ]);
    }

    public function store(ContactRoleRequest $request): RedirectResponse
    {
        ContactRole::query()->create($request->validated());

        return to_route('settings.contact-roles.index');
    }

    public function update(ContactRoleRequest $request, ContactRole $contactRole): RedirectResponse
    {
        $contactRole->update($request->validated());

        return to_route('settings.contact-roles.index');
    }

    public function destroy(ContactRole $contactRole): RedirectResponse
    {
        if ($contactRole->contacts()->exists()) {
            return to_route('settings.contact-roles.index')
                ->withErrors(['contactRole' => 'Nao podes eliminar uma funcao associada a contactos.']);
        }

        $contactRole->delete();

        return to_route('settings.contact-roles.index');
    }
}
