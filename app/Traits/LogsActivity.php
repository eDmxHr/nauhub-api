<?php

namespace App\Traits;

use App\Models\ActivityLog;

trait LogsActivity
{
    /**
     * Boot the trait
     */
    protected static function bootLogsActivity()
    {
        // Log when a model is created
        static::created(function ($model) {
            $model->logActivity('created', "{$model->getEntityName()} created");
        });

        // Log when a model is updated
        static::updated(function ($model) {
            $changes = $model->getChanges();
            unset($changes['updated_at']); // Don't log timestamp changes

            if (!empty($changes)) {
                $model->logActivity(
                    'updated',
                    "{$model->getEntityName()} updated",
                    ['changes' => $changes, 'original' => $model->getOriginal()]
                );
            }
        });

        // Log when a model is deleted
        static::deleted(function ($model) {
            $model->logActivity('deleted', "{$model->getEntityName()} deleted");
        });
    }

    /**
     * Log an activity for this model
     */
    public function logActivity(
        string $action,
        string $description,
        ?array $metadata = null,
        string $severity = 'info'
    ): ActivityLog {
        return ActivityLog::log(
            action: $action,
            description: $description,
            entityType: class_basename($this),
            entityId: $this->id ?? null,
            metadata: $metadata,
            severity: $severity
        );
    }

    /**
     * Get human-readable entity name
     */
    protected function getEntityName(): string
    {
        $className = class_basename($this);

        // Add entity identifier if available
        if (isset($this->title)) {
            return "{$className} '{$this->title}'";
        } elseif (isset($this->name)) {
            return "{$className} '{$this->name}'";
        } elseif (isset($this->email)) {
            return "{$className} '{$this->email}'";
        }

        return $className;
    }
}
