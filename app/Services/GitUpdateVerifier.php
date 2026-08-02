<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Optional, opt-in signature gate on the core self-update's `git pull`.
 *
 * HTTPS to GitHub only proves the bytes weren't altered in transit — it says
 * nothing if the maintainer's GitHub account, DNS, or the repo itself is
 * compromised after the fact. Enabling require_signed_updates makes every
 * commit about to be merged require a signature `git verify-commit` can
 * confirm against a key already in the server's keyring, so trust is
 * anchored to whichever key the operator chose to import — not to whatever
 * currently answers on the wire.
 *
 * Shared by both self-update entry points (admin panel and the
 * `hybridcore:update` CLI command) so there is exactly one place this check
 * can be forgotten in.
 */
class GitUpdateVerifier
{
    public function requireSignedCommits(): bool
    {
        return (bool) config('hybridcore.require_signed_updates');
    }

    /**
     * Verify every commit between HEAD and the already-fetched remote branch
     * tip. Call this after `git fetch`, before `git pull`/`git merge` — a
     * no-op unless require_signed_updates is enabled.
     *
     * @throws RuntimeException when verification is required and any incoming commit fails it.
     */
    public function assertIncomingCommitsAreSigned(string $repoPath): void
    {
        if (! $this->requireSignedCommits()) {
            return;
        }

        $branch = $this->output($repoPath, ['git', 'rev-parse', '--abbrev-ref', 'HEAD']);
        $hashes = array_filter(explode(
            "\n",
            $this->output($repoPath, ['git', 'rev-list', "HEAD..origin/{$branch}"]),
        ));

        foreach ($hashes as $hash) {
            $process = new Process(['git', 'verify-commit', $hash], $repoPath);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(
                    "Commit {$hash} could not be verified (git verify-commit failed) — the update was not applied. ".
                    'Import the signer\'s GPG key into the server\'s keyring, or disable HYBRIDCORE_REQUIRE_SIGNED_UPDATES if this is expected.',
                );
            }
        }
    }

    /** @param array<int, string> $command */
    private function output(string $cwd, array $command): string
    {
        $process = new Process($command, $cwd);
        $process->run();

        return trim($process->getOutput());
    }
}
