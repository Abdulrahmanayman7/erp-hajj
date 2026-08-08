<?php

namespace Tests\Support;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only tenant-owned model with soft deletes. No business model in the
 * Tenant Foundation uses soft deletes yet, but the documented trait
 * behavior "soft-deleted records remain tenant-scoped" must be proven now.
 */
class ScopedItem extends Model implements TenantOwned
{
    use SoftDeletes;
    use UsesTenantScope;

    protected $table = 'scoped_items';

    protected $fillable = ['name'];

    public static function migrate(): void
    {
        if (Schema::hasTable('scoped_items')) {
            return;
        }

        Schema::create('scoped_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'name']);
        });
    }
}
