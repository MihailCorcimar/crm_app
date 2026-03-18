<?php

namespace App\Http\Controllers;

use App\Http\Requests\EntityRequest;
use App\Models\Contact;
use App\Models\Country;
use App\Models\Entity;
use App\Support\DealStageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class EntityController extends Controller
{
    public function __construct(
        private readonly DealStageService $dealStageService,
    ) {
        $this->authorizeResource(Entity::class, 'entity');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $name = trim($request->string('name')->toString());
        $vat = trim($request->string('vat')->toString());
        $status = $request->string('status')->toString();

        $entities = Entity::query()
            ->when($name !== '', fn ($query) => $query->where('name', 'like', "%{$name}%"))
            ->when($vat !== '', fn ($query) => $query->where(function ($innerQuery) use ($vat): void {
                $innerQuery
                    ->where('vat', 'like', "%{$vat}%")
                    ->orWhere('tax_id', 'like', "%{$vat}%");
            }))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Entity $entity): array => [
                'id' => $entity->id,
                'vat' => (string) ($entity->vat ?: $entity->tax_id),
                'name' => $entity->name,
                'phone' => $entity->phone,
                'email' => $entity->email,
                'status' => $entity->status,
            ]);

        return Inertia::render('entities/Index', [
            'entities' => $entities,
            'filters' => [
                'name' => $name,
                'vat' => $vat,
                'status' => in_array($status, ['active', 'inactive'], true) ? $status : '',
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $type = $request->string('type')->toString();

        return Inertia::render('entities/Create', [
            'defaultType' => in_array($type, ['customer', 'supplier', 'both'], true) ? $type : 'both',
            'countries' => $this->countries(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(EntityRequest $request): RedirectResponse
    {
        Entity::query()->create($this->payload($request->validated()));

        return to_route('entities.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Entity $entity): Response
    {
        $entity->load('country');

        return Inertia::render('entities/Show', [
            'entity' => [
                'id' => $entity->id,
                'type' => $entity->type,
                'number' => $entity->number,
                'vat' => (string) ($entity->vat ?: $entity->tax_id),
                'name' => $entity->name,
                'phone' => $entity->phone,
                'mobile' => $entity->mobile,
                'website' => $entity->website,
                'email' => $entity->email,
                'status' => $entity->status,
                'address' => $entity->address,
                'postal_code' => $entity->postal_code,
                'city' => $entity->city,
                'country' => $entity->country?->name,
                'notes' => $entity->notes,
                'gdpr_consent' => $entity->gdpr_consent,
            ],
            'associated_people' => $this->associatedPeople($entity),
            'deal_history' => $this->dealHistory($entity),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Entity $entity): Response
    {
        return Inertia::render('entities/Edit', [
            'entity' => [
                'id' => $entity->id,
                'type' => $entity->type,
                'vat' => (string) ($entity->vat ?: $entity->tax_id),
                'name' => $entity->name,
                'phone' => $entity->phone,
                'mobile' => $entity->mobile,
                'website' => $entity->website,
                'email' => $entity->email,
                'status' => $entity->status,
                'address' => $entity->address,
                'postal_code' => $entity->postal_code,
                'city' => $entity->city,
                'country_id' => $entity->country_id,
                'notes' => $entity->notes,
                'gdpr_consent' => $entity->gdpr_consent,
            ],
            'countries' => $this->countries(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EntityRequest $request, Entity $entity): RedirectResponse
    {
        $entity->update($this->payload($request->validated()));

        return to_route('entities.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Entity $entity): RedirectResponse
    {
        $entity->delete();

        return to_route('entities.index');
    }

    public function lookupVat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vat' => ['nullable', 'string', 'regex:/^\d{9}$/', 'required_without:tax_id'],
            'tax_id' => ['nullable', 'string', 'regex:/^\d{9}$/', 'required_without:vat'],
            'country_code' => ['nullable', 'string', 'size:2'],
        ]);

        $countryCode = strtoupper((string) ($validated['country_code'] ?? 'PT'));
        $vatInput = (string) ($validated['vat'] ?? $validated['tax_id'] ?? '');
        $vatNumber = preg_replace('/\D+/', '', $vatInput);

        $envelope = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tns="urn:ec.europa.eu:taxud:vies:services:checkVat:types">
    <soap:Body>
        <tns:checkVat>
            <tns:countryCode>{$countryCode}</tns:countryCode>
            <tns:vatNumber>{$vatNumber}</tns:vatNumber>
        </tns:checkVat>
    </soap:Body>
</soap:Envelope>
XML;

        try {
            $response = Http::connectTimeout(5)
                ->timeout(10)
                ->accept('text/xml')
                ->withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => 'urn:ec.europa.eu:taxud:vies:services:checkVat',
                ])
                ->withBody($envelope, 'text/xml; charset=utf-8')
                ->post('https://ec.europa.eu/taxation_customs/vies/services/checkVatService');
        } catch (ConnectionException) {
            return response()->json([
                'valid' => false,
                'message' => 'Falha de ligacao ao servico VIES.',
            ], 422);
        } catch (\Throwable) {
            return response()->json([
                'valid' => false,
                'message' => 'Erro inesperado ao consultar o VIES.',
            ], 422);
        }

        if (! $response->ok()) {
            return response()->json([
                'valid' => false,
                'message' => 'Nao foi possivel contactar o VIES.',
            ], 422);
        }

        $rawBody = trim((string) $response->body());
        $rawBody = preg_replace('/^\xEF\xBB\xBF/', '', $rawBody) ?? $rawBody;

        if ($rawBody === '') {
            return response()->json([
                'valid' => false,
                'message' => 'Resposta vazia do VIES.',
            ], 422);
        }

        if (preg_match('/<[^>]*:?Fault\\b/i', $rawBody) === 1) {
            return response()->json([
                'valid' => false,
                'message' => 'VIES devolveu um erro para o NIF indicado.',
            ], 422);
        }

        if (preg_match('/<[^>]*:?valid>(true|false)<\\/[^>]*:?valid>/i', $rawBody, $validMatch) !== 1) {
            return response()->json([
                'valid' => false,
                'message' => 'Resposta invalida do VIES.',
            ], 422);
        }

        $isValid = strtolower($validMatch[1]) === 'true';

        preg_match('/<[^>]*:?name>(.*?)<\\/[^>]*:?name>/is', $rawBody, $nameMatch);
        preg_match('/<[^>]*:?address>(.*?)<\\/[^>]*:?address>/is', $rawBody, $addressMatch);
        preg_match('/<[^>]*:?countryCode>([A-Z]{2})<\\/[^>]*:?countryCode>/i', $rawBody, $countryCodeMatch);

        $name = trim(html_entity_decode(strip_tags($nameMatch[1] ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8'));
        $address = trim(html_entity_decode(strip_tags($addressMatch[1] ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8'));
        $responseCountryCode = strtoupper(trim((string) ($countryCodeMatch[1] ?? $countryCode)));
        $normalizedAddress = preg_replace('/\s+/', ' ', str_replace("\n", ' ', $address)) ?? '';

        $postalCode = null;
        $city = null;
        if (preg_match('/(\d{4}-\d{3})\s+(.+)$/', $normalizedAddress, $matches) === 1) {
            $postalCode = $matches[1];
            $city = $matches[2];
        }

        return response()->json([
            'valid' => $isValid,
            'country_code' => $responseCountryCode,
            'name' => $name === '---' ? null : $name,
            'address' => $address === '---' ? null : $address,
            'postal_code' => $postalCode,
            'city' => $city,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated): array
    {
        return [
            'type' => $validated['type'],
            'tax_id' => $validated['vat'],
            'vat' => $validated['vat'],
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'website' => $validated['website'] ?? null,
            'email' => $validated['email'] ?? null,
            'status' => $validated['status'],
            'address' => $validated['address'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'city' => $validated['city'] ?? null,
            'country_id' => $validated['country_id'],
            'notes' => $validated['notes'] ?? null,
            'gdpr_consent' => (bool) ($validated['gdpr_consent'] ?? false),
        ];
    }

    /**
     * @return array<int, array{id: int, code: string, name: string}>
     */
    private function countries(): array
    {
        return Country::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Country $country): array => [
                'id' => $country->id,
                'code' => strtoupper((string) $country->code),
                'name' => $country->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, email: string|null, mobile: string|null, status: string}>
     */
    private function associatedPeople(Entity $entity): array
    {
        return Contact::query()
            ->where('entity_id', $entity->id)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(25)
            ->get(['id', 'first_name', 'last_name', 'email', 'mobile', 'status'])
            ->map(fn (Contact $contact): array => [
                'id' => $contact->id,
                'name' => trim(sprintf('%s %s', $contact->first_name, (string) $contact->last_name)),
                'email' => $contact->email,
                'mobile' => $contact->mobile,
                'status' => (string) $contact->status,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, title: string, stage: string, value: float, probability: int, expected_close_date: string|null, owner: string|null, created_at: string}>
     */
    private function dealHistory(Entity $entity): array
    {
        if (! Schema::hasTable('deals')) {
            return [];
        }

        $stageLabels = collect($this->dealStageService->forTenant($entity->tenant_id))
            ->mapWithKeys(fn (array $stage): array => [(string) $stage['value'] => (string) $stage['label']])
            ->all();

        $query = DB::table('deals')
            ->leftJoin('users as owners', 'owners.id', '=', 'deals.owner_id')
            ->where('deals.entity_id', $entity->id);

        if (Schema::hasColumn('deals', 'tenant_id')) {
            $query->where('deals.tenant_id', $entity->tenant_id);
        }

        return $query
            ->orderByDesc('deals.created_at')
            ->limit(25)
            ->get([
                'deals.id',
                'deals.title',
                'deals.stage',
                'deals.value',
                'deals.probability',
                'deals.expected_close_date',
                'deals.created_at',
                'owners.name as owner_name',
            ])
            ->map(function ($deal) use ($stageLabels): array {
                $expectedCloseDate = $deal->expected_close_date !== null
                    ? Carbon::parse((string) $deal->expected_close_date)->format('d/m/Y')
                    : null;

                $createdAt = $deal->created_at !== null
                    ? Carbon::parse((string) $deal->created_at)->format('d/m/Y H:i')
                    : '-';

                return [
                    'id' => (int) $deal->id,
                    'title' => (string) $deal->title,
                    'stage' => (string) ($stageLabels[(string) $deal->stage] ?? (string) $deal->stage),
                    'value' => (float) $deal->value,
                    'probability' => (int) $deal->probability,
                    'expected_close_date' => $expectedCloseDate,
                    'owner' => $deal->owner_name !== null ? (string) $deal->owner_name : null,
                    'created_at' => $createdAt,
                ];
            })
            ->values()
            ->all();
    }
}

