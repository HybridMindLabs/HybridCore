<?php

namespace Tests\Unit;

use App\Services\GitUpdateVerifier;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Builds two real, throwaway git repos (a "remote" and a clone of it) per
 * test, so the check runs against real `git fetch`/`git verify-commit`
 * behaviour rather than a mock of it.
 */
class GitUpdateVerifierTest extends TestCase
{
    private string $remote;

    private string $local;

    protected function setUp(): void
    {
        parent::setUp();

        $base = sys_get_temp_dir().'/hc-gitverify-'.uniqid();
        $this->remote = $base.'/remote';
        $this->local = $base.'/local';

        mkdir($this->remote, 0755, true);
        $this->runGit($this->remote, ['git', 'init', '--initial-branch=main']);
        $this->runGit($this->remote, ['git', 'config', 'user.email', 'test@example.com']);
        $this->runGit($this->remote, ['git', 'config', 'user.name', 'Test']);
        file_put_contents($this->remote.'/file.txt', 'v1');
        $this->runGit($this->remote, ['git', 'add', '.']);
        $this->runGit($this->remote, ['git', 'commit', '-m', 'initial']);

        $this->runGit(sys_get_temp_dir(), ['git', 'clone', $this->remote, $this->local]);
        $this->runGit($this->local, ['git', 'config', 'user.email', 'test@example.com']);
        $this->runGit($this->local, ['git', 'config', 'user.name', 'Test']);
    }

    protected function tearDown(): void
    {
        $this->runGit(sys_get_temp_dir(), ['rm', '-rf', dirname($this->remote)]);
        config(['hybridcore.require_signed_updates' => false]);

        parent::tearDown();
    }

    private function addUnsignedCommit(): void
    {
        file_put_contents($this->remote.'/file.txt', 'v2');
        $this->runGit($this->remote, ['git', 'commit', '-am', 'second, unsigned']);
    }

    public function test_disabled_by_default_config(): void
    {
        $this->assertFalse((new GitUpdateVerifier)->requireSignedCommits());
    }

    public function test_does_nothing_when_disabled_even_with_an_unsigned_incoming_commit(): void
    {
        config(['hybridcore.require_signed_updates' => false]);
        $this->addUnsignedCommit();
        $this->runGit($this->local, ['git', 'fetch', 'origin']);

        (new GitUpdateVerifier)->assertIncomingCommitsAreSigned($this->local);
        $this->addToAssertionCount(1); // reaching here without an exception is the assertion
    }

    public function test_does_nothing_when_there_are_no_incoming_commits(): void
    {
        config(['hybridcore.require_signed_updates' => true]);
        $this->runGit($this->local, ['git', 'fetch', 'origin']);

        (new GitUpdateVerifier)->assertIncomingCommitsAreSigned($this->local);
        $this->addToAssertionCount(1);
    }

    public function test_rejects_an_unsigned_incoming_commit_when_enabled(): void
    {
        config(['hybridcore.require_signed_updates' => true]);
        $this->addUnsignedCommit();
        $this->runGit($this->local, ['git', 'fetch', 'origin']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('could not be verified');

        (new GitUpdateVerifier)->assertIncomingCommitsAreSigned($this->local);
    }

    /** @param array<int, string> $command */
    private function runGit(string $cwd, array $command): void
    {
        $process = new Process($command, $cwd);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->fail("Setup command failed: {$process->getErrorOutput()}");
        }
    }
}
