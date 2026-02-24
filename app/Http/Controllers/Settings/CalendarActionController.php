<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CalendarActionRequest;
use App\Models\CalendarAction;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CalendarActionController extends Controller
{
    public function index(): Response
    {
        $calendarActions = CalendarAction::query()
            ->withCount('events')
            ->orderBy('name')
            ->get()
            ->map(fn (CalendarAction $calendarAction): array => [
                'id' => $calendarAction->id,
                'name' => $calendarAction->name,
                'events_count' => $calendarAction->events_count,
                'status' => $calendarAction->status,
            ])
            ->all();

        return Inertia::render('settings/CalendarActions', [
            'calendarActions' => $calendarActions,
        ]);
    }

    public function store(CalendarActionRequest $request): RedirectResponse
    {
        CalendarAction::query()->create($request->validated());

        return to_route('settings.calendar-actions.index');
    }

    public function update(CalendarActionRequest $request, CalendarAction $calendarAction): RedirectResponse
    {
        $calendarAction->update($request->validated());

        return to_route('settings.calendar-actions.index');
    }

    public function destroy(CalendarAction $calendarAction): RedirectResponse
    {
        if ($calendarAction->events()->exists()) {
            return to_route('settings.calendar-actions.index')
                ->withErrors(['calendarAction' => 'Nao podes eliminar uma acao associada a atividades.']);
        }

        $calendarAction->delete();

        return to_route('settings.calendar-actions.index');
    }
}
