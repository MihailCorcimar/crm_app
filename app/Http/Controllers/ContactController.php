<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Http\Requests\ContactMergeRequest;
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
            'duplicate_candidates' => $this->duplicateCandidates($contact),
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

    public function merge(ContactMergeRequest $request, Contact $contact): RedirectResponse
    {
        $duplicateContactId = (int) $request->validated('duplicate_contact_id');
        if ($duplicateContactId === (int) $contact->id) {
            return back()->withErrors([
                'duplicate_contact_id' => 'Seleciona outra pessoa para merge.',
            ]);
        }

        $duplicateContact = Contact::query()
            ->whereKey($duplicateContactId)
            ->firstOrFail();

        $this->authorize('update', $contact);
        $this->authorize('update', $duplicateContact);

        DB::transaction(function () use ($contact, $duplicateContact): void {
            $contact->update($this->mergePayload($contact, $duplicateContact));

            $this->reassignContactRelations($contact, $duplicateContact);
            $duplicateContact->delete();
        });

        return to_route('people.show', $contact)->with('success', 'Duplicado unido com sucesso.');
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
     * @return array<string, mixed>
     */
    private function mergePayload(Contact $primaryContact, Contact $duplicateContact): array
    {
        $primaryNotes = trim((string) ($primaryContact->notes ?? ''));
        $duplicateNotes = trim((string) ($duplicateContact->notes ?? ''));

        $notes = $primaryNotes;
        if ($duplicateNotes !== '') {
            $notes = $primaryNotes === ''
                ? $duplicateNotes
                : $primaryNotes.PHP_EOL.PHP_EOL.'[Merge] '.now()->format('Y-m-d H:i').PHP_EOL.$duplicateNotes;
        }

        $status = $primaryContact->status;
        if ($status !== 'active' && $duplicateContact->status === 'active') {
            $status = 'active';
        }

        return [
            'entity_id' => $primaryContact->entity_id ?? $duplicateContact->entity_id,
            'first_name' => trim($primaryContact->first_name) !== '' ? $primaryContact->first_name : $duplicateContact->first_name,
            'last_name' => $primaryContact->last_name ?: $duplicateContact->last_name,
            'role_id' => $primaryContact->role_id ?: $duplicateContact->role_id,
            'phone' => $primaryContact->phone ?: $duplicateContact->phone,
            'mobile' => $primaryContact->mobile ?: $duplicateContact->mobile,
            'email' => $primaryContact->email ?: $duplicateContact->email,
            'gdpr_consent' => (bool) ($primaryContact->gdpr_consent || $duplicateContact->gdpr_consent),
            'notes' => $notes !== '' ? $notes : null,
            'status' => $status,
        ];
    }

    private function reassignContactRelations(Contact $primaryContact, Contact $duplicateContact): void
    {
        if (Schema::hasTable('deals') && Schema::hasColumn('deals', 'person_id')) {
            $query = DB::table('deals')
                ->where('person_id', $duplicateContact->id);

            if (Schema::hasColumn('deals', 'tenant_id')) {
                $query->where('tenant_id', $primaryContact->tenant_id);
            }

            $query->update([
                'person_id' => $primaryContact->id,
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('calendar_events')
            && Schema::hasColumn('calendar_events', 'eventable_type')
            && Schema::hasColumn('calendar_events', 'eventable_id')
        ) {
            $query = DB::table('calendar_events')
                ->where('eventable_type', Contact::class)
                ->where('eventable_id', $duplicateContact->id);

            if (Schema::hasColumn('calendar_events', 'tenant_id')) {
                $query->where('tenant_id', $primaryContact->tenant_id);
            }

            $query->update([
                'eventable_id' => $primaryContact->id,
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('calendar_event_attendees')) {
            $duplicateEventIds = DB::table('calendar_event_attendees as attendees')
                ->join('calendar_events as events', 'events.id', '=', 'attendees.calendar_event_id')
                ->where('attendees.attendee_type', Contact::class)
                ->where('attendees.attendee_id', $duplicateContact->id)
                ->where('events.tenant_id', $primaryContact->tenant_id)
                ->pluck('attendees.calendar_event_id')
                ->all();

            if ($duplicateEventIds !== []) {
                DB::table('calendar_event_attendees')
                    ->where('attendee_type', Contact::class)
                    ->where('attendee_id', $primaryContact->id)
                    ->whereIn('calendar_event_id', $duplicateEventIds)
                    ->delete();

                DB::table('calendar_event_attendees')
                    ->where('attendee_type', Contact::class)
                    ->where('attendee_id', $duplicateContact->id)
                    ->whereIn('calendar_event_id', $duplicateEventIds)
                    ->update([
                        'attendee_id' => $primaryContact->id,
                        'updated_at' => now(),
                    ]);
            }
        }

        if (Schema::hasTable('lead_form_submissions') && Schema::hasColumn('lead_form_submissions', 'contact_id')) {
            DB::table('lead_form_submissions')
                ->where('tenant_id', $primaryContact->tenant_id)
                ->where('contact_id', $duplicateContact->id)
                ->update([
                    'contact_id' => $primaryContact->id,
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('ai_sales_suggestions') && Schema::hasColumn('ai_sales_suggestions', 'contact_id')) {
            DB::table('ai_sales_suggestions')
                ->where('tenant_id', $primaryContact->tenant_id)
                ->where('contact_id', $duplicateContact->id)
                ->update([
                    'contact_id' => $primaryContact->id,
                    'updated_at' => now(),
                ]);
        }
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
     * @return array<int, array{id: int, full_name: string, email: string|null, mobile: string|null, entity: string|null, reason: string}>
     */
    private function duplicateCandidates(Contact $contact): array
    {
        $hasEmail = $contact->email !== null && trim($contact->email) !== '';
        $hasMobile = $contact->mobile !== null && trim($contact->mobile) !== '';

        if (! $hasEmail && ! $hasMobile) {
            return [];
        }

        $query = Contact::query()
            ->with(['entity:id,name'])
            ->whereKeyNot($contact->id)
            ->where(function ($query) use ($contact): void {
                $hasCondition = false;

                if ($contact->email !== null && trim($contact->email) !== '') {
                    $hasCondition = true;
                    $query->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($contact->email))]);
                }

                if ($contact->mobile !== null && trim($contact->mobile) !== '') {
                    if ($hasCondition) {
                        $query->orWhere('mobile', trim($contact->mobile));
                    } else {
                        $query->where('mobile', trim($contact->mobile));
                    }
                }
            })
            ->latest()
            ->limit(20)
            ->get();

        return $query
            ->reject(fn (Contact $candidate): bool => (int) $candidate->id === (int) $contact->id)
            ->map(function (Contact $candidate) use ($contact): array {
                $reason = 'Dados de contacto iguais';
                if ($contact->email !== null && $candidate->email !== null && mb_strtolower(trim($contact->email)) === mb_strtolower(trim($candidate->email))) {
                    $reason = 'Email igual';
                } elseif ($contact->mobile !== null && $candidate->mobile !== null && trim($contact->mobile) === trim($candidate->mobile)) {
                    $reason = 'Telemovel igual';
                }

                return [
                    'id' => $candidate->id,
                    'full_name' => trim($candidate->first_name.' '.(string) $candidate->last_name),
                    'email' => $candidate->email,
                    'mobile' => $candidate->mobile,
                    'entity' => $candidate->entity?->name,
                    'reason' => $reason,
                ];
            })
            ->values()
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

