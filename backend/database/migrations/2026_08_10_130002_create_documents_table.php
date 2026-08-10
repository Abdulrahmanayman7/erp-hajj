<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-owned documents (metadata + private storage key).
 *
 * @see docs/09-modules/09-documents/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('document_number', 20);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('document_categories')->restrictOnDelete();
            $table->string('status', 32)->default('active');
            $table->string('original_filename', 255);
            $table->string('stored_filename', 64);
            $table->string('storage_disk', 32);
            $table->string('storage_path', 512);
            $table->string('mime_type', 127);
            $table->string('extension', 16);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->string('linkable_type', 64)->nullable();
            $table->unsignedBigInteger('linkable_id')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'document_number'], 'documents_tenant_number_unique');
            $table->index(['tenant_id', 'linkable_type', 'linkable_id'], 'documents_tenant_linkable_idx');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'category_id']);
            $table->index(['tenant_id', 'uploaded_by']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'checksum_sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
