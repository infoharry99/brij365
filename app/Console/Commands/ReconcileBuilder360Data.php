<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ReconcileBuilder360Data extends Command
{
    protected $signature = 'builder360:reconcile {--json : Output machine-readable reconciliation evidence}';

    protected $description = 'Run read-only single-company, project-reference and scoring consistency checks';

    public function handle(): int
    {
        $companyCode = (string) config('builder360.single_company.code');
        $company = Schema::hasTable('companies')
            ? DB::table('companies')->where('code', $companyCode)->first()
            : null;

        $tables = $this->tableNames();
        $companyReferenceOrphans = [];
        $retainedOtherCompanyRecords = [];
        $projectOrphans = [];

        foreach ($tables as $table) {
            if ($company !== null && Schema::hasColumn($table, 'company_id')) {
                $retainedCount = DB::table($table)
                    ->whereNotNull('company_id')
                    ->where('company_id', '<>', $company->id)
                    ->count();

                if ($retainedCount > 0) {
                    $retainedOtherCompanyRecords[$table] = $retainedCount;
                }

                if ($table !== 'companies') {
                    $orphanCount = DB::table($table.' as source')
                        ->whereNotNull('source.company_id')
                        ->whereNotExists(fn ($query) => $query
                            ->selectRaw('1')
                            ->from('companies')
                            ->whereColumn('companies.id', 'source.company_id'))
                        ->count();

                    if ($orphanCount > 0) {
                        $companyReferenceOrphans[$table] = $orphanCount;
                    }
                }
            }

            if ($table !== 'projects' && Schema::hasTable('projects') && Schema::hasColumn($table, 'project_id')) {
                $count = DB::table($table.' as source')
                    ->whereNotNull('source.project_id')
                    ->whereNotExists(fn ($query) => $query
                        ->selectRaw('1')
                        ->from('projects')
                        ->whereColumn('projects.id', 'source.project_id'))
                    ->count();

                if ($count > 0) {
                    $projectOrphans[$table] = $count;
                }
            }
        }

        $activeScoringRuleDuplicates = Schema::hasTable('scoring_rules')
            ? DB::table('scoring_rules')
                ->select('rule_key')
                ->where('status', 'active')
                ->groupBy('rule_key')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('rule_key')
                ->values()
                ->all()
            : [];

        $checks = [
            'single_company_enabled' => (bool) config('builder360.single_company.enabled'),
            'configured_company_found' => $company !== null,
            'one_active_company' => $this->countWhere('companies', 'status', 'active') === 1,
            'company_scope_clean' => $companyReferenceOrphans === [],
            'project_references_clean' => $projectOrphans === [],
            'active_scoring_rules_unique' => $activeScoringRuleDuplicates === [],
            'failed_queue_empty' => $this->tableCount('failed_jobs') === 0,
        ];

        $result = [
            'status' => in_array(false, $checks, true) ? 'degraded' : 'ok',
            'database' => [
                'driver' => DB::getDriverName(),
                'name' => DB::getDatabaseName(),
            ],
            'company' => [
                'configured_code' => $companyCode,
                'configured_company_found' => $company !== null,
                'active_companies' => $this->countWhere('companies', 'status', 'active'),
            ],
            'core_counts' => [
                'projects' => $this->tableCount('projects'),
                'users' => $this->tableCount('users'),
                'roles' => $this->tableCount('roles'),
                'employees' => $this->tableCount('employees'),
                'active_settings' => $this->countWhere('system_settings', 'status', 'active'),
                'audit_events' => $this->tableCount('audit_events'),
                'notifications' => $this->tableCount('user_notifications'),
                'scoring_rules' => $this->tableCount('scoring_rules'),
                'score_snapshots' => $this->tableCount('score_snapshots'),
            ],
            'scope_evidence' => [
                'tables_checked' => count($tables),
                'company_reference_orphans' => $companyReferenceOrphans,
                'retained_other_company_records' => $retainedOtherCompanyRecords,
                'project_reference_orphans' => $projectOrphans,
                'active_scoring_rule_duplicates' => $activeScoringRuleDuplicates,
            ],
            'queue' => [
                'pending' => $this->tableCount('jobs'),
                'failed' => $this->tableCount('failed_jobs'),
            ],
            'checks' => $checks,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->twoColumnDetail('Reconciliation status', strtoupper($result['status']));
            $this->components->twoColumnDetail('Configured company', $companyCode);
            $this->components->twoColumnDetail('Tables checked', (string) count($tables));
            foreach ($checks as $label => $passed) {
                $this->components->twoColumnDetail(str_replace('_', ' ', $label), $passed ? 'PASS' : 'FAIL');
            }
        }

        return $result['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<string> */
    private function tableNames(): array
    {
        if (DB::getDriverName() === 'mysql') {
            return DB::table('information_schema.tables')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_type', 'BASE TABLE')
                ->orderBy('table_name')
                ->pluck('table_name')
                ->map(fn ($table): string => (string) $table)
                ->all();
        }

        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"))
                ->map(fn (object $row): string => (string) $row->name)
                ->all();
        }

        return collect(Schema::getTables())
            ->map(fn (array $table): string => (string) ($table['name'] ?? ''))
            ->filter()
            ->values()
            ->all();
    }

    private function tableCount(string $table): int
    {
        return Schema::hasTable($table) ? DB::table($table)->count() : 0;
    }

    private function countWhere(string $table, string $column, mixed $value): int
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column)
            ? DB::table($table)->where($column, $value)->count()
            : 0;
    }
}
