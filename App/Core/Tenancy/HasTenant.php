<?php

declare(strict_types=1);

namespace Core\Tenancy;

use Core\ModelQuery;

/**
 * Trait for Model subclasses that belong to a tenant.
 *
 * Adds a global scope that automatically filters by the current tenant ID.
 *
 *   class Post extends Model {
 *       use HasTenant;
 *       protected static string $table = 'posts';
 *       protected static string $tenantColumn = 'tenant_id';  // default
 *   }
 *
 *   // After TenantMiddleware resolves Tenant::id() = 3:
 *   Post::query()->get();   // WHERE tenant_id = 3 is applied automatically
 *   Post::query()->withoutGlobalScopes()->get();  // bypass if needed
 *
 *   // When saving a new record the tenant_id is auto-set:
 *   $post = new Post();
 *   $post->title = 'Hello';
 *   $post->Save();  // tenant_id filled in automatically
 */
trait HasTenant
{
    protected static string $tenantColumn = 'tenant_id';

    public static function bootHasTenant(): void
    {
        static::addGlobalScope('tenant', function (ModelQuery $q) {
            $id = Tenant::id();
            if ($id !== null) {
                $q->where(static::$tenantColumn, $id);
            }
        });
    }

    /** Call in the subclass constructor or a static initializer to activate the scope. */
    public static function initializeTenantScope(): void
    {
        static::bootHasTenant();
    }

    /** Automatically populate tenant_id before saving a new record. */
    public function Save(): bool
    {
        if (!$this->exists && Tenant::id() !== null) {
            $col = static::$tenantColumn;
            if (array_key_exists($col, $this->schema ?? [])) {
                $this->$col = Tenant::id();
            }
        }
        return parent::Save();
    }
}
