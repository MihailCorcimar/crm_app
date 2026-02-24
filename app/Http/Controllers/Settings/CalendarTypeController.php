<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CalendarTypeRequest;
use App\Models\CalendarType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CalendarTypeController extends Controller
{
    public function index(): Response
    {
        $calendarTypes = CalendarType::query()
            ->withCount('events')
            ->orderBy('name')
            ->get()
            ->map(fn (CalendarType $calendarType): array => [
                'id' => $calendarType->id,
                'name' => $calendarType->name,
                'events_count' => $calendarType->events_count,
                'status' => $calendarType->status,
            ])
            ->all();

        return Inertia::render('settings/CalendarTypes', [
            'calendarTypes' => $calendarTypes,
        ]);
    }

    public function store(CalendarTypeRequest $request): RedirectResponse
    {
        CalendarType::query()->create($request->validated());

        return to_route('settings.calendar-types.index');
    }

    public function update(CalendarTypeRequest $request, CalendarType $calendarType): RedirectResponse
    {
        $calendarType->update($request->validated());

        return to_route('settings.calendar-types.index');
    }

    public function destroy(CalendarType $calendarType): RedirectResponse
    {
        if ($calendarType->events()->exists()) {
            return to_route('settings.calendar-types.index')
                ->withErrors(['calendarType' => 'Nao podes eliminar um tipo associado a atividades.']);
        }

        $calendarType->delete();

        return to_route('settings.calendar-types.index');
    }
}
