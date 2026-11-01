<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_document_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('requirement_def_id')
                ->constrained('supplier_document_requirement_defs')
                ->restrictOnDelete();
            $table->foreignId('document_id')->constrained('documents')->restrictOnDelete();
            $table->string('verification_status', 32)->default('pending_verification');
            $table->date('expires_at')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('supersedes_id')->nullable()
                ->constrained('supplier_document_records')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'supplier_id', 'verification_status'], 'supplier_doc_records_supplier_status_idx');
            $table->index(['tenant_id', 'document_id'], 'supplier_doc_records_document_idx');
            $table->index(['tenant_id', 'expires_at'], 'supplier_doc_records_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_document_records');
    }
};
