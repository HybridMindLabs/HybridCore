<?php

namespace App\Http\Requests\Account;

use App\Services\SettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** FormRequest so Precognition can validate fields live while typing. */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'display_name' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'bio' => ['nullable', 'string', 'max:500'],
            'location' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'],
            'profile_privacy' => ['required', 'in:public,members,private'],
        ];

        if ($this->has('username')) {
            $rules['username'] = [
                'required', 'string', 'min:3', 'max:32',
                'regex:/^[a-z0-9_\-]+$/i',
                Rule::unique('users', 'username')->ignore($this->user()->id),
            ];
        }

        return $rules;
    }

    /** Username change cooldown — enforced as a validation error so it surfaces live too. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('username') || $this->input('username') === $this->user()->username) {
                return;
            }

            if (! $this->user()->canChangUsername()) {
                $validator->errors()->add('username', __('account.username_cooldown', [
                    'date' => $this->user()->username_changed_at
                        ->addDays((int) app(SettingsService::class)->get('username_change_cooldown_days', 30))
                        ->toFormattedDateString(),
                ]));
            }
        });
    }
}
