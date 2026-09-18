<?php

namespace App\Modules\Documents\Support;

use App\Core\Tenancy\TenantStorage;
use App\Modules\Documents\Exceptions\DocumentDomainException;
use App\Modules\Documents\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class DocumentFileStore
{
    public function __construct(
        private readonly TenantStorage $tenantStorage,
    ) {}

    /**
     * @return array{
     *   original_filename: string,
     *   stored_filename: string,
     *   storage_disk: string,
     *   storage_path: string,
     *   mime_type: string,
     *   extension: string,
     *   size_bytes: int,
     *   checksum_sha256: string
     * }
     */
    public function store(UploadedFile $file): array
    {
        $max = (int) config('documents.max_size_bytes', 20 * 1024 * 1024);
        $size = $file->getSize();
        if ($size === false || $size > $max) {
            throw DocumentDomainException::fileTooLarge();
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        /** @var array<string, list<string>> $mimeMap */
        $mimeMap = config('documents.mime_map', []);

        if ($extension === '' || ! array_key_exists($extension, $mimeMap)) {
            throw DocumentDomainException::invalidFile();
        }

        $detectedMime = '';
        $realPath = $file->getRealPath() ?: $file->getPathname();
        if (is_string($realPath) && $realPath !== '' && is_file($realPath)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detectedMime = (string) ($finfo->file($realPath) ?: '');
        }

        if ($detectedMime === '' || ! in_array($detectedMime, $mimeMap[$extension], true)) {
            throw DocumentDomainException::invalidFile();
        }

        $storedFilename = (string) Str::uuid().'.'.$extension;
        $relative = $this->tenantStorage->path(TenantStorage::DOCUMENTS, $storedFilename);
        $disk = (string) config('documents.disk', 'local');
        $directory = dirname($relative);
        if ($directory === '.' || $directory === '') {
            throw DocumentDomainException::storageFailed();
        }

        try {
            $written = Storage::disk($disk)->putFileAs($directory, $file, basename($relative));
            if ($written === false) {
                throw DocumentDomainException::storageFailed();
            }
        } catch (DocumentDomainException $e) {
            throw $e;
        } catch (Throwable) {
            throw DocumentDomainException::storageFailed();
        }

        try {
            $absolute = Storage::disk($disk)->path($relative);
            $checksum = hash_file('sha256', $absolute);
            if ($checksum === false) {
                $this->deletePath($disk, $relative);
                throw DocumentDomainException::storageFailed();
            }
        } catch (DocumentDomainException $e) {
            throw $e;
        } catch (Throwable) {
            $this->deletePath($disk, $relative);
            throw DocumentDomainException::storageFailed();
        }

        return [
            'original_filename' => $this->sanitizeOriginalFilename($file->getClientOriginalName(), $extension),
            'stored_filename' => $storedFilename,
            'storage_disk' => $disk,
            'storage_path' => $relative,
            'mime_type' => $detectedMime,
            'extension' => $extension,
            'size_bytes' => (int) $size,
            'checksum_sha256' => $checksum,
        ];
    }

    public function deleteDocumentBlob(Document $document): void
    {
        $this->deletePath($document->storage_disk, $document->storage_path);
    }

    public function exists(Document $document): bool
    {
        return Storage::disk($document->storage_disk)->exists($document->storage_path);
    }

    public function deletePath(string $disk, string $path): void
    {
        try {
            if ($path !== '' && Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        } catch (Throwable) {
            // best-effort cleanup
        }
    }

    private function sanitizeOriginalFilename(string $original, string $extension): string
    {
        $name = str_replace(["\0", '/', '\\'], '', $original);
        $name = basename(str_replace(['..'], '', $name));
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'file.'.$extension;
        }

        return mb_substr($name, 0, 255);
    }
}
