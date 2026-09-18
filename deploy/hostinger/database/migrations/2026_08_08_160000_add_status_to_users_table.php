<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auth account status for Sprint 005.
 * Values: active | disabled (PHP: App\Core\Auth\UserStatus).
 * Indexed: login path filters by email primarily; status checks are
 * single-row after auth — no list query yet, so no status index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('status', 20)->default('active')->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('status');
        });
    }
};
