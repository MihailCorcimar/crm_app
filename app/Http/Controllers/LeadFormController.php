<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeadFormRequest;
use App\Models\LeadForm;
use App\Models\LeadFormSubmission;
use App\Models\User;
use App\Support\DealStageService;
use App\Support\LeadFormConversionSettings;
use App\Support\LeadFormFieldCatalog;
use App\Support\LeadFormSubmissionConversionService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LeadFormController extends Controller
{
    public function __construct(
        private readonly LeadFormFieldCatalog $fieldCatalog,
        private readonly LeadFormConversionSettings $conversionSettings,
        private readonly LeadFormSubmissionConversionService $conversionService,
        private readonly DealStageService $dealStageService
    )
    {
        $this->authorizeResource(LeadForm::class, 'leadForm');
    }

    public function index(Request $request): Response
    {
        $query = trim($request->string('q')->toString());
        $status = trim($request->string('status')->toString());

        $forms = LeadForm::query()
            ->withCount('submissions')
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($innerQuery) use ($query): void {
                    $innerQuery
                        ->where('name', 'like', "%{$query}%")
                        ->orWhere('slug', 'like', "%{$query}%");
                });
            })
            ->when(
                in_array($status, [LeadForm::STATUS_ACTIVE, LeadForm::STATUS_INACTIVE], true),
                fn ($builder) => $builder->where('status', $status)
            )
            ->orderByDesc('updated_at')
            ->paginate(12)
            ->withQueryString()
            ->through(function (LeadForm $leadForm): array {
                $normalizedSchema = $this->fieldCatalog->normalize((array) $leadForm->field_schema);
                $enabled = $this->fieldCatalog->enabledFields($normalizedSchema);

                return [
                    'id' => $leadForm->id,
                    'name' => $leadForm->name,
                    'slug' => $leadForm->slug,
                    'status' => $leadForm->status,
                    'requires_captcha' => (bool) $leadForm->requires_captcha,
                    'enabled_fields_count' => count($enabled),
                    'submissions_count' => (int) $leadForm->submissions_count,
                    'public_url' => route('public.lead-forms.show', ['token' => $leadForm->embed_token]),
                    'updated_at' => $leadForm->updated_at?->format('d/m/Y H:i'),
                ];
            });

        return Inertia::render('lead-forms/Index', [
            'forms' => $forms,
            'filters' => [
                'q' => $query,
                'status' => in_array($status, [LeadForm::STATUS_ACTIVE, LeadForm::STATUS_INACTIVE], true) ? $status : '',
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $tenantId = TenantContext::id($request) ?? (int) ($request->user()?->current_tenant_id ?? 0);

        return Inertia::render('lead-forms/Create', [
            'defaults' => [
                'name' => '',
                'slug' => '',
                'status' => LeadForm::STATUS_ACTIVE,
                'requires_captcha' => true,
                'confirmation_message' => 'Obrigado pelo contacto. A equipa ira responder em breve.',
                'field_schema' => $this->fieldCatalog->defaults(),
                'conversion_settings' => $this->conversionSettings->defaults($tenantId),
            ],
            'ownerOptions' => $this->ownerOptions($tenantId),
            'stageOptions' => $this->stageOptions($tenantId),
        ]);
    }

    public function store(LeadFormRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        LeadForm::query()->create([
            'name' => trim((string) $validated['name']),
            'slug' => trim((string) $validated['slug']),
            'status' => (string) $validated['status'],
            'requires_captcha' => (bool) $validated['requires_captcha'],
            'confirmation_message' => trim((string) $validated['confirmation_message']),
            'field_schema' => $this->fieldCatalog->normalize((array) $validated['field_schema']),
            'conversion_settings' => $this->conversionSettings->normalize(
                is_array($validated['conversion_settings'] ?? null) ? $validated['conversion_settings'] : null,
                TenantContext::id($request) ?? (int) ($request->user()?->current_tenant_id ?? 0)
            ),
            'embed_token' => Str::random(48),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return to_route('lead-forms.index');
    }

    public function show(LeadForm $leadForm): Response
    {
        $normalizedSchema = $this->fieldCatalog->normalize((array) $leadForm->field_schema);
        $enabledFields = $this->fieldCatalog->enabledFields($normalizedSchema);
        $conversionSettings = $this->conversionSettings->normalize(
            is_array($leadForm->conversion_settings) ? $leadForm->conversion_settings : null,
            (int) $leadForm->tenant_id
        );

        $submissions = LeadFormSubmission::query()
            ->with([
                'contact:id,first_name,last_name,email',
                'entity:id,name',
                'deal:id,title,stage',
                'convertedBy:id,name',
                'ignoredBy:id,name',
            ])
            ->where('lead_form_id', $leadForm->id)
            ->orderByDesc('submitted_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (LeadFormSubmission $submission): array => [
                'id' => $submission->id,
                'status' => (string) $submission->status,
                'contact' => $submission->contact !== null ? [
                    'id' => $submission->contact->id,
                    'name' => trim($submission->contact->first_name.' '.(string) $submission->contact->last_name),
                    'email' => $submission->contact->email,
                ] : null,
                'entity' => $submission->entity !== null ? [
                    'id' => $submission->entity->id,
                    'name' => $submission->entity->name,
                ] : null,
                'deal' => $submission->deal !== null ? [
                    'id' => $submission->deal->id,
                    'title' => $submission->deal->title,
                    'stage' => $submission->deal->stage,
                ] : null,
                'source_type' => $submission->source_type,
                'source_url' => $submission->source_url,
                'source_origin' => $submission->source_origin,
                'ip_address' => $submission->ip_address,
                'submitted_at' => $submission->submitted_at?->format('d/m/Y H:i:s'),
                'converted_at' => $submission->converted_at?->format('d/m/Y H:i:s'),
                'converted_by' => $submission->convertedBy?->name,
                'ignored_at' => $submission->ignored_at?->format('d/m/Y H:i:s'),
                'ignored_by' => $submission->ignoredBy?->name,
                'payload' => $submission->payload,
            ]);

        $publicUrl = route('public.lead-forms.show', ['token' => $leadForm->embed_token]);
        $scriptUrl = route('public.lead-forms.embed-script', ['token' => $leadForm->embed_token]);

        return Inertia::render('lead-forms/Show', [
            'leadForm' => [
                'id' => $leadForm->id,
                'name' => $leadForm->name,
                'slug' => $leadForm->slug,
                'status' => $leadForm->status,
                'requires_captcha' => (bool) $leadForm->requires_captcha,
                'confirmation_message' => $leadForm->confirmation_message,
                'field_schema' => $normalizedSchema,
                'enabled_fields' => $enabledFields,
                'conversion_settings' => $conversionSettings,
                'public_url' => $publicUrl,
                'embed_iframe_code' => sprintf('<iframe src="%s" width="100%%" height="680" style="border:0;" loading="lazy"></iframe>', $publicUrl),
                'embed_script_code' => sprintf(
                    '<script async src="%s"></script>'.PHP_EOL.'<div data-crm-lead-form="%s"></div>',
                    $scriptUrl,
                    $leadForm->embed_token
                ),
            ],
            'submissions' => $submissions,
        ]);
    }

    public function edit(LeadForm $leadForm): Response
    {
        $tenantId = (int) $leadForm->tenant_id;

        return Inertia::render('lead-forms/Edit', [
            'leadForm' => [
                'id' => $leadForm->id,
                'name' => $leadForm->name,
                'slug' => $leadForm->slug,
                'status' => $leadForm->status,
                'requires_captcha' => (bool) $leadForm->requires_captcha,
                'confirmation_message' => $leadForm->confirmation_message,
                'field_schema' => $this->fieldCatalog->normalize((array) $leadForm->field_schema),
                'conversion_settings' => $this->conversionSettings->normalize(
                    is_array($leadForm->conversion_settings) ? $leadForm->conversion_settings : null,
                    $tenantId
                ),
            ],
            'ownerOptions' => $this->ownerOptions($tenantId),
            'stageOptions' => $this->stageOptions($tenantId),
        ]);
    }

    public function update(LeadFormRequest $request, LeadForm $leadForm): RedirectResponse
    {
        $validated = $request->validated();

        $leadForm->update([
            'name' => trim((string) $validated['name']),
            'slug' => trim((string) $validated['slug']),
            'status' => (string) $validated['status'],
            'requires_captcha' => (bool) $validated['requires_captcha'],
            'confirmation_message' => trim((string) $validated['confirmation_message']),
            'field_schema' => $this->fieldCatalog->normalize((array) $validated['field_schema']),
            'conversion_settings' => $this->conversionSettings->normalize(
                is_array($validated['conversion_settings'] ?? null) ? $validated['conversion_settings'] : null,
                (int) $leadForm->tenant_id
            ),
            'updated_by' => $request->user()?->id,
        ]);

        return to_route('lead-forms.show', $leadForm);
    }

    public function destroy(LeadForm $leadForm): RedirectResponse
    {
        $leadForm->delete();

        return to_route('lead-forms.index');
    }

    public function convertSubmission(Request $request, LeadForm $leadForm, LeadFormSubmission $submission): RedirectResponse
    {
        $this->authorize('update', $leadForm);

        abort_unless((int) $submission->lead_form_id === (int) $leadForm->id, 404);

        $settings = $this->conversionSettings->normalize(
            is_array($leadForm->conversion_settings) ? $leadForm->conversion_settings : null,
            (int) $leadForm->tenant_id
        );

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $this->conversionService->convert($leadForm, $submission, $settings, $actor);

        return back()->with('success', 'Submissao convertida com sucesso.');
    }

    public function ignoreSubmission(Request $request, LeadForm $leadForm, LeadFormSubmission $submission): RedirectResponse
    {
        $this->authorize('update', $leadForm);

        abort_unless((int) $submission->lead_form_id === (int) $leadForm->id, 404);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $this->conversionService->ignore($submission, $actor);

        return back()->with('success', 'Submissao marcada como ignorada.');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function ownerOptions(?int $tenantId): array
    {
        if (! is_int($tenantId) || $tenantId <= 0) {
            return [];
        }

        return User::query()
            ->join('tenant_user', 'tenant_user.user_id', '=', 'users.id')
            ->where('tenant_user.tenant_id', $tenantId)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name'])
            ->map(fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => $user->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function stageOptions(?int $tenantId): array
    {
        return collect($this->dealStageService->forTenant($tenantId))
            ->map(fn (array $stage): array => [
                'value' => (string) $stage['value'],
                'label' => (string) $stage['label'],
            ])
            ->values()
            ->all();
    }
}
