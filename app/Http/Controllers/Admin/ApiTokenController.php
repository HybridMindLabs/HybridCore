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
        $accounts = ServiceAccount::with('tokens')->orderByDesc('created_at')->get()->map(fn (ServiceAccount $account) => [
            'id' => $account->id,
            'name' => $account->name,
            'created_at' => $account->created_at->toDateTimeString(),
            'tokens' => $account->tokens->map(fn (PersonalAccessToken $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->toDateTimeString(),
                'expires_at' => $token->expires_at?->toDateTimeString(),
                'created_at' => $token->created_at->toDateTimeString(),
            ]),
        ]);

        return Inertia::render('Admin/ApiTokens/Index', [
            'accounts' => $accounts,
            'available_abilities' => $this->abilities->all(),
        ]);
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

    public function revokeToken(PersonalAccessToken $token): RedirectResponse
    {
        abort_unless($token->tokenable_type === ServiceAccount::class, 404);

        $account = $token->tokenable;

        $this->activityLog->log(
            'service_account.token_revoked',
            "Revoked a token".($account ? " for \"{$account->name}\"" : ''),
            $account instanceof ServiceAccount ? $account : null,
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
