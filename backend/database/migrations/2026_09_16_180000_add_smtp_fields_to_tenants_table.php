<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-configurable SMTP delivery (ADR-0016 amended exception).
 * Additive only — existing mail_from_* columns are unchanged.
 * mail_password is stored encrypted via Eloquent cast (never plaintext at rest).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('mail_mailer', 32)->nullable()->after('mail_from_name');
            $table->string('mail_host', 255)->nullable()->after('mail_mailer');
            $table->unsignedInteger('mail_port')->nullable()->after('mail_host');
            $table->string('mail_encryption', 16)->nullable()->after('mail_port');
            $table->string('mail_username', 255)->nullable()->after('mail_encryption');
            $table->text('mail_password')->nullable()->after('mail_username');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn([
                'mail_mailer',
                'mail_host',
                'mail_port',
                'mail_encryption',
                'mail_username',
                'mail_password',
            ]);
        });
    }
};
