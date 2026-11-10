<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S10 G1 — short-TTL replay nonces for GPS ingest HMAC webhooks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_ingest_nonces', function (Blueprint $table): void {
            $table->id();
            $table->string('nonce', 128);
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique('nonce', 'gps_nonce_uq');
            $table->index('expires_at', 'gps_nonce_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_ingest_nonces');
    }
};
