<?php

namespace App\Http\Requests\Account;

use App\Services\Auth\LoginSecurityService;
use Illuminate\Foundation\Http\FormRequest;

/** FormRequest so Precognition can validate fields live while typing. */
class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // The built-in current_password rule checks against the
            // authenticated user's password — live, on blur.
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', app(LoginSecurityService::class)->passwordRules()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.current_password' => __('account.wrong_current_password'),
        ];
    }
}
