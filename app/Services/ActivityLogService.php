<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ActivityLogService
{
    /** Property keys that must never be written to the activity log. */
    private const SENSITIVE_KEYS = ['password', 'password_confirmation', 'secret', 'token', 'api_key', 'app_key', 'db_password', 'mail_password',
        'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'bridge_token_hash'];

    public function log(
        string $event,
        string $description,
        ?Model $subject = null,
        ?array $properties = null,
    ): void {
        try {
            if (! Schema::hasTable((new ActivityLog)->getTable())) {
                return;
            }
        } catch (\Throwable) {
            // No reachable database — Schema::hasTable() throws rather than
            // returning false. Logging is best-effort and must never break the
            // action it is recording.
            return;
        }

        $user = Auth::user();
        $request = request();

        ActivityLog::create([
            'event' => $event,
            'description' => $description,
            'causer_type' => $user ? get_class($user) : null,
            'causer_id' => $user?->getKey(),
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties !== null ? $this->scrub($properties) : null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);
    }

    /**
     * Recursively mask sensitive values in logged properties. The key stays so
     * the log still shows that a secret changed — only its value is hidden.
     */
    private function scrub(array $properties): array
    {
        foreach ($properties as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $properties[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $properties[$key] = $this->scrub($value);
            }
        }

        return $properties;
    }
}
