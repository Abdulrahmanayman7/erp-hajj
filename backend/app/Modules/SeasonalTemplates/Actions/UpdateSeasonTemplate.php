<?php

namespace App\Modules\SeasonalTemplates\Actions;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonalTemplates\Exceptions\SeasonalTemplatesDomainException;
use App\Modules\SeasonalTemplates\Models\SeasonTemplate;
use App\Modules\SeasonalTemplates\Support\TemplateItemWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateSeasonTemplate
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TemplateItemWriter $items,
    ) {}

    /**
     * Only draft templates are editable; approved/archived templates are immutable.
     *
     * @param  array<string, mixed>  $data  name?, description?, items?[]
     */
    public function execute(User $actor, SeasonTemplate $template, array $data, Request $request): SeasonTemplate
    {
        $this->tenantContext->require();

        $items = array_key_exists('items', $data)
            ? $this->items->normalize((array) $data['items'])
            : null;

        return DB::transaction(function () use ($template, $data, $items): SeasonTemplate {
            $locked = SeasonTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isEditable()) {
                throw SeasonalTemplatesDomainException::invalidTransition('لا يمكن تعديل قالب غير مسودة.');
            }

            if (array_key_exists('name', $data)) {
                $locked->name = trim((string) $data['name']);
            }
            if (array_key_exists('description', $data)) {
                $locked->description = $data['description'];
            }
            $locked->save();

            if ($items !== null) {
                $this->items->replace($locked, $items);
            }

            return $locked->load(['items', 'createdBy', 'approvedBy']);
        });
    }
}
