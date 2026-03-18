<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalendarEventRequest;
use App\Models\CalendarAction;
use App\Models\CalendarEvent;
use App\Models\CalendarType;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Entity;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(CalendarEvent::class, 'calendar');
    }

    public function index(Request $request): Response
    {
        $baseQuery = CalendarEvent::query()
            ->with([
                'eventable',
                'owner:id,name',
                'type:id,name',
                'action:id,name',
                'attendees.attendee',
            ]);

        if ($request->filled('owner_id')) {
            $baseQuery->where('owner_id', $request->integer('owner_id'));
        }

        if ($request->filled('eventable_type')) {
            $eventableClass = $this->eventableClass($request->string('eventable_type')->toString());
            if ($eventableClass !== null) {
                $baseQuery->where('eventable_type', $eventableClass);
            }
        }

        $timeScope = $request->string('time_scope')->toString();
        $this->applyTimeScopeFilter($baseQuery, $timeScope);

        $events = (clone $baseQuery)
            ->orderBy('start_at')
            ->get()
            ->map(fn (CalendarEvent $calendarEvent): array => [
                'id' => $calendarEvent->id,
                'start' => $calendarEvent->startAt()->format('Y-m-d\TH:i:s'),
                'end' => $calendarEvent->endAt()->format('Y-m-d\TH:i:s'),
                'title' => $calendarEvent->title ?: $this->eventTitle($calendarEvent),
                'extendedProps' => [
                    'owner' => $calendarEvent->owner?->name,
                    'type' => $calendarEvent->type?->name,
                    'action' => $calendarEvent->action?->name,
                    'eventable' => $this->eventableLabel($calendarEvent),
                    'attendees_count' => $calendarEvent->attendees->count(),
                    'entity_attendees' => $this->attendeeLabels($calendarEvent, Entity::class),
                    'person_attendees' => $this->attendeeLabels($calendarEvent, Contact::class),
                    'deal_attendees' => $this->attendeeLabels($calendarEvent, Deal::class),
                    'status' => $calendarEvent->status,
                    'time_state' => $this->timeStateLabel($calendarEvent),
                ],
            ])
            ->all();

        $rows = (clone $baseQuery)
            ->orderByDesc('start_at')
            ->get()
            ->map(fn (CalendarEvent $calendarEvent): array => [
                'id' => $calendarEvent->id,
                'title' => $calendarEvent->title,
                'start_at' => $calendarEvent->startAt()->format('Y-m-d H:i'),
                'end_at' => $calendarEvent->endAt()->format('Y-m-d H:i'),
                'eventable' => $this->eventableLabel($calendarEvent),
                'owner' => $calendarEvent->owner?->name,
                'type' => $calendarEvent->type?->name,
                'action' => $calendarEvent->action?->name,
                'attendees_count' => $calendarEvent->attendees->count(),
                'status' => $calendarEvent->status,
                'time_state' => $this->timeStateLabel($calendarEvent),
            ])
            ->all();

        return Inertia::render('calendar/Index', [
            'events' => $events,
            'rows' => $rows,
            'filters' => [
                'owner_id' => $request->query('owner_id', ''),
                'eventable_type' => $request->query('eventable_type', ''),
                'time_scope' => $request->query('time_scope', 'all'),
            ],
            'owners' => $this->owners(),
            'eventableTypes' => $this->eventableTypes(),
            'entities' => $this->entities(),
            'people' => $this->people(),
            'deals' => $this->deals(),
            'types' => $this->types(),
            'actions' => $this->actions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('calendar/Create', [
            'owners' => $this->owners(),
            'eventableTypes' => $this->eventableTypes(),
            'entities' => $this->entities(),
            'people' => $this->people(),
            'deals' => $this->deals(),
            'types' => $this->types(),
            'actions' => $this->actions(),
            'defaults' => [
                'start_at' => now()->format('Y-m-d\TH:i'),
                'end_at' => now()->addHour()->format('Y-m-d\TH:i'),
                'status' => 'active',
                'owner_id' => auth()->id(),
            ],
        ]);
    }

    public function store(CalendarEventRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $calendarEvent = CalendarEvent::query()->create($this->payload($validated));
            $calendarEvent->attendees()->createMany($this->attendeesPayload($validated));
        });

        return to_route('calendar.index');
    }

    public function edit(CalendarEvent $calendar): Response
    {
        $calendar->load('attendees');

        return Inertia::render('calendar/Edit', [
            'event' => [
                'id' => $calendar->id,
                'title' => $calendar->title,
                'description' => $calendar->description,
                'start_at' => $calendar->startAt()->format('Y-m-d\TH:i'),
                'end_at' => $calendar->endAt()->format('Y-m-d\TH:i'),
                'location' => $calendar->location,
                'owner_id' => $calendar->owner_id,
                'eventable_type' => $this->eventableTypeFromClass($calendar->eventable_type),
                'eventable_id' => $calendar->eventable_id,
                'calendar_type_id' => $calendar->calendar_type_id,
                'calendar_action_id' => $calendar->calendar_action_id,
                'status' => $calendar->status,
                'attendee_entity_ids' => $calendar->attendees
                    ->where('attendee_type', Entity::class)
                    ->pluck('attendee_id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all(),
                'attendee_person_ids' => $calendar->attendees
                    ->where('attendee_type', Contact::class)
                    ->pluck('attendee_id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all(),
                'attendee_deal_ids' => $calendar->attendees
                    ->where('attendee_type', Deal::class)
                    ->pluck('attendee_id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all(),
            ],
            'owners' => $this->owners(),
            'eventableTypes' => $this->eventableTypes(),
            'entities' => $this->entities(),
            'people' => $this->people(),
            'deals' => $this->deals(),
            'types' => $this->types(),
            'actions' => $this->actions(),
        ]);
    }

    public function update(CalendarEventRequest $request, CalendarEvent $calendar): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($calendar, $validated): void {
            $calendar->update($this->payload($validated));
            $calendar->attendees()->delete();
            $calendar->attendees()->createMany($this->attendeesPayload($validated));
        });

        return to_route('calendar.index');
    }

    public function destroy(CalendarEvent $calendar): RedirectResponse
    {
        $calendar->delete();

        return to_route('calendar.index');
    }

    private function eventTitle(CalendarEvent $calendarEvent): string
    {
        return $calendarEvent->title ?: 'Atividade';
    }

    private function eventableLabel(CalendarEvent $calendarEvent): string
    {
        if ($calendarEvent->eventable === null) {
            return '-';
        }

        if ($calendarEvent->eventable instanceof Entity) {
            return 'Entidade: '.$calendarEvent->eventable->name;
        }

        if ($calendarEvent->eventable instanceof Contact) {
            $fullName = trim($calendarEvent->eventable->first_name.' '.($calendarEvent->eventable->last_name ?? ''));

            return 'Pessoa: '.$fullName;
        }

        if ($calendarEvent->eventable instanceof Deal) {
            return 'Negocio: '.$calendarEvent->eventable->title;
        }

        return '-';
    }

    /**
     * @return array<int, string>
     */
    private function attendeeLabels(CalendarEvent $calendarEvent, string $attendeeType): array
    {
        return $calendarEvent->attendees
            ->where('attendee_type', $attendeeType)
            ->map(function ($attendee): string {
                $model = $attendee->attendee;

                if ($model instanceof Entity) {
                    return (string) $model->name;
                }

                if ($model instanceof Contact) {
                    return trim($model->first_name.' '.($model->last_name ?? ''));
                }

                if ($model instanceof Deal) {
                    return (string) $model->title;
                }

                return '';
            })
            ->filter(fn (string $label): bool => trim($label) !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated): array
    {
        $startAt = CarbonImmutable::parse((string) $validated['start_at']);
        $endAt = CarbonImmutable::parse((string) $validated['end_at']);
        $duration = max(1, $startAt->diffInMinutes($endAt));

        $eventableClass = $this->eventableClass(isset($validated['eventable_type']) ? (string) $validated['eventable_type'] : null);

        return [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'location' => $validated['location'] ?? null,
            'owner_id' => $validated['owner_id'],
            'eventable_type' => $eventableClass,
            'eventable_id' => $validated['eventable_id'] ?? null,
            'calendar_type_id' => $validated['calendar_type_id'] ?? null,
            'calendar_action_id' => $validated['calendar_action_id'] ?? null,
            'status' => $validated['status'],
            // Legacy columns kept in sync while they still exist.
            'event_date' => $startAt->format('Y-m-d'),
            'event_time' => $startAt->format('H:i:s'),
            'duration_minutes' => $duration,
            'user_id' => $validated['owner_id'],
            'entity_id' => $eventableClass === Entity::class ? $validated['eventable_id'] : null,
            'share' => null,
            'knowledge' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, array{attendee_type: string, attendee_id: int}>
     */
    private function attendeesPayload(array $validated): array
    {
        $attendees = [];

        foreach (($validated['attendee_entity_ids'] ?? []) as $id) {
            $attendees[] = [
                'attendee_type' => Entity::class,
                'attendee_id' => (int) $id,
            ];
        }

        foreach (($validated['attendee_person_ids'] ?? []) as $id) {
            $attendees[] = [
                'attendee_type' => Contact::class,
                'attendee_id' => (int) $id,
            ];
        }

        foreach (($validated['attendee_deal_ids'] ?? []) as $id) {
            $attendees[] = [
                'attendee_type' => Deal::class,
                'attendee_id' => (int) $id,
            ];
        }

        return collect($attendees)
            ->unique(fn (array $item): string => $item['attendee_type'].':'.$item['attendee_id'])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function owners(): array
    {
        $tenantId = TenantContext::id();

        if (! is_int($tenantId) || $tenantId <= 0) {
            return [];
        }

        return User::query()
            ->where('status', 'active')
            ->whereHas('tenants', fn ($query) => $query->where('tenants.id', $tenantId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function eventableTypes(): array
    {
        return [
            ['value' => 'entity', 'label' => 'Entidade'],
            ['value' => 'person', 'label' => 'Pessoa'],
            ['value' => 'deal', 'label' => 'Negocio'],
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function entities(): array
    {
        return Entity::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Entity $entity): array => [
                'id' => $entity->id,
                'name' => $entity->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, entity_id: int|null}>
     */
    private function people(): array
    {
        return Contact::query()
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'entity_id'])
            ->map(fn (Contact $person): array => [
                'id' => $person->id,
                'name' => trim($person->first_name.' '.($person->last_name ?? '')),
                'entity_id' => $person->entity_id,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, entity_id: int|null}>
     */
    private function deals(): array
    {
        return Deal::query()
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get(['id', 'title', 'entity_id'])
            ->map(fn (Deal $deal): array => [
                'id' => $deal->id,
                'name' => $deal->title,
                'entity_id' => $deal->entity_id,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function types(): array
    {
        return CalendarType::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (CalendarType $calendarType): array => [
                'id' => $calendarType->id,
                'name' => $calendarType->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function actions(): array
    {
        return CalendarAction::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (CalendarAction $calendarAction): array => [
                'id' => $calendarAction->id,
                'name' => $calendarAction->name,
            ])
            ->all();
    }

    private function applyTimeScopeFilter(Builder $query, string $timeScope): void
    {
        $now = now();

        if ($timeScope === 'past') {
            $query->where('end_at', '<', $now);

            return;
        }

        if ($timeScope === 'upcoming') {
            $query->where('end_at', '>=', $now);

            return;
        }

        if ($timeScope === 'today') {
            $query
                ->where('start_at', '<=', $now->copy()->endOfDay())
                ->where('end_at', '>=', $now->copy()->startOfDay());
        }
    }

    private function timeStateLabel(CalendarEvent $calendarEvent): string
    {
        $now = now();
        $startAt = $calendarEvent->startAt();
        $endAt = $calendarEvent->endAt();

        if ($endAt->lessThan($now)) {
            return 'past';
        }

        if ($startAt->lessThanOrEqualTo($now) && $endAt->greaterThanOrEqualTo($now)) {
            return 'today';
        }

        return 'upcoming';
    }

    private function eventableClass(?string $eventableType): ?string
    {
        return match ($eventableType) {
            'entity' => Entity::class,
            'person' => Contact::class,
            'deal' => Deal::class,
            default => null,
        };
    }

    private function eventableTypeFromClass(?string $eventableClass): ?string
    {
        return match ($eventableClass) {
            Entity::class => 'entity',
            Contact::class => 'person',
            Deal::class => 'deal',
            default => null,
        };
    }
}
