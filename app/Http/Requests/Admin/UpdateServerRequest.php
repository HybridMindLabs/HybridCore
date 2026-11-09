<?php

namespace App\Http\Requests\Admin;

use App\Models\Server;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServerRequest extends FormRequest
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
        $server = $this->route('server');
        $serverId = $server instanceof Server ? $server->id : $server;

        return [
            'game_id' => ['required', 'exists:games,id'],
            'ip' => [
                'required', 'string', 'max:45',
                Rule::unique('servers')->ignore($serverId)->where(fn ($q) => $q->where('port', $this->input('port'))),
            ],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'query_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'display_address' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:30'],
            'is_active' => ['boolean'],
            'is_visible' => ['boolean'],
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
