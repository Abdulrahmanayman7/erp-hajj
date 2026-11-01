<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S04 Phase IV WP02: durable IV-D6 routing block fields on purchase requests. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->timestamp('routing_blocked_at')->nullable()->after('matrix_version_no');
            $table->string('routing_block_code', 64)->nullable()->after('routing_blocked_at');
            $table->text('routing_block_message')->nullable()->after('routing_block_code');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->dropColumn(['routing_blocked_at', 'routing_block_code', 'routing_block_message']);
        });
    }
};
