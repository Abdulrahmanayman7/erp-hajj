<?php

namespace App\Modules\Documents\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Documents\Enums\DocumentStatus;
use App\Modules\Documents\Support\DocumentNumberGenerator;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Tenant-owned document metadata (binary on private disk).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $document_number
 * @property string $title
 * @property string|null $description
 * @property int|null $category_id
 * @property DocumentStatus $status
 * @property string $original_filename
 * @property string $stored_filename
 * @property string $storage_disk
 * @property string $storage_path
 * @property string $mime_type
 * @property string $extension
 * @property int $size_bytes
 * @property string $checksum_sha256
 * @property string|null $linkable_type
 * @property int|null $linkable_id
 * @property int $uploaded_by
 * @property Carbon|null $archived_at
 * @property int|null $archived_by
 */
class Document extends Model implements TenantOwned
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'title',
        'description',
        'category_id',
        'status',
        'original_filename',
        'stored_filename',
        'storage_disk',
        'storage_path',
        'mime_type',
        'extension',
        'size_bytes',
        'checksum_sha256',
        'linkable_type',
        'linkable_id',
        'uploaded_by',
        'archived_at',
        'archived_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'size_bytes' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    protected static function newFactory(): DocumentFactory
    {
        return DocumentFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Document $document): void {
            $number = $document->getAttribute('document_number');
            if ($number === null || $number === '') {
                $document->setAttribute(
                    'document_number',
                    app(DocumentNumberGenerator::class)->next(),
                );
            }
        });

        static::updating(function (Document $document): void {
            if ($document->isDirty('document_number')) {
                throw new \LogicException('Document number is immutable after creation.');
            }
            if ($document->isDirty('tenant_id')) {
                throw new \LogicException('Document tenant_id is immutable.');
            }
            foreach ([
                'stored_filename',
                'storage_disk',
                'storage_path',
                'mime_type',
                'extension',
                'size_bytes',
                'checksum_sha256',
                'original_filename',
                'uploaded_by',
            ] as $field) {
                if ($document->isDirty($field)) {
                    throw new \LogicException("Document {$field} is immutable after creation.");
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
