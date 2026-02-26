<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalendarEventRequest;
use App\Models\CalendarAction;
use App\Models\CalendarEvent;
use App\Models\CalendarType;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                'entity:id,name',
                'user:id,name',
                'type:id,name',
                'action:id,name',
            ]);

        if ($request->filled('user_id')) {
            $baseQuery->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('entity_id')) {
            $baseQuery->where('entity_id', $request->integer('entity_id'));
        }

        $events = (clone $baseQuery)
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->get()
            ->map(fn (CalendarEvent $calendarEvent): array => [
                'id' => $calendarEvent->id,
                'start' => $calendarEvent->startAt()->format('Y-m-d\TH:i:s'),
                'end' => $calendarEvent->endAt()->format('Y-m-d\TH:i:s'),
                'title' => $this->eventTitle($calendarEvent),
                'extendedProps' => [
                    'entity' => $calendarEvent->entity?->name,
                    'user' => $calendarEvent->user?->name,
                    'type' => $calendarEvent->type?->name,
                    'action' => $calendarEvent->action?->name,
                    'status' => $calendarEvent->status,
                ],
            ])
            ->all();

        $rows = (clone $baseQuery)
            ->orderByDesc('event_date')
            ->orderByDesc('event_time')
            ->get()
            ->map(fn (CalendarEvent $calendarEvent): array => [
                'id' => $calendarEvent->id,
                'event_date' => $calendarEvent->event_date?->format('Y-m-d'),
                'event_time' => substr((string) $calendarEvent->event_time, 0, 5),
                'duration_minutes' => $calendarEvent->duration_minutes,
                'entity' => $calendarEvent->entity?->name,
                'user' => $calendarEvent->user?->name,
                'type' => $calendarEvent->type?->name,
                'action' => $calendarEvent->action?->name,
                'status' => $calendarEvent->status,
            ])
            ->all();

        return Inertia::render('calendar/Index', [
            'events' => $events,
            'rows' => $rows,
            'filters' => [
                'user_id' => $request->query('user_id', ''),
                'entity_id' => $request->query('entity_id', ''),
            ],
            'users' => $this->users(),
            'entities' => $this->entities(),
            'types' => $this->types(),
            'actions' => $this->actions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('calendar/Create', [
            'users' => $this->users(),
            'entities' => $this->entities(),
            'types' => $this->types(),
            'actions' => $this->actions(),
            'defaults' => [
                'event_date' => now()->format('Y-m-d'),
                'event_time' => now()->format('H:i'),
                'duration_minutes' => 60,
                'status' => 'active',
                'user_id' => auth()->id(),
            ],
        ]);
    }

    public function store(CalendarEventRequest $request): RedirectResponse
    {
        CalendarEvent::query()->create($request->validated());

        return to_route('calendar.index');
    }

    public function edit(CalendarEvent $calendar): Response
    {
        return Inertia::render('calendar/Edit', [
            'event' => [
                'id' => $calendar->id,
                'event_date' => $calendar->event_date?->format('Y-m-d'),
                'event_time' => substr((string) $calendar->event_time, 0, 5),
                'duration_minutes' => $calendar->duration_minutes,
                'share' => $calendar->share,
                'knowledge' => $calendar->knowledge,
                'entity_id' => $calendar->entity_id,
                'user_id' => $calendar->user_id,
                'calendar_type_id' => $calendar->calendar_type_id,
                'calendar_action_id' => $calendar->calendar_action_id,
                'description' => $calendar->description,
                'status' => $calendar->status,
            ],
            'users' => $this->users(),
            'entities' => $this->entities(),
            'types' => $this->types(),
            'actions' => $this->actions(),
        ]);
    }

    public function update(CalendarEventRequest $request, CalendarEvent $calendar): RedirectResponse
    {
        $calendar->update($request->validated());

        return to_route('calendar.index');
    }

    public function destroy(CalendarEvent $calendar): RedirectResponse
    {
        $calendar->delete();

        return to_route('calendar.index');
    }

    private function eventTitle(CalendarEvent $calendarEvent): string
    {
        $type = $calendarEvent->type?->name ?? 'Atividade';
        $entity = $calendarEvent->entity?->name;

        return $entity ? sprintf('%s - %s', $type, $entity) : $type;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function users(): array
    {
        return User::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->all();
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
}

