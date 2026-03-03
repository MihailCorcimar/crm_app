<?php

namespace App\Http\Requests;

use App\Models\Deal;
use Illuminate\Foundation\Http\FormRequest;

class DealProposalUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Deal|null $deal */
        $deal = $this->route('deal');

        return $deal !== null
            ? ($this->user()?->can('update', $deal) ?? false)
            : false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'proposal_file' => ['required', 'file', 'mimes:pdf,doc,docx,odt', 'max:10240'],
        ];
    }
}
