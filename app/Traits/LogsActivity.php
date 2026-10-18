<?php

namespace App\Traits;

use App\Services\ActivityLogService;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait to automatically record model events in the ActivityLog.
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model) {
            $service = app(ActivityLogService::class);
            $event = static::getActivityLogEventPrefix().'.created';
            $description = static::getActivityLogModelName().' created: '.static::getActivityLogIdentifier($model);

            $service->log(
                event: $event,
                description: $description,
                subject: $model,
                properties: ['attributes' => $model->attributesToArray()],
            );
        });

        static::updated(function (Model $model) {
            $service = app(ActivityLogService::class);
            $event = static::getActivityLogEventPrefix().'.updated';
            $description = static::getActivityLogModelName().' updated: '.static::getActivityLogIdentifier($model);

            $changes = $model->getChanges();
            // Don't log if only timestamps changed
            unset($changes['updated_at']);
            if (empty($changes)) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $changes);

            $service->log(
                event: $event,
                description: $description,
                subject: $model,
                properties: [
                    'old' => $original,
                    'new' => $changes,
                ],
            );
        });

        static::deleted(function (Model $model) {
            $service = app(ActivityLogService::class);
            $event = static::getActivityLogEventPrefix().'.deleted';
            $description = static::getActivityLogModelName().' deleted: '.static::getActivityLogIdentifier($model);

            $service->log(
                event: $event,
                description: $description,
                subject: $model,
                properties: ['attributes' => $model->attributesToArray()],
            );
        });
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
