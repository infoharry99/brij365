<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('previous_rule_id')->nullable()->constrained('scoring_rules')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rule_key', 80);
            $table->string('name', 140);
            $table->unsignedInteger('version');
            $table->string('status', 32)->default('draft');
            $table->json('configuration');
            $table->char('configuration_checksum', 64);
            $table->text('change_reason');
            $table->dateTime('effective_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('retired_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'rule_key', 'version'], 'scoring_rules_company_key_version_unique');
            $table->index(['company_id', 'rule_key', 'status'], 'scoring_rules_company_key_status_index');
            $table->index(['status', 'effective_at'], 'scoring_rules_status_effective_index');
        });

        Schema::create('score_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('scoring_rule_id')->constrained()->restrictOnDelete();
            $table->foreignId('overridden_from_snapshot_id')->nullable()->constrained('score_snapshots')->nullOnDelete();
            $table->foreignId('overridden_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->decimal('total_score', 10, 4);
            $table->json('component_scores');
            $table->json('applied_weights');
            $table->string('score_band', 80)->nullable();
            $table->json('input_snapshot')->nullable();
            $table->char('input_hash', 64);
            $table->unsignedInteger('rule_version');
            $table->boolean('is_current')->default(true);
            $table->boolean('is_override')->default(false);
            $table->text('override_reason')->nullable();
            $table->dateTime('overridden_at')->nullable();
            $table->dateTime('calculated_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'subject_type', 'subject_id', 'is_current'], 'score_snapshots_subject_current_index');
            $table->index(['scoring_rule_id', 'calculated_at'], 'score_snapshots_rule_calculated_index');
            $table->index(['score_band', 'calculated_at'], 'score_snapshots_band_calculated_index');
        });

        Schema::create('scoring_recalculation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('scoring_rule_id')->constrained()->restrictOnDelete();
            $table->foreignId('triggered_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('processed_records')->default(0);
            $table->unsignedInteger('failed_records')->default(0);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status', 'created_at'], 'scoring_recalc_company_status_index');
        });

        Schema::create('scoring_recalculation_failures', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('scoring_recalculation_run_id');
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->string('error_code', 80);
            $table->text('error_message');
            $table->json('context')->nullable();
            $table->timestamps();

            $table->foreign('scoring_recalculation_run_id', 'score_recalc_failures_run_fk')
                ->references('id')
                ->on('scoring_recalculation_runs')
                ->cascadeOnDelete();
            $table->index(['scoring_recalculation_run_id', 'subject_type'], 'scoring_recalc_failures_run_subject_index');
        });

        DB::table('erp_modules')->updateOrInsert(
            ['slug' => 'scoring'],
            [
                'group_name' => 'System',
                'name' => 'Scoring Logic',
                'route' => 'scoring',
                'icon' => 'sliders',
                'sort_order' => 905,
                'required_permissions' => json_encode(['scoring.view'], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->grantPermissions();
    }

    public function down(): void
    {
        $this->revokePermissions();
        DB::table('erp_modules')->where('slug', 'scoring')->delete();
        Schema::dropIfExists('scoring_recalculation_failures');
        Schema::dropIfExists('scoring_recalculation_runs');
        Schema::dropIfExists('score_snapshots');
        Schema::dropIfExists('scoring_rules');
    }

    private function grantPermissions(): void
    {
        $rolePermissions = [
            'sales_head' => ['scoring.view'],
            'construction_head' => ['scoring.view'],
            'finance_head' => ['scoring.view'],
            'hr_manager' => ['scoring.view'],
            'recruiter' => ['scoring.view'],
            'auditor' => ['scoring.view'],
            'compliance' => ['scoring.view'],
            'system_admin' => ['scoring.view', 'scoring.manage', 'scoring.approve', 'scoring.override', 'scoring.recalculate'],
        ];

        foreach ($rolePermissions as $slug => $permissions) {
            $role = DB::table('roles')->where('slug', $slug)->first(['id', 'permissions']);
            if (! $role) {
                continue;
            }

            $existing = json_decode((string) $role->permissions, true);
            $merged = array_values(array_unique(array_merge(is_array($existing) ? $existing : [], $permissions)));
            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode($merged, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }
    }

    private function revokePermissions(): void
    {
        $scoringPermissions = ['scoring.view', 'scoring.manage', 'scoring.approve', 'scoring.override', 'scoring.recalculate'];
        DB::table('roles')->select(['id', 'permissions'])->orderBy('id')->get()->each(function (object $role) use ($scoringPermissions): void {
            $existing = json_decode((string) $role->permissions, true);
            if (! is_array($existing)) {
                return;
            }
            $remaining = array_values(array_diff($existing, $scoringPermissions));
            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode($remaining, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        });
    }
};
