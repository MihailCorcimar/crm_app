<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\Entity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Contact::class, 'contact');
    }
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

        return to_route('people.index');
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
                'entity_id' => $contact->entity_id,
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
            'interaction_history' => $this->interactionHistory($contact),
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

        return to_route('people.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        return to_route('people.index');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated): array
    {
        return [
            'entity_id' => $validated['entity_id'] ?? null,
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

    /**
     * @return array<int, array{key: string, interaction_type: string, title: string, details: string, occurred_at: string}>
     */
    private function interactionHistory(Contact $contact): array
    {
        $items = collect();

        if (Schema::hasTable('deals')) {
            $items = $items->concat($this->dealInteractions($contact));
        }

        if (Schema::hasTable('calendar_events')) {
            $items = $items->concat($this->eventInteractions($contact));
        }

        return $items
            ->sortByDesc('sort_at')
            ->take(25)
            ->values()
            ->map(static fn (array $item): array => [
                'key' => $item['key'],
                'interaction_type' => $item['interaction_type'],
                'title' => $item['title'],
                'details' => $item['details'],
                'occurred_at' => $item['occurred_at'],
            ])
            ->all();
    }

    /**
     * @return Collection<int, array{key: string, interaction_type: string, title: string, details: string, occurred_at: string, sort_at: int}>
     */
    private function dealInteractions(Contact $contact): Collection
    {
        $query = DB::table('deals')
            ->where(function ($query) use ($contact): void {
                $query->where('deals.person_id', $contact->id);

                if ($contact->entity_id !== null) {
                    $query->orWhere('deals.entity_id', $contact->entity_id);
                }
            });

        if (Schema::hasColumn('deals', 'tenant_id')) {
            $query->where('deals.tenant_id', $contact->tenant_id);
        }

        $dealRows = $query
            ->orderByDesc('deals.created_at')
            ->limit(25)
            ->get([
                'deals.id',
                'deals.title',
                'deals.stage',
                'deals.value',
                'deals.probability',
                'deals.created_at',
                'deals.updated_at',
            ]);

        return $dealRows->map(static function ($deal): array {
            $timestampSource = $deal->updated_at ?? $deal->created_at ?? now()->toDateTimeString();
            $timestamp = Carbon::parse((string) $timestampSource);

            return [
                'key' => sprintf('deal-%d', (int) $deal->id),
                'interaction_type' => 'Negocio',
                'title' => (string) $deal->title,
                'details' => sprintf(
                    'Etapa: %s | Valor: %.2f EUR | Probabilidade: %d%%',
                    (string) $deal->stage,
                    (float) $deal->value,
                    (int) $deal->probability
                ),
                'occurred_at' => $timestamp->format('d/m/Y H:i'),
                'sort_at' => $timestamp->getTimestamp(),
            ];
        });
    }

    /**
     * @return Collection<int, array{key: string, interaction_type: string, title: string, details: string, occurred_at: string, sort_at: int}>
     */
    private function eventInteractions(Contact $contact): Collection
    {
        $baseQuery = DB::table('calendar_events');

        if (Schema::hasColumn('calendar_events', 'tenant_id')) {
            $baseQuery->where('calendar_events.tenant_id', $contact->tenant_id);
        }

        if (Schema::hasColumn('calendar_events', 'eventable_type') && Schema::hasColumn('calendar_events', 'eventable_id')) {
            $baseQuery->where(function ($query) use ($contact): void {
                $query->where(function ($morphQuery) use ($contact): void {
                    $morphQuery
                        ->whereIn('calendar_events.eventable_type', [Contact::class, 'App\\Models\\Person'])
                        ->where('calendar_events.eventable_id', $contact->id);
                });

                if ($contact->entity_id !== null) {
                    $query->orWhere(function ($morphQuery) use ($contact): void {
                        $morphQuery
                            ->where('calendar_events.eventable_type', Entity::class)
                            ->where('calendar_events.eventable_id', $contact->entity_id);
                    });
                }
            });
        } else {
            $baseQuery->where(function ($query) use ($contact): void {
                if ($contact->entity_id !== null) {
                    $query->where('calendar_events.entity_id', $contact->entity_id);
                } else {
                    $query->whereRaw('1 = 0');
                }
            });
        }

        $eventRows = $baseQuery
            ->orderByDesc('calendar_events.created_at')
            ->limit(25)
            ->get([
                'calendar_events.id',
                'calendar_events.title',
                'calendar_events.description',
                'calendar_events.start_at',
                'calendar_events.event_date',
                'calendar_events.event_time',
                'calendar_events.created_at',
            ]);

        return $eventRows->map(static function ($event): array {
            $timestamp = null;

            if ($event->start_at !== null) {
                $timestamp = Carbon::parse((string) $event->start_at);
            } elseif ($event->event_date !== null) {
                $eventTime = $event->event_time !== null ? (string) $event->event_time : '00:00:00';
                $timestamp = Carbon::parse(sprintf('%s %s', (string) $event->event_date, $eventTime));
            } elseif ($event->created_at !== null) {
                $timestamp = Carbon::parse((string) $event->created_at);
            } else {
                $timestamp = now();
            }

            $title = trim((string) ($event->title ?? ''));
            $description = trim((string) ($event->description ?? ''));

            return [
                'key' => sprintf('event-%d', (int) $event->id),
                'interaction_type' => 'Evento',
                'title' => $title !== '' ? $title : 'Evento sem titulo',
                'details' => $description !== '' ? $description : 'Sem descricao adicional.',
                'occurred_at' => $timestamp->format('d/m/Y H:i'),
                'sort_at' => $timestamp->getTimestamp(),
            ];
        });
    }
}

