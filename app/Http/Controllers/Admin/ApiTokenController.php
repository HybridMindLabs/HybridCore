<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceAccount;
use App\Services\ActivityLogService;
use App\Services\Extensions\Registries\AbilityRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    public function __construct(
        private readonly AbilityRegistry $abilities,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(): Response
    {
        $accounts = ServiceAccount::with('tokens')->orderByDesc('created_at')->get()
            ->map(fn (ServiceAccount $account) => $this->accountToArray($account))
            ->values()->all();

        return Inertia::render('Admin/ApiTokens/Index', [
            'accounts' => $accounts,
            'available_abilities' => $this->abilities->all(),
        ]);
    }

    /**
     * @return array{id: int, name: string, created_at: string, tokens: array<int, array{id: int, name: string, abilities: array<int, string>, last_used_at: string|null, expires_at: string|null, created_at: string}>}
     */
    private function accountToArray(ServiceAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'created_at' => $account->created_at->toDateTimeString(),
            'tokens' => $account->tokens->map(fn (PersonalAccessToken $token) => $this->tokenToArray($token))->values()->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, abilities: array<int, string>, last_used_at: string|null, expires_at: string|null, created_at: string, is_expired: bool, expires_soon: bool}
     */
    private function tokenToArray(PersonalAccessToken $token): array
    {
        return [
            'id' => $token->id,
            'name' => $token->name,
            'abilities' => $token->abilities ?? [],
            'last_used_at' => $token->last_used_at?->toDateTimeString(),
            'expires_at' => $token->expires_at?->toDateTimeString(),
            'created_at' => $token->created_at->toDateTimeString(),
            // Computed server-side so the admin UI never has to parse dates
            // or reason about timezones itself.
            'is_expired' => (bool) $token->expires_at?->isPast(),
            'expires_soon' => $token->expires_at !== null
                && $token->expires_at->isFuture()
                && $token->expires_at->diffInDays(now(), absolute: true) <= 7,
        ];
    }

    private function abilityRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', 'in:'.implode(',', array_keys($this->abilities->all()))],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /** Create a new service account with its first token. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->abilityRules());

        $account = ServiceAccount::create([
            'name' => $data['name'],
            'created_by' => $request->user()->id,
        ]);

        $token = $account->createToken(
            $data['name'],
            $data['abilities'],
            isset($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : null,
        );

        $this->activityLog->log('service_account.created', "Created service account \"{$account->name}\"", $account);

        return back()->with('success', "\"{$account->name}\" created.")->with('plain_token', $token->plainTextToken);
    }

    /** Issue another token on an existing service account. */
    public function issueToken(Request $request, ServiceAccount $serviceAccount): RedirectResponse
    {
        $data = $request->validate($this->abilityRules());

        $token = $serviceAccount->createToken(
            $data['name'],
            $data['abilities'],
            isset($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : null,
        );

        $this->activityLog->log('service_account.token_issued', "Issued a new token for \"{$serviceAccount->name}\"", $serviceAccount);

        return back()->with('success', 'Token issued.')->with('plain_token', $token->plainTextToken);
    }

    /**
     * Revoke $token and immediately issue a replacement with the same name
     * and abilities — the credential changes, the grant doesn't. Any
     * expiry is not carried over; reissue one from the account panel if
     * the rotated token needs to expire too.
     */
    public function rotateToken(PersonalAccessToken $token): RedirectResponse
    {
        abort_unless($token->tokenable_type === ServiceAccount::class, 404);

        $account = $token->tokenable;
        abort_unless($account instanceof ServiceAccount, 404);

        $name = $token->name;
        $abilities = $token->abilities ?? [];

        $token->delete();
        $newToken = $account->createToken($name, $abilities);

        $this->activityLog->log('service_account.token_rotated', "Rotated a token for \"{$account->name}\"", $account);

        return back()->with('success', 'Token rotated — the old one no longer works.')->with('plain_token', $newToken->plainTextToken);
    }

    public function revokeToken(PersonalAccessToken $token): RedirectResponse
    {
        abort_unless($token->tokenable_type === ServiceAccount::class, 404);

        $account = $token->tokenable instanceof ServiceAccount ? $token->tokenable : null;

        $this->activityLog->log(
            'service_account.token_revoked',
            'Revoked a token'.($account ? " for \"{$account->name}\"" : ''),
            $account,
        );

        $token->delete();

        return back()->with('success', 'Token revoked.');
    }

    public function destroy(ServiceAccount $serviceAccount): RedirectResponse
    {
        $name = $serviceAccount->name;

        // Sanctum's personal_access_tokens table is polymorphic with no DB-level
        // foreign key to tokenable_id/tokenable_type — nothing cascades on its
        // own, so a token issued before this delete would otherwise survive as
        // an orphan still able to authenticate.
        $serviceAccount->tokens()->delete();
        $serviceAccount->delete();

        $this->activityLog->log('service_account.deleted', "Deleted service account \"{$name}\"");

        return redirect()->route('admin.api-tokens.index')->with('success', "\"{$name}\" deleted.");
    }
}
