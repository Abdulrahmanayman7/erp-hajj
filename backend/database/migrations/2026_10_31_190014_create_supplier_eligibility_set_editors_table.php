<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S04 II-A remediation M5: full editor history of a requirement-set version so the
 * C3 separation-of-duties check covers every contributor, not only the last editor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_eligibility_set_editors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('requirement_set_id')
                ->constrained('supplier_eligibility_requirement_sets')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('first_edited_at');

            $table->unique(['tenant_id', 'requirement_set_id', 'user_id'], 'supplier_elig_set_editors_unique');
        });

        // Backfill existing versions: author and last editor are known contributors.
        $now = now();
        foreach (DB::table('supplier_eligibility_requirement_sets')->get() as $set) {
            foreach (array_unique(array_filter([$set->authored_by, $set->last_edited_by])) as $userId) {
                DB::table('supplier_eligibility_set_editors')->insertOrIgnore([
                    'tenant_id' => $set->tenant_id,
                    'requirement_set_id' => $set->id,
                    'user_id' => $userId,
                    'first_edited_at' => $set->created_at ?? $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_eligibility_set_editors');
    }
};
