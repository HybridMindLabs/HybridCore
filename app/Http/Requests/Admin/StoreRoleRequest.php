<?php

namespace App\Http\Requests\Admin;

use App\Support\AvailableIcons;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('roles', 'slug')],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['required', 'string', Rule::in(AvailableIcons::ROLE_ICONS)],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,slug'],
        ];
    }
}
