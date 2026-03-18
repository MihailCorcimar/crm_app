<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ChatQueryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $rawSessionId = $this->input('session_id');

        if (! is_string($rawSessionId)) {
            $this->merge(['session_id' => null]);

            return;
        }

        $sessionId = trim($rawSessionId);
        if (
            $sessionId === ''
            || in_array(Str::lower($sessionId), ['legacy', 'null', 'undefined'], true)
            || ! Str::isUuid($sessionId)
        ) {
            $this->merge(['session_id' => null]);

            return;
        }

        $this->merge(['session_id' => $sessionId]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:1000'],
            'session_id' => ['nullable', 'uuid'],
        ];
    }
}
