<?php

namespace App\Traits;

use App\Services\ActivityLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Records create/update/delete of a model in the ActivityLog, with old → new
 * values on updates.
 *
 * Only changes made by a signed-in person through the admin panel are
 * recorded — background jobs and members editing their own profile would
 * otherwise drown the log.
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model) {
            if (! static::shouldLogActivity()) {
                return;
            }

            app(ActivityLogService::class)->log(
                event: static::getActivityLogEventPrefix().'.created',
                description: static::getActivityLogModelName().' created: '.static::getActivityLogIdentifier($model),
                subject: $model,
                properties: ['attributes' => static::redactActivityValues($model, static::withoutIgnoredAttributes($model->attributesToArray()))],
            );
        });

        static::updated(function (Model $model) {
            if (! static::shouldLogActivity()) {
                return;
            }

            $changes = static::withoutIgnoredAttributes($model->getChanges());
            if (empty($changes)) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $changes);

            app(ActivityLogService::class)->log(
                event: static::getActivityLogEventPrefix().'.updated',
                description: static::getActivityLogModelName().' updated: '.static::getActivityLogIdentifier($model),
                subject: $model,
                properties: [
                    'old' => static::redactActivityValues($model, $original),
                    'new' => static::redactActivityValues($model, $changes),
                ],
            );
        });

        static::deleted(function (Model $model) {
            if (! static::shouldLogActivity()) {
                return;
            }

            app(ActivityLogService::class)->log(
                event: static::getActivityLogEventPrefix().'.deleted',
                description: static::getActivityLogModelName().' deleted: '.static::getActivityLogIdentifier($model),
                subject: $model,
                properties: ['attributes' => static::redactActivityValues($model, static::withoutIgnoredAttributes($model->attributesToArray()))],
            );
        });
    }

    protected static function shouldLogActivity(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        return app()->runningInConsole() || request()->routeIs('admin.*');
    }

    /** Attributes whose churn isn't an admin decision (timestamps, presence, counters). */
    protected static function getActivityLogIgnoredAttributes(): array
    {
        return [];
    }

    /** Whether this record's values must not be written to the log (stored secrets). */
    protected static function shouldRedactActivityValues(Model $model): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected static function withoutIgnoredAttributes(array $attributes): array
    {
        $ignored = array_merge(['created_at', 'updated_at'], static::getActivityLogIgnoredAttributes());

        return array_diff_key($attributes, array_flip($ignored));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected static function redactActivityValues(Model $model, array $values): array
    {
        return static::shouldRedactActivityValues($model)
            ? array_map(fn () => '[redacted]', $values)
            : $values;
    }

    protected static function getActivityLogEventPrefix(): string
    {
        return strtolower(class_basename(static::class));
    }

    protected static function getActivityLogModelName(): string
    {
        return class_basename(static::class);
    }

    protected static function getActivityLogIdentifier(Model $model): string
    {
        return (string) ($model->getAttribute('name')
            ?? $model->getAttribute('title')
            ?? $model->getAttribute('username')
            ?? $model->getKey());
    }
}
