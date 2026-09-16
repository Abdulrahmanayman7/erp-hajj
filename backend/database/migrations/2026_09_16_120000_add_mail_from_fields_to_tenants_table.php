<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-configurable mail sender identity (ADR-0016 column pattern).
 * SMTP credentials remain environment-only — never stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('mail_from_address', 255)->nullable()->after('contact_phone');
            $table->string('mail_from_name', 255)->nullable()->after('mail_from_address');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['mail_from_address', 'mail_from_name']);
        });
    }
};
