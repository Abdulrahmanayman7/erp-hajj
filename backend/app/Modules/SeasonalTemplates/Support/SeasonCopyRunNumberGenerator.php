<?php

namespace App\Modules\SeasonalTemplates\Support;

use App\Core\Tenancy\TenantContext;

/**
 * Allocates CPY-###### per tenant. Numbers are never reused.
 */
final class SeasonCopyRunNumberGenerator
{
    private const TABLE = 'season_copy_run_number_sequences';

    private const PREFIX = 'CPY-';

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SequenceAllocator $sequences,
    ) {}

    public function next(): string
    {
        $value = $this->sequences->next(self::TABLE, $this->tenantContext->require()->id);

        return self::PREFIX.str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }
}
