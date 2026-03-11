<?php

namespace App\Http\Requests;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactMergeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Contact|null $primaryContact */
        $primaryContact = $this->route('contact');
        if ($primaryContact === null) {
            return false;
        }

        $duplicateId = (int) $this->input('duplicate_contact_id');
        if ($duplicateId <= 0 || $duplicateId === (int) $primaryContact->id) {
            return false;
        }

        $duplicateContact = Contact::query()
            ->whereKey($duplicateId)
            ->first();

        if ($duplicateContact === null) {
            return false;
        }

        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return $user->can('update', $primaryContact)
            && $user->can('update', $duplicateContact);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Contact|null $primaryContact */
        $primaryContact = $this->route('contact');
        $tenantId = (int) ($primaryContact?->tenant_id ?? 0);
        $primaryContactId = (int) ($primaryContact?->id ?? 0);

        return [
            'duplicate_contact_id' => [
                'required',
                'integer',
                Rule::notIn([$primaryContactId]),
                Rule::exists('contacts', 'id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ];
    }
}
