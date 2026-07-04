<?php

namespace App\Http\Requests\Installer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'min:2', 'max:100'],
            'app_url' => ['required', 'url', 'max:255'],
            'app_locale' => ['required', 'string', Rule::in(array_keys($this->supportedLocales()))],
            'app_timezone' => ['required', 'string', 'timezone:all'],
        ];
    }

    public function attributes(): array
    {
        return [
            'app_name' => 'application name',
            'app_url' => 'application URL',
            'app_locale' => 'locale',
            'app_timezone' => 'timezone',
        ];
    }

    private function supportedLocales(): array
    {
        return ['en' => 'English', 'bg' => 'Bulgarian', 'de' => 'German', 'fr' => 'French', 'pl' => 'Polish'];
    }
}
