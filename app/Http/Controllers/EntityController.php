<?php

namespace App\Http\Controllers;

use App\Http\Requests\EntityRequest;
use App\Models\Country;
use App\Models\Entity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class EntityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $type = $request->string('type')->toString();

        $entities = Entity::query()
            ->when($type === 'customer', fn ($query) => $query->whereIn('type', ['customer', 'both']))
            ->when($type === 'supplier', fn ($query) => $query->whereIn('type', ['supplier', 'both']))
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Entity $entity): array => [
                'id' => $entity->id,
                'tax_id' => $entity->tax_id,
                'name' => $entity->name,
                'phone' => $entity->phone,
                'mobile' => $entity->mobile,
                'website' => $entity->website,
                'email' => $entity->email,
                'status' => $entity->status,
                'type' => $entity->type,
            ]);

        return Inertia::render('entities/Index', [
            'entities' => $entities,
            'filters' => [
                'type' => in_array($type, ['customer', 'supplier', 'both'], true) ? $type : 'both',
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
            'defaultType' => in_array($type, ['customer', 'supplier', 'both'], true) ? $type : 'customer',
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
                'tax_id' => $entity->tax_id,
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
                'tax_id' => $entity->tax_id,
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
            'tax_id' => ['required', 'string', 'regex:/^\d{9}$/'],
            'country_code' => ['nullable', 'string', 'size:2'],
        ]);

        $countryCode = strtoupper((string) ($validated['country_code'] ?? 'PT'));
        $vatNumber = preg_replace('/\D+/', '', (string) $validated['tax_id']);

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
            'tax_id' => $validated['tax_id'],
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
}
