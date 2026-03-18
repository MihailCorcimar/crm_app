<?php

namespace App\Http\Requests\Access;

use App\Models\PermissionGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PermissionGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var PermissionGroup|null $permissionGroup */
        $permissionGroup = $this->route('permission_group');

        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('permission_groups', 'name')->ignore($permissionGroup?->id),
            ],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];

        foreach (PermissionGroup::permissionColumns() as $column) {
            $rules[$column] = ['boolean'];
        }

        return $rules;
    }
}
