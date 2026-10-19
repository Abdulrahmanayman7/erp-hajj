<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 3 Gate B: additive columns for actual departure (B2) and relocate
 * history chain. No CHECK widen and no ops side effects.
 *
 * @see docs/reviews/ACCOMMODATION_SPRINT3_SCOPED_APPROVAL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accommodation_assignments', function (Blueprint $table): void {
            $table->dateTime('actual_departed_at')->nullable()->after('effective_ends_at');
            $table->unsignedBigInteger('relocated_from_assignment_id')->nullable()->after('checkout_source');

            $table->foreign(
                ['tenant_id', 'season_id', 'relocated_from_assignment_id'],
                'acc_asg_relocated_from_fk',
            )
                ->references(['tenant_id', 'season_id', 'id'])
                ->on('accommodation_assignments')
                ->restrictOnDelete();

            $table->index(
                ['tenant_id', 'season_id', 'relocated_from_assignment_id'],
                'acc_asg_relocated_from_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('accommodation_assignments', function (Blueprint $table): void {
            $table->dropForeign('acc_asg_relocated_from_fk');
            $table->dropIndex('acc_asg_relocated_from_idx');
            $table->dropColumn(['actual_departed_at', 'relocated_from_assignment_id']);
        });
    }
};
