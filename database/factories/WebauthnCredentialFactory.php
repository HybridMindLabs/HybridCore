<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WebauthnCredential;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<WebauthnCredential> */
class WebauthnCredentialFactory extends Factory
{
    protected $model = WebauthnCredential::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Test Passkey',
            'credential_id' => Str::random(32),
            'public_key' => "-----BEGIN PUBLIC KEY-----\ntest\n-----END PUBLIC KEY-----",
            'sign_counter' => 0,
        ];
    }
}
