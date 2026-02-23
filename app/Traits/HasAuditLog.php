<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Trait HasAuditLog
 *
 * Enregistre automatiquement chaque création, modification
 * et suppression dans la table audit_logs.
 */
trait HasAuditLog
{
    protected static function bootHasAuditLog(): void
    {
        static::created(function ($model) {
            static::logAction($model, 'created', [], $model->getAttributes());
        });

        static::updated(function ($model) {
            static::logAction($model, 'updated', $model->getOriginal(), $model->getChanges());
        });

        static::deleted(function ($model) {
            static::logAction($model, 'deleted', $model->getAttributes(), []);
        });
    }

    private static function logAction($model, string $event, array $old, array $new): void
    {
        try {
            AuditLog::create([
                'user_id'    => Auth::id(),
                'tenant_id'  => $model->tenant_id ?? app('current_tenant_id', null),
                'action'     => strtolower(class_basename($model)) . '.' . $event,
                'model_type' => get_class($model),
                'model_id'   => $model->getKey(),
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        } catch (\Exception $e) {
            // Ne jamais bloquer l'action métier si l'audit échoue
            \Log::warning('Audit log failed: ' . $e->getMessage());
        }
    }
}
