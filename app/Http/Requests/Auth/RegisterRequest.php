<?php

namespace App\Http\Requests\Auth;

use App\Services\Auth\LoginSecurityService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest (rather than inline validation) so Laravel Precognition can
 * validate individual fields live while the user types — the precognitive
 * dispatcher only resolves controller parameters, it never runs the body.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(LoginSecurityService::class)->registrationEnabled();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            // Optional so API and older clients keep working; the form always sends it.
            'username' => ['nullable', 'string', 'min:3', 'max:32', 'regex:/^[a-z0-9_\-]+$/i', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', app(LoginSecurityService::class)->passwordRules()],
        ];
    }
}
