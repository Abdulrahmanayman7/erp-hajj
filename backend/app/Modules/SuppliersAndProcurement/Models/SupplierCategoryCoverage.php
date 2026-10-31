<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierCategoryCoverage extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $table = 'supplier_category_coverages';

    protected $fillable = [
        'supplier_id',
        'supplier_category_id',
        'geo_place_id',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SupplierCategory::class, 'supplier_category_id');
    }

    public function geoPlace(): BelongsTo
    {
        return $this->belongsTo(GeoPlace::class, 'geo_place_id');
    }
}
