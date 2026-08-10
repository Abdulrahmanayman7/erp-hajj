<?php

namespace App\Modules\Documents\Support;

use App\Modules\Contracts\Models\Contract;
use App\Modules\Decisions\Models\Decision;
use App\Modules\Documents\Enums\DocumentLinkableType;
use App\Modules\Documents\Exceptions\DocumentDomainException;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentCategory;
use App\Modules\Employees\Models\Employee;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Model;

final class DocumentReferenceValidator
{
    /**
     * @return array{0: string|null, 1: int|null}
     */
    public function resolveLink(?string $type, mixed $id): array
    {
        $hasType = $type !== null && $type !== '';
        $hasId = $id !== null && $id !== '';

        if (! $hasType && ! $hasId) {
            return [null, null];
        }

        if ($hasType xor $hasId) {
            throw DocumentDomainException::linkInvalid();
        }

        $alias = DocumentLinkableType::tryFromAlias($type);
        if ($alias === null) {
            throw DocumentDomainException::linkInvalid();
        }

        $targetId = (int) $id;
        $model = $this->findTarget($alias, $targetId);
        if ($model === null) {
            throw DocumentDomainException::linkInvalid();
        }

        return [$alias->value, (int) $model->getKey()];
    }

    public function resolveCategory(?int $categoryId, ?int $currentCategoryId = null): ?DocumentCategory
    {
        if ($categoryId === null) {
            return null;
        }

        $category = DocumentCategory::query()->whereKey($categoryId)->first();
        if ($category === null) {
            throw DocumentDomainException::categoryInvalid();
        }

        $keepingSame = $currentCategoryId !== null && (int) $category->id === (int) $currentCategoryId;
        if (! $keepingSame && ! $category->is_active) {
            throw DocumentDomainException::categoryInvalid();
        }

        return $category;
    }

    public function assertNoDocumentsLinked(DocumentLinkableType $type, int $id): void
    {
        $exists = Document::query()
            ->where('linkable_type', $type->value)
            ->where('linkable_id', $id)
            ->exists();

        if ($exists) {
            throw DocumentDomainException::entityInUse();
        }
    }

    public function linkLabel(?string $type, ?int $id, ?Model $loaded = null): ?string
    {
        if ($type === null || $id === null) {
            return null;
        }

        $alias = DocumentLinkableType::tryFromAlias($type);
        if ($alias === null) {
            return null;
        }

        $model = $loaded ?? $this->findTarget($alias, $id);
        if ($model === null) {
            return null;
        }

        return match ($alias) {
            DocumentLinkableType::Contract => sprintf('%s — %s', $model->getAttribute('contract_number'), $model->getAttribute('title')),
            DocumentLinkableType::Meeting => sprintf('%s — %s', $model->getAttribute('meeting_number'), $model->getAttribute('title')),
            DocumentLinkableType::Decision => sprintf('%s — %s', $model->getAttribute('decision_number'), $model->getAttribute('title')),
            DocumentLinkableType::Task => sprintf('%s — %s', $model->getAttribute('task_number'), $model->getAttribute('title')),
            DocumentLinkableType::Employee => sprintf('%s — %s', $model->getAttribute('employee_number'), $model->getAttribute('full_name')),
            DocumentLinkableType::OrganizationUnit => sprintf('%s — %s', $model->getAttribute('code'), $model->getAttribute('name')),
        };
    }

    private function findTarget(DocumentLinkableType $alias, int $id): ?Model
    {
        $query = match ($alias) {
            DocumentLinkableType::Contract => Contract::query(),
            DocumentLinkableType::Meeting => Meeting::query(),
            DocumentLinkableType::Decision => Decision::query(),
            DocumentLinkableType::Task => Task::query(),
            DocumentLinkableType::Employee => Employee::query(),
            DocumentLinkableType::OrganizationUnit => OrganizationUnit::query(),
        };

        return $query->whereKey($id)->first();
    }
}
