<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accommodation_code_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('prefix', 8);
            $table->unsignedBigInteger('next_number');
            $table->timestamps();
            $table->unique(['tenant_id', 'prefix']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accommodation_code_sequences');
    }
};
