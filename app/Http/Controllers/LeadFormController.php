<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeadFormRequest;
use App\Models\LeadForm;
use App\Models\LeadFormSubmission;
use App\Support\LeadFormFieldCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LeadFormController extends Controller
{
    public function __construct(private readonly LeadFormFieldCatalog $fieldCatalog)
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

    public function create(): Response
    {
        return Inertia::render('lead-forms/Create', [
            'defaults' => [
                'name' => '',
                'slug' => '',
                'status' => LeadForm::STATUS_ACTIVE,
                'requires_captcha' => true,
                'confirmation_message' => 'Obrigado pelo contacto. A equipa ira responder em breve.',
                'field_schema' => $this->fieldCatalog->defaults(),
            ],
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

        $submissions = LeadFormSubmission::query()
            ->with('contact:id,first_name,last_name,email')
            ->where('lead_form_id', $leadForm->id)
            ->orderByDesc('submitted_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (LeadFormSubmission $submission): array => [
                'id' => $submission->id,
                'contact' => $submission->contact !== null ? [
                    'id' => $submission->contact->id,
                    'name' => trim($submission->contact->first_name.' '.(string) $submission->contact->last_name),
                    'email' => $submission->contact->email,
                ] : null,
                'source_type' => $submission->source_type,
                'source_url' => $submission->source_url,
                'source_origin' => $submission->source_origin,
                'ip_address' => $submission->ip_address,
                'submitted_at' => $submission->submitted_at?->format('d/m/Y H:i:s'),
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
        return Inertia::render('lead-forms/Edit', [
            'leadForm' => [
                'id' => $leadForm->id,
                'name' => $leadForm->name,
                'slug' => $leadForm->slug,
                'status' => $leadForm->status,
                'requires_captcha' => (bool) $leadForm->requires_captcha,
                'confirmation_message' => $leadForm->confirmation_message,
                'field_schema' => $this->fieldCatalog->normalize((array) $leadForm->field_schema),
            ],
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
            'updated_by' => $request->user()?->id,
        ]);

        return to_route('lead-forms.show', $leadForm);
    }

    public function destroy(LeadForm $leadForm): RedirectResponse
    {
        $leadForm->delete();

        return to_route('lead-forms.index');
    }
}
