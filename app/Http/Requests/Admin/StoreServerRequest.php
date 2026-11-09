<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'game_id' => ['required', 'exists:games,id'],
            'ip' => [
                'required', 'string', 'max:45',
                Rule::unique('servers')->where(fn ($q) => $q->where('port', $this->input('port'))),
            ],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'query_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'is_visible' => ['nullable', 'boolean'],
            'display_address' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ip.unique' => 'A server with this IP and port already exists.',
        ];
    }
}
