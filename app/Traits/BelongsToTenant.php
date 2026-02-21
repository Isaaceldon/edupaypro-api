<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait BelongsToTenant
 *
 * Auto-scope toutes les requêtes Eloquent au tenant courant.
 * Appliqué à tous les modèles multi-tenant (School, Student, Payment, etc.)
 */
trait BelongsToTenant
{
    /**
     * Boot le trait : ajoute un global scope sur tenant_id.
     */
    protected static function bootBelongsToTenant(): void
    {
        // Auto-filtre par tenant_id si un tenant est résolu
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (app()->has('current_tenant_id')) {
                $builder->where(
                    $builder->getModel()->getTable() . '.tenant_id',
                    app('current_tenant_id')
                );
            }
        });

        // Auto-assigne le tenant_id à la création
        static::creating(function ($model) {
            if (empty($model->tenant_id) && app()->has('current_tenant_id')) {
                $model->tenant_id = app('current_tenant_id');
            }
        });
    }

    /**
     * Ignorer le scope tenant pour une requête (admin système).
     */
    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope('tenant');
    }
}
