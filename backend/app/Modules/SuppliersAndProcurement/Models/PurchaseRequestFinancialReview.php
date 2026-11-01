<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestFinancialReviewOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestFinancialReview extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'justification',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => PurchaseRequestFinancialReviewOutcome::class,
            'version_no' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
