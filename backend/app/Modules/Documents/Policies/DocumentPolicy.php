<?php

namespace App\Modules\Documents\Policies;

use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Support\DocumentReferenceValidator;

class DocumentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('documents.view');
    }

    public function view(User $actor, Document $document): bool
    {
        return $actor->hasPermission('documents.view')
            && $this->sameTenant($actor, $document)
            && $this->mayAccessLinkable($actor, $document);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('documents.upload');
    }

    public function upload(User $actor): bool
    {
        return $this->create($actor);
    }

    public function download(User $actor, Document $document): bool
    {
        return $actor->hasPermission('documents.download')
            && $this->sameTenant($actor, $document)
            && $this->mayAccessLinkable($actor, $document);
    }

    public function update(User $actor, Document $document): bool
    {
        return $actor->hasPermission('documents.update')
            && $this->sameTenant($actor, $document)
            && $this->mayAccessLinkable($actor, $document);
    }

    public function archive(User $actor, Document $document): bool
    {
        return $actor->hasPermission('documents.archive')
            && $this->sameTenant($actor, $document)
            && $this->mayAccessLinkable($actor, $document);
    }

    public function restore(User $actor, Document $document): bool
    {
        return $this->archive($actor, $document);
    }

    public function delete(User $actor, Document $document): bool
    {
        return $actor->hasPermission('documents.delete')
            && $this->sameTenant($actor, $document)
            && $this->mayAccessLinkable($actor, $document);
    }

    private function sameTenant(User $actor, Document $document): bool
    {
        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $document->tenant_id;
    }

    private function mayAccessLinkable(User $actor, Document $document): bool
    {
        return app(DocumentReferenceValidator::class)->actorMayAccessDocumentLinkable($actor, $document);
    }
}
