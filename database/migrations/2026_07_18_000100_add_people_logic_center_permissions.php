<?php

use App\Domain\Scoring\Support\LogicCenterPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const ROLE_DEFAULTS = [
        'hr_manager' => [
            LogicCenterPermissions::PERFORMANCE_MANAGE,
            LogicCenterPermissions::PERFORMANCE_APPROVE,
            LogicCenterPermissions::PERFORMANCE_OVERRIDE_REQUEST,
            LogicCenterPermissions::ROSTER_MANAGE,
            LogicCenterPermissions::ROSTER_PUBLISH,
            LogicCenterPermissions::ROSTER_REOPEN,
            LogicCenterPermissions::SWAP_APPROVE,
            LogicCenterPermissions::ATTENDANCE_FINALIZE,
            LogicCenterPermissions::ATTENDANCE_REOPEN,
            LogicCenterPermissions::AUDIT_VIEW,
        ],
        'payroll' => [
            LogicCenterPermissions::STATUTORY_SIMULATE,
            LogicCenterPermissions::AUDIT_VIEW,
        ],
        'compliance' => [
            LogicCenterPermissions::STATUTORY_MANAGE,
            LogicCenterPermissions::STATUTORY_VERIFY,
            LogicCenterPermissions::STATUTORY_APPROVE,
            LogicCenterPermissions::AUDIT_VIEW,
        ],
        'auditor' => [LogicCenterPermissions::AUDIT_VIEW],
        'employee' => [LogicCenterPermissions::SWAP_REQUEST],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        foreach (self::ROLE_DEFAULTS as $slug => $permissions) {
            $this->mergePermissions($slug, $permissions);
        }

        if (Schema::hasTable('erp_modules')) {
            DB::table('erp_modules')->where('slug', 'scoring')->update([
                'required_permissions' => json_encode(LogicCenterPermissions::navigation(), JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // This data migration merges permissions into mutable role/module JSON.
        // The prior values cannot be reconstructed safely: subtracting the
        // flattened defaults could remove permissions that predated this
        // migration or were granted independently. Rollback is intentionally
        // non-destructive.
    }

    /** @param list<string> $permissions */
    private function mergePermissions(string $slug, array $permissions): void
    {
        $role = DB::table('roles')->where('slug', $slug)->first(['id', 'permissions']);
        if (! $role) {
            return;
        }

        $existing = json_decode((string) $role->permissions, true);
        DB::table('roles')->where('id', $role->id)->update([
            'permissions' => json_encode(array_values(array_unique(array_merge(is_array($existing) ? $existing : [], $permissions))), JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }
};
