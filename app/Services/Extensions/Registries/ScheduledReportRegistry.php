<?php

namespace App\Services\Extensions\Registries;

use Closure;

/**
 * Lets extensions contribute rows to the weekly community email digest. Each
 * provider returns pre-localized rows; the digest command escapes and renders
 * them as list items (extensions never inject raw HTML into the email).
 *
 *   $registry->scheduledReports()->register(
 *       fn () => [
 *           ['text' => trans('vote::messages.digest_top', ['name' => $top]), 'url' => url('/vote')],
 *       ],
 *   );
 *
 * A row is: ['text' => string, 'url' => ?string].
 */
class ScheduledReportRegistry
{
    /** @var array<int, Closure> */
    private array $providers = [];

    /**
     * @param  Closure(): array<int, array{text: string, url?: string|null}>  $resolver
     */
    public function register(Closure $resolver): void
    {
        $this->providers[] = $resolver;
    }

    /**
     * Run every provider and return the merged rows. A provider that throws is
     * silently skipped — a broken digest section must not stop the mail-out.
     *
     * @return array<int, array{text: string, url: string|null}>
     */
    public function collect(): array
    {
        $rows = [];
        foreach ($this->providers as $resolver) {
            try {
                $result = $resolver();
            } catch (\Throwable) {
                continue;
            }

            foreach ((is_array($result) ? $result : []) as $row) {
                if (is_array($row) && isset($row['text']) && is_string($row['text'])) {
                    $rows[] = ['text' => $row['text'], 'url' => $row['url'] ?? null];
                }
            }
        }

        return $rows;
    }

    /** @return array<int, Closure> */
    public function all(): array
    {
        return $this->providers;
    }
}
