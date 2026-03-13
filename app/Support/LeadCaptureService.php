<?php

namespace App\Support;

use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\LeadForm;
use App\Models\LeadFormSubmission;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeadCaptureService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function capture(
        LeadForm $leadForm,
        array $payload,
        string $sourceType,
        ?string $sourceUrl,
        ?string $sourceOrigin,
        ?string $ipAddress,
        ?string $userAgent
    ): LeadFormSubmission {
        return DB::transaction(function () use ($leadForm, $payload, $sourceType, $sourceUrl, $sourceOrigin, $ipAddress, $userAgent): LeadFormSubmission {
            $tenantId = (int) $leadForm->tenant_id;
            $roleId = $this->ensureLeadRole($tenantId);
            $payload = $this->normalizePayload($payload);

            [$firstName, $lastName] = $this->splitFullName((string) ($payload['full_name'] ?? ''));
            $email = $this->cleanNullableString($payload['email'] ?? null);
            $phone = $this->cleanNullableString($payload['phone'] ?? null);
            $company = $this->cleanNullableString($payload['company'] ?? null);
            $message = $this->cleanNullableString($payload['message'] ?? null);
            $customFieldsSummary = $this->summarizeCustomFields($payload);

            $existingContact = null;

            if ($email !== null) {
                $existingContact = Contact::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                    ->first();
            }

            if ($existingContact !== null) {
                $existingContact->update([
                    'mobile' => $existingContact->mobile ?: $phone,
                    'notes' => $this->appendNote((string) $existingContact->notes, $leadForm, $sourceType, $sourceOrigin, $company, $message, $customFieldsSummary),
                ]);

                $contact = $existingContact->fresh();
            } else {
                $contact = Contact::withoutGlobalScopes()->create([
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
                    'notes' => $this->appendNote('', $leadForm, $sourceType, $sourceOrigin, $company, $message, $customFieldsSummary),
                    'status' => 'active',
                ]);
            }

            return LeadFormSubmission::create([
                'lead_form_id' => $leadForm->id,
                'tenant_id' => $tenantId,
                'contact_id' => $contact?->id,
                'source_type' => $sourceType,
                'source_url' => $sourceUrl,
                'source_origin' => $sourceOrigin,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 512) : null,
                'captcha_passed' => true,
                'payload' => $payload,
                'submitted_at' => Carbon::now(),
            ]);
        });
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

    private function nextContactNumber(int $tenantId): int
    {
        $maxNumber = Contact::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->max('number');

        return ((int) $maxNumber) + 1;
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

    private function appendNote(
        string $existing,
        LeadForm $leadForm,
        string $sourceType,
        ?string $sourceOrigin,
        ?string $company,
        ?string $message,
        string $customFieldsSummary
    ): string {
        $lines = [];

        if (trim($existing) !== '') {
            $lines[] = trim($existing);
        }

        $lines[] = sprintf(
            '[Lead Form] %s | source: %s%s | captured at: %s',
            $leadForm->name,
            $sourceType,
            $sourceOrigin !== null ? ' ('.$sourceOrigin.')' : '',
            now()->format('Y-m-d H:i:s')
        );

        if ($company !== null && $company !== '') {
            $lines[] = 'Company: '.$company;
        }

        if ($message !== null && $message !== '') {
            $lines[] = 'Message: '.$message;
        }

        if ($customFieldsSummary !== '') {
            $lines[] = 'Custom fields: '.$customFieldsSummary;
        }

        return implode(PHP_EOL, $lines);
    }

    private function cleanNullableString(mixed $value): ?string
    {
        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        return collect($payload)
            ->map(function (mixed $value): mixed {
                if (is_bool($value) || is_int($value) || is_float($value)) {
                    return $value;
                }

                if ($value === null) {
                    return null;
                }

                $text = trim((string) $value);

                return $text === '' ? null : mb_substr($text, 0, 4000);
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function summarizeCustomFields(array $payload): string
    {
        /** @var Collection<string, mixed> $custom */
        $custom = collect($payload)->except(['full_name', 'email', 'phone', 'company', 'message']);

        return $custom
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(function (mixed $value, string $key): string {
                $label = str_replace('_', ' ', $key);
                $label = ucfirst(trim($label));

                if (is_bool($value)) {
                    return $label.': '.($value ? 'yes' : 'no');
                }

                return $label.': '.(string) $value;
            })
            ->values()
            ->implode(' | ');
    }
}
