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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', app(LoginSecurityService::class)->passwordRules()],
        ];
    }
}
