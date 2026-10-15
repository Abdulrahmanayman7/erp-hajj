<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pilgrim identity documents (type + issuing country + number).
 * UNIQUE (tenant_id, document_type, issuing_country, normalized_number)
 * prevents the same document from belonging to two pilgrims in a tenant.
 *
 * @see docs/09-modules/16-season-and-pilgrims/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pilgrim_identity_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('pilgrim_id');
            $table->string('document_type', 32);
            $table->string('issuing_country', 3);
            $table->string('document_number', 64);
            $table->string('normalized_number', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign(['tenant_id', 'pilgrim_id'], 'pilgrim_identity_documents_pilgrim_fk')
                ->references(['tenant_id', 'id'])
                ->on('pilgrims')
                ->restrictOnDelete();

            $table->unique(
                ['tenant_id', 'document_type', 'issuing_country', 'normalized_number'],
                'pilgrim_identity_documents_identity_unique',
            );
            $table->index(['tenant_id', 'pilgrim_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pilgrim_identity_documents');
    }
};
