<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\Entity;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $contacts = Contact::query()
            ->with(['entity:id,name', 'role:id,name'])
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Contact $contact): array => [
                'id' => $contact->id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'role' => $contact->role?->name,
                'entity' => $contact->entity?->name,
                'phone' => $contact->phone,
                'mobile' => $contact->mobile,
                'email' => $contact->email,
            ]);

        return Inertia::render('contacts/Index', [
            'contacts' => $contacts,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('contacts/Create', [
            'entities' => $this->entities(),
            'roles' => $this->roles(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ContactRequest $request): RedirectResponse
    {
        Contact::query()->create($this->payload($request->validated()));

        return to_route('contacts.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Contact $contact): Response
    {
        $contact->load(['entity:id,name', 'role:id,name']);

        return Inertia::render('contacts/Show', [
            'contact' => [
                'id' => $contact->id,
                'number' => $contact->number,
                'entity' => $contact->entity?->name,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'role' => $contact->role?->name,
                'phone' => $contact->phone,
                'mobile' => $contact->mobile,
                'email' => $contact->email,
                'gdpr_consent' => $contact->gdpr_consent,
                'notes' => $contact->notes,
                'status' => $contact->status,
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Contact $contact): Response
    {
        return Inertia::render('contacts/Edit', [
            'contact' => [
                'id' => $contact->id,
                'number' => $contact->number,
                'entity_id' => $contact->entity_id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'role_id' => $contact->role_id,
                'phone' => $contact->phone,
                'mobile' => $contact->mobile,
                'email' => $contact->email,
                'gdpr_consent' => $contact->gdpr_consent,
                'notes' => $contact->notes,
                'status' => $contact->status,
            ],
            'entities' => $this->entities(),
            'roles' => $this->roles(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ContactRequest $request, Contact $contact): RedirectResponse
    {
        $contact->update($this->payload($request->validated()));

        return to_route('contacts.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        return to_route('contacts.index');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated): array
    {
        return [
            'entity_id' => $validated['entity_id'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'] ?? null,
            'role_id' => $validated['role_id'],
            'phone' => $validated['phone'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'email' => $validated['email'] ?? null,
            'gdpr_consent' => (bool) ($validated['gdpr_consent'] ?? false),
            'notes' => $validated['notes'] ?? null,
            'status' => $validated['status'],
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function entities(): array
    {
        return Entity::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Entity $entity): array => [
                'id' => $entity->id,
                'name' => $entity->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function roles(): array
    {
        return ContactRole::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ContactRole $role): array => [
                'id' => $role->id,
                'name' => $role->name,
            ])
            ->all();
    }
}
