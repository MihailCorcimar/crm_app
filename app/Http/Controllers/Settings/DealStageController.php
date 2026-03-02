<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DealStageSettingsRequest;
use App\Support\DealStageService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DealStageController extends Controller
{
    public function __construct(private readonly DealStageService $dealStageService)
    {
    }

    public function index(): Response
    {
        return Inertia::render('settings/DealStages', [
            'stages' => $this->dealStageService->forTenant(TenantContext::id()),
        ]);
    }

    public function update(DealStageSettingsRequest $request): RedirectResponse
    {
        $this->dealStageService->updateForTenant(
            TenantContext::id($request),
            $request->validated('stages')
        );

        return to_route('settings.deal-stages.index');
    }
}
