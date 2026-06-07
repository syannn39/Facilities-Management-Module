<?php

namespace App\Traits;

use App\Models\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 *
 * @method static void addGlobalScope(mixed $scope, mixed $implementation = null)
 * @method static void creating(callable $callback)
 */
trait BelongsToTenant
{
    /**
     * Boot the trait and apply the global tenant isolation scope.
     */
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        // Automatically attach the logged-in user's tenant_id whenever a record is created
        static::creating(function ($model) {
            $user = Auth::user();

            if ($user && $user->tenant_id) {
                $model->tenant_id = $user->tenant_id;
            }
        });
    }
}