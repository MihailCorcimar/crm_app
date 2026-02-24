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

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('permission_groups', 'name')->ignore($permissionGroup?->id),
            ],
            'menu_a_create' => ['boolean'],
            'menu_a_read' => ['boolean'],
            'menu_a_update' => ['boolean'],
            'menu_a_delete' => ['boolean'],
            'menu_b_create' => ['boolean'],
            'menu_b_read' => ['boolean'],
            'menu_b_update' => ['boolean'],
            'menu_b_delete' => ['boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
