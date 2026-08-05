<?php

namespace Tests\Unit\Models;

use App\Models\ServiceAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_service_account_can_issue_and_check_an_abilities_scoped_token(): void
    {
        $account = ServiceAccount::factory()->create(['name' => 'Discord Bot']);

        $token = $account->createToken('discord-bot', ['notifications:read']);

        $this->assertNotEmpty($token->plainTextToken);
        $this->assertTrue($token->accessToken->can('notifications:read'));
        $this->assertFalse($token->accessToken->can('notifications:write'));
    }

    public function test_a_service_account_belongs_to_the_admin_that_created_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $account = ServiceAccount::factory()->create(['created_by' => $admin->id]);

        $this->assertTrue($account->creator->is($admin));
    }
}
