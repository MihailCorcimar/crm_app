<?php

namespace App\Support;

use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\Country;
use App\Models\Deal;
use App\Models\Entity;
use App\Models\LeadForm;
use App\Models\LeadFormSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeadFormSubmissionConversionService
{
    /**
     * @param  array{
     *   create_deal: bool,
     *   entity_name_field_key: string|null,
     *   deal_title_field_key: string|null,
     *   deal_title_template: string,
     *   deal_value_field_key: string|null,
     *   deal_stage: string,
     *   deal_owner_id: int|null,
     *   deal_probability: int
     * }  $settings
     */
    public function convert(LeadForm $leadForm, LeadFormSubmission $submission, array $settings, User $actor): LeadFormSubmission
    {
        return DB::transaction(function () use ($leadForm, $submission, $settings, $actor): LeadFormSubmission {
            /** @var LeadFormSubmission $locked */
            $locked = LeadFormSubmission::query()
                ->whereKey($submission->id)
                ->lockForUpdate()
                ->firstOrFail();

            $tenantId = (int) $leadForm->tenant_id;
            $payload = is_array($locked->payload) ? $locked->payload : [];

            $contact = $this->resolveContact($tenantId, $payload, $locked->contact);
            $entity = $this->resolveEntity($tenantId, $payload, $settings, $contact);

            if ($contact !== null && $entity !== null && (int) $contact->entity_id !== (int) $entity->id) {
                $contact->update(['entity_id' => $entity->id]);
            }

            $deal = null;
            if ($settings['create_deal']) {
                $deal = $this->createDeal($tenantId, $payload, $settings, $actor, $entity, $locked);
            }

            $locked->update([
                'contact_id' => $contact?->id,
                'entity_id' => $entity?->id,
                'deal_id' => $deal?->id,
                'status' => LeadFormSubmission::STATUS_CONVERTED,
                'converted_at' => now(),
                'converted_by' => $actor->id,
                'ignored_at' => null,
                'ignored_by' => null,
            ]);

            return $locked->fresh(['contact:id,first_name,last_name,email', 'entity:id,name', 'deal:id,title,stage']) ?? $locked;
        });
    }

    public function ignore(LeadFormSubmission $submission, User $actor): LeadFormSubmission
    {
        $submission->update([
            'status' => LeadFormSubmission::STATUS_IGNORED,
            'ignored_at' => now(),
            'ignored_by' => $actor->id,
        ]);

        return $submission->fresh(['contact:id,first_name,last_name,email', 'entity:id,name', 'deal:id,title,stage']) ?? $submission;
    }

    private function resolveContact(int $tenantId, array $payload, ?Contact $existing): ?Contact
    {
        if ($existing !== null) {
            return $existing;
        }

        $email = $this->cleanString($payload['email'] ?? null);
        if ($email !== null) {
            $matched = Contact::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                ->first();

            if ($matched !== null) {
                return $matched;
            }
        }

        $fullName = $this->cleanString($payload['full_name'] ?? null);
        $phone = $this->cleanString($payload['phone'] ?? null);

        if ($fullName === null && $email === null && $phone === null) {
            return null;
        }

        [$firstName, $lastName] = $this->splitFullName($fullName ?? 'Lead');
        $roleId = $this->ensureLeadRole($tenantId);

        return Contact::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'number' => $this->nextContactNumber($tenantId),
            'entity_id' => null,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'role_id' => $roleId,
            'phone' => null,
            'mobile' => $phone,
            'email' => $email,
            'gdpr_consent' => false,
            'notes' => '[Lead Form] Contacto criado na conversao manual.',
            'status' => 'active',
        ]);
    }

    /**
     * @param  array{
     *   create_deal: bool,
     *   entity_name_field_key: string|null,
     *   deal_title_field_key: string|null,
     *   deal_title_template: string,
     *   deal_value_field_key: string|null,
     *   deal_stage: string,
     *   deal_owner_id: int|null,
     *   deal_probability: int
     * }  $settings
     */
    private function resolveEntity(int $tenantId, array $payload, array $settings, ?Contact $contact): ?Entity
    {
        $entityName = $this->valueForKey($payload, $settings['entity_name_field_key']);

        if ($entityName === null && $contact !== null && $contact->entity_id !== null) {
            return Entity::withoutGlobalScopes()->find($contact->entity_id);
        }

        if ($entityName === null) {
            return null;
        }

        $existing = Entity::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($entityName)])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $countryId = $this->resolveCountryId($tenantId);
        if ($countryId === null) {
            return null;
        }

        $vat = $this->generateUniqueVat($tenantId);

        return Entity::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'type' => 'customer',
            'number' => $this->nextEntityNumber($tenantId),
            'tax_id' => $vat,
            'vat' => $vat,
            'name' => $entityName,
            'address' => null,
            'postal_code' => null,
            'city' => null,
            'country_id' => $countryId,
            'phone' => null,
            'mobile' => null,
            'website' => null,
            'email' => null,
            'gdpr_consent' => false,
            'notes' => '[Lead Form] Entidade criada na conversao manual.',
            'status' => 'active',
        ]);
    }

    /**
     * @param  array{
     *   create_deal: bool,
     *   entity_name_field_key: string|null,
     *   deal_title_field_key: string|null,
     *   deal_title_template: string,
     *   deal_value_field_key: string|null,
     *   deal_stage: string,
     *   deal_owner_id: int|null,
     *   deal_probability: int
     * }  $settings
     */
    private function createDeal(
        int $tenantId,
        array $payload,
        array $settings,
        User $actor,
        ?Entity $entity,
        LeadFormSubmission $submission
    ): Deal {
        $ownerId = $this->resolveOwnerId($tenantId, $settings['deal_owner_id'], (int) $actor->id);

        $titleFromField = $this->valueForKey($payload, $settings['deal_title_field_key']);
        $entityName = $entity?->name ?? ($this->valueForKey($payload, $settings['entity_name_field_key']) ?? 'Sem entidade');
        $fullName = $this->cleanString($payload['full_name'] ?? null) ?? 'Lead';
        $sourceType = trim((string) $submission->source_type);
        $sourceType = $sourceType !== '' ? $sourceType : 'public_page';

        $title = $titleFromField ?? $this->renderTitleTemplate(
            (string) $settings['deal_title_template'],
            $entityName,
            $fullName,
            $sourceType
        );

        if (trim($title) === '') {
            $title = 'Lead inbound';
        }

        $value = $this->parseDecimal($this->valueForKey($payload, $settings['deal_value_field_key'])) ?? 0.0;

        return Deal::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'entity_id' => $entity?->id,
            'person_id' => null,
            'title' => mb_substr($title, 0, 255),
            'value' => $value,
            'stage' => (string) $settings['deal_stage'],
            'probability' => (int) $settings['deal_probability'],
            'expected_close_date' => null,
            'owner_id' => $ownerId,
        ]);
    }

    private function resolveOwnerId(int $tenantId, ?int $preferredOwnerId, int $fallbackOwnerId): int
    {
        if ($preferredOwnerId !== null && DB::table('tenant_user')->where('tenant_id', $tenantId)->where('user_id', $preferredOwnerId)->exists()) {
            return $preferredOwnerId;
        }

        if (DB::table('tenant_user')->where('tenant_id', $tenantId)->where('user_id', $fallbackOwnerId)->exists()) {
            return $fallbackOwnerId;
        }

        $firstMemberId = DB::table('tenant_user')
            ->where('tenant_id', $tenantId)
            ->orderBy('user_id')
            ->value('user_id');

        if (is_numeric($firstMemberId)) {
            return (int) $firstMemberId;
        }

        return $fallbackOwnerId;
    }

    private function resolveCountryId(int $tenantId): ?int
    {
        $countryId = Country::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', 'PT')
            ->value('id');

        if (is_numeric($countryId)) {
            return (int) $countryId;
        }

        $fallback = Country::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->value('id');

        return is_numeric($fallback) ? (int) $fallback : null;
    }

    private function generateUniqueVat(int $tenantId): string
    {
        for ($attempt = 0; $attempt < 15; $attempt++) {
            $candidate = (string) random_int(100000000, 999999999);
            $exists = Entity::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where(function ($query) use ($candidate): void {
                    $query->where('vat', $candidate)
                        ->orWhere('tax_id', $candidate);
                })
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }

        return (string) random_int(100000000, 999999999);
    }

    private function nextContactNumber(int $tenantId): int
    {
        $max = Contact::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->max('number');

        return ((int) $max) + 1;
    }

    private function nextEntityNumber(int $tenantId): int
    {
        $max = Entity::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->max('number');

        return ((int) $max) + 1;
    }

    private function ensureLeadRole(int $tenantId): int
    {
        $role = ContactRole::withoutGlobalScopes()
            ->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'name' => 'Lead',
                ]
            );

        return (int) $role->id;
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function splitFullName(string $value): array
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return ['Lead', null];
        }

        $parts = preg_split('/\s+/', $trimmed) ?: [];
        $firstName = (string) array_shift($parts);
        $lastName = count($parts) > 0 ? implode(' ', $parts) : null;

        return [$firstName, $lastName];
    }

    private function cleanString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, 255);
    }

    private function valueForKey(array $payload, ?string $key): ?string
    {
        if ($key === null || trim($key) === '') {
            return null;
        }

        return $this->cleanString($payload[$key] ?? null);
    }

    private function parseDecimal(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        $normalized = preg_replace('/[^\d,.\-]/', '', $raw) ?? '';
        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function renderTitleTemplate(string $template, string $entityName, string $fullName, string $sourceType): string
    {
        return str_replace(
            ['{entity_name}', '{full_name}', '{source_type}'],
            [$entityName, $fullName, $sourceType],
            $template
        );
    }
}

