<?php

namespace App\Modules\Documents\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Documents\Enums\DocumentStatus;
use App\Modules\Documents\Exceptions\DocumentDomainException;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Support\DocumentFileStore;
use App\Modules\Documents\Support\DocumentNumberGenerator;
use App\Modules\Documents\Support\DocumentReferenceValidator;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

final class UploadDocument
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly DocumentNumberGenerator $numbers,
        private readonly DocumentFileStore $files,
        private readonly DocumentReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, UploadedFile $file, array $data, Request $request): Document
    {
        $tenant = $this->tenantContext->require();

        $stored = $this->files->store($file);

        try {
            return DB::transaction(function () use ($actor, $data, $request, $tenant, $stored): Document {
                $categoryId = array_key_exists('category_id', $data) && $data['category_id'] !== null && $data['category_id'] !== ''
                    ? (int) $data['category_id']
                    : null;
                $category = $this->references->resolveCategory($categoryId);

                [$linkableType, $linkableId] = $this->references->resolveLink(
                    isset($data['linkable_type']) ? (string) $data['linkable_type'] : null,
                    $data['linkable_id'] ?? null,
                );

                $title = isset($data['title']) ? trim((string) $data['title']) : '';
                if ($title === '') {
                    $title = pathinfo($stored['original_filename'], PATHINFO_FILENAME);
                    $title = $title !== '' ? $title : $stored['original_filename'];
                }

                $document = new Document([
                    'title' => mb_substr($title, 0, 255),
                    'description' => $data['description'] ?? null,
                    'category_id' => $category?->id,
                    'status' => DocumentStatus::Active,
                    'original_filename' => $stored['original_filename'],
                    'stored_filename' => $stored['stored_filename'],
                    'storage_disk' => $stored['storage_disk'],
                    'storage_path' => $stored['storage_path'],
                    'mime_type' => $stored['mime_type'],
                    'extension' => $stored['extension'],
                    'size_bytes' => $stored['size_bytes'],
                    'checksum_sha256' => $stored['checksum_sha256'],
                    'linkable_type' => $linkableType,
                    'linkable_id' => $linkableId,
                    'uploaded_by' => $actor->id,
                ]);
                $document->document_number = $this->numbers->next();
                $document->save();

                $this->security->record(AuthorizationSecurityEvent::DOCUMENT_UPLOADED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'document_id' => $document->id,
                    'document_number' => $document->document_number,
                    'mime_type' => $document->mime_type,
                    'size_bytes' => $document->size_bytes,
                    'extension' => $document->extension,
                    'linkable_type' => $document->linkable_type,
                    'linkable_id' => $document->linkable_id,
                ], $request);

                if ($linkableType !== null) {
                    $this->security->record(AuthorizationSecurityEvent::DOCUMENT_LINKED, [
                        'tenant_id' => $tenant->id,
                        'actor_id' => $actor->id,
                        'document_id' => $document->id,
                        'document_number' => $document->document_number,
                        'linkable_type' => $linkableType,
                        'linkable_id' => $linkableId,
                    ], $request);
                }

                return $this->loadRelations($document);
            });
        } catch (QueryException $e) {
            $this->files->deletePath($stored['storage_disk'], $stored['storage_path']);
            if ($this->isUniqueNumberViolation($e)) {
                throw DocumentDomainException::numberTaken();
            }
            throw $e;
        } catch (Throwable $e) {
            $this->files->deletePath($stored['storage_disk'], $stored['storage_path']);
            throw $e;
        }
    }

    private function loadRelations(Document $document): Document
    {
        return $document->load(['category', 'uploader', 'linkable']);
    }

    private function isUniqueNumberViolation(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'documents_tenant_number_unique')
            || (str_contains($message, 'document_number') && str_contains($message, 'Duplicate'));
    }
}
