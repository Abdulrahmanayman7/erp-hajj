<?php

namespace Database\Factories;

use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantStorage;
use App\Models\User;
use App\Modules\Documents\Enums\DocumentStatus;
use App\Modules\Documents\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = 'txt';
        $storedFilename = (string) Str::uuid().'.'.$extension;
        $tenant = app(TenantContext::class)->require();
        $storagePath = app(TenantStorage::class)->path(TenantStorage::DOCUMENTS, $storedFilename);
        $disk = (string) config('documents.disk', 'local');
        $content = 'factory-document-content';

        Storage::disk($disk)->put($storagePath, $content);

        return [
            'title' => fake()->sentence(3),
            'description' => null,
            'category_id' => null,
            'status' => DocumentStatus::Active,
            'original_filename' => 'sample.txt',
            'stored_filename' => $storedFilename,
            'storage_disk' => $disk,
            'storage_path' => $storagePath,
            'mime_type' => 'text/plain',
            'extension' => $extension,
            'size_bytes' => strlen($content),
            'checksum_sha256' => hash('sha256', $content),
            'linkable_type' => null,
            'linkable_id' => null,
            'uploaded_by' => function () use ($tenant): int {
                return User::factory()->create(['tenant_id' => $tenant->id])->id;
            },
            'archived_at' => null,
            'archived_by' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => DocumentStatus::Archived,
            'archived_at' => now(),
        ]);
    }
}
