<?php

namespace App\Domain\Payroll\Services;

use App\Domain\Payroll\Data\StatutoryPayrollCutoverManifest;
use App\Domain\Payroll\ValueObjects\MinorMoney;
use App\Models\PayrollCalculationSnapshot;
use App\Models\SystemSetting;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class PayrollCalculationSnapshotVerifier
{
    public function __construct(
        private readonly CanonicalPayrollHasher $hasher,
        private readonly StatutoryRulePackDefinitionValidator $rulePackValidator,
    ) {}

    public function assertGovernedIntegrity(PayrollCalculationSnapshot $snapshot, string $field = 'payroll_run'): void
    {
        $snapshot->loadMissing(['lines', 'payrollRunItem', 'attendanceSnapshot']);

        if (data_get($snapshot->rule_context, 'mode') !== 'governed_verified') {
            $this->fail($field, 'The payroll calculation is not a governed verified snapshot.');
        }

        $input = $snapshot->input_snapshot;
        if (! is_array($input) || $input === []) {
            $this->fail($field, 'The governed payroll calculation does not contain its immutable canonical input snapshot. Regenerate payroll.');
        }

        $this->assertHashMatches($snapshot->input_hash, $this->hasher->hash($input), $field, 'input');

        if (! hash_equals(
            $this->hasher->hash((array) $snapshot->rule_context),
            $this->hasher->hash((array) ($input['rule_context'] ?? [])),
        )) {
            $this->fail($field, 'The governed payroll rule context no longer matches its pinned canonical input.');
        }

        $lines = $this->canonicalLines($snapshot->lines);
        $expectedResultHash = $this->hasher->hash([
            'gross_minor' => (int) $snapshot->gross_minor,
            'deduction_minor' => (int) $snapshot->deduction_minor,
            'employer_contribution_minor' => (int) $snapshot->employer_contribution_minor,
            'net_minor' => (int) $snapshot->net_minor,
            'lines' => $lines,
            'input_hash' => (string) $snapshot->input_hash,
        ]);
        $this->assertHashMatches($snapshot->result_hash, $expectedResultHash, $field, 'result');

        $this->assertLineTotals($snapshot, $field);
        $this->assertRunItemMatches($snapshot, $input, $field);
        $this->assertAttendanceMatches($snapshot, $input, $field);
        $this->assertPinnedRules($snapshot, $lines, $field);
        $this->assertPinnedManifest($snapshot, $field);
    }

    /** @param Collection<int, \App\Models\PayrollCalculationLine> $lines */
    private function canonicalLines(Collection $lines): array
    {
        return $lines
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->map(fn ($line): array => [
                'system_setting_id' => $line->system_setting_id === null ? null : (int) $line->system_setting_id,
                'component_code' => (string) $line->component_code,
                'component_name' => (string) $line->component_name,
                'line_type' => (string) $line->line_type,
                'amount_minor' => (int) $line->amount_minor,
                'basis_minor' => (int) $line->basis_minor,
                'rate_ppm' => $line->rate_ppm === null ? null : (int) $line->rate_ppm,
                'sort_order' => (int) $line->sort_order,
                'trace' => (array) $line->trace,
            ])
            ->values()
            ->all();
    }

    private function assertLineTotals(PayrollCalculationSnapshot $snapshot, string $field): void
    {
        $gross = (int) $snapshot->lines->where('line_type', 'earning')->sum('amount_minor');
        $deductions = (int) $snapshot->lines
            ->whereIn('line_type', ['deduction', 'tax_adjustment'])
            ->sum('amount_minor');
        $employer = (int) $snapshot->lines->where('line_type', 'employer_contribution')->sum('amount_minor');

        if ($gross !== (int) $snapshot->gross_minor
            || $deductions !== (int) $snapshot->deduction_minor
            || $employer !== (int) $snapshot->employer_contribution_minor
            || $gross - $deductions !== (int) $snapshot->net_minor) {
            $this->fail($field, 'The governed payroll calculation lines no longer reconcile to the stored result totals.');
        }
    }

    /** @param array<string, mixed> $input */
    private function assertRunItemMatches(PayrollCalculationSnapshot $snapshot, array $input, string $field): void
    {
        $item = $snapshot->payrollRunItem;
        if ($item === null
            || (int) $item->company_id !== (int) $snapshot->company_id
            || (int) $item->employee_id !== (int) $snapshot->employee_id
            || (int) $item->salary_structure_id !== (int) ($input['salary_structure_id'] ?? 0)
            || (int) $snapshot->salary_assignment_id !== (int) ($input['salary_assignment_id'] ?? 0)
            || (int) $snapshot->employee_id !== (int) ($input['employee_id'] ?? 0)
            || MinorMoney::fromDecimal((string) $item->gross_earnings)->minor !== (int) $snapshot->gross_minor
            || MinorMoney::fromDecimal((string) $item->total_deductions)->minor !== (int) $snapshot->deduction_minor
            || MinorMoney::fromDecimal((string) $item->net_payable)->minor !== (int) $snapshot->net_minor) {
            $this->fail($field, 'The governed payroll run item no longer matches its immutable calculation snapshot.');
        }

        $componentMinor = $snapshot->lines
            ->whereNull('system_setting_id')
            ->mapWithKeys(fn ($line): array => [(string) $line->component_code => (int) $line->amount_minor])
            ->all();
        if (! hash_equals(
            $this->hasher->hash($componentMinor),
            $this->hasher->hash((array) ($input['component_minor'] ?? [])),
        )) {
            $this->fail($field, 'The governed payroll base components no longer match the canonical calculation input.');
        }
    }

    /** @param array<string, mixed> $input */
    private function assertAttendanceMatches(PayrollCalculationSnapshot $snapshot, array $input, string $field): void
    {
        $pinned = $input['attendance_snapshot'] ?? null;
        $attendance = $snapshot->attendanceSnapshot;
        if (! is_array($pinned)
            || $attendance === null
            || (int) ($pinned['id'] ?? 0) !== (int) $attendance->id
            || (int) $snapshot->payroll_attendance_snapshot_id !== (int) $attendance->id
            || ! hash_equals((string) ($pinned['source_hash'] ?? ''), (string) $attendance->source_hash)
            || (string) ($pinned['payable_days'] ?? '') !== (string) $attendance->payable_days
            || (int) ($pinned['scheduled_days'] ?? 0) !== (int) $attendance->scheduled_days) {
            $this->fail($field, 'The finalized attendance evidence no longer matches the governed payroll input snapshot.');
        }
    }

    /** @param list<array<string, mixed>> $lines */
    private function assertPinnedRules(PayrollCalculationSnapshot $snapshot, array $lines, string $field): void
    {
        $pins = collect((array) data_get($snapshot->rule_context, 'settings', []));
        if ($pins->isEmpty()) {
            $this->fail($field, 'The governed payroll calculation has no pinned statutory rule versions.');
        }

        $settings = SystemSetting::query()
            ->with('statutoryVerification')
            ->whereIn('id', $pins->pluck('setting_id')->map(fn ($id): int => (int) $id)->filter()->all())
            ->get()
            ->keyBy('id');

        foreach ($pins as $pin) {
            if (! is_array($pin)) {
                $this->fail($field, 'A pinned statutory rule reference is malformed.');
            }

            $setting = $settings->get((int) ($pin['setting_id'] ?? 0));
            if (! $setting instanceof SystemSetting
                || (int) $setting->company_id !== (int) $snapshot->company_id
                || $setting->setting_key !== ($pin['setting_key'] ?? null)
                || (int) $setting->version !== (int) ($pin['version'] ?? 0)) {
                $this->fail($field, 'A pinned statutory rule version no longer resolves to the original company setting.');
            }

            $checksum = $this->hasher->hash((array) $setting->value);
            $this->assertHashMatches((string) ($pin['checksum'] ?? ''), $checksum, $field, 'statutory rule');
            $this->rulePackValidator->assertValid((array) $setting->value);

            $verification = $setting->statutoryVerification;
            if ($verification === null
                || $verification->verified_by_user_id === null
                || ! hash_equals((string) $verification->configuration_checksum, $checksum)
                || $setting->approved_by_user_id === null
                || $setting->created_by_user_id === null
                || $verification->verified_by_user_id === $setting->created_by_user_id
                || $verification->verified_by_user_id === $setting->approved_by_user_id
                || $setting->approved_by_user_id === $setting->created_by_user_id) {
                $this->fail($field, 'A pinned statutory rule no longer satisfies independent maker, verifier, and approver governance.');
            }

            if (! hash_equals(
                $this->hasher->hash($this->canonicalSourceEvidence((array) ($pin['source_evidence'] ?? []))),
                $this->hasher->hash($this->canonicalSourceEvidence((array) data_get($setting->value, 'source_evidence', []))),
            )) {
                $this->fail($field, 'Pinned statutory source evidence no longer matches the verified rule version.');
            }
        }

        $pinnedIds = $pins->pluck('setting_id')->map(fn ($id): int => (int) $id)->all();
        foreach ($lines as $line) {
            if ($line['system_setting_id'] === null) {
                continue;
            }
            if (! in_array((int) $line['system_setting_id'], $pinnedIds, true)
                || (int) data_get($line, 'trace.setting_id', 0) !== (int) $line['system_setting_id']) {
                $this->fail($field, 'A governed payroll line is not linked to its pinned statutory rule.');
            }
        }
    }

    /** @param list<array<string, mixed>> $evidence */
    private function canonicalSourceEvidence(array $evidence): array
    {
        return collect($evidence)
            ->map(fn (array $source): array => [
                'authority' => (string) ($source['authority'] ?? ''),
                'title' => (string) ($source['title'] ?? ''),
                'document_reference' => (string) ($source['document_reference'] ?? ''),
                'url' => (string) ($source['url'] ?? ''),
                'source_checksum' => strtolower((string) ($source['source_checksum'] ?? '')),
                'published_or_accessed_on' => (string) ($source['published_or_accessed_on'] ?? ''),
            ])
            ->values()
            ->all();
    }

    private function assertPinnedManifest(PayrollCalculationSnapshot $snapshot, string $field): void
    {
        $manifest = (array) data_get($snapshot->rule_context, 'cutover_manifest', []);
        $manifestId = (int) ($manifest['setting_id'] ?? 0);
        $mode = (string) data_get($snapshot->rule_context, 'cutover_mode', '');
        if ($manifestId === 0) {
            if ($mode === StatutoryPayrollCutoverManifest::MODE_GOVERNED_REQUIRED) {
                $this->fail($field, 'Governed-required payroll has no pinned statutory cutover manifest.');
            }

            return;
        }

        $setting = SystemSetting::query()->find($manifestId);
        if ($setting === null
            || (int) $setting->company_id !== (int) $snapshot->company_id
            || $setting->setting_key !== StatutoryPayrollCutoverManifest::SETTING_KEY
            || (int) $setting->version !== (int) ($manifest['version'] ?? 0)) {
            $this->fail($field, 'The pinned statutory cutover manifest no longer resolves to the original company version.');
        }

        $this->assertHashMatches(
            (string) ($manifest['checksum'] ?? ''),
            $this->hasher->hash((array) $setting->value),
            $field,
            'statutory cutover manifest',
        );
    }

    private function assertHashMatches(string $stored, string $expected, string $field, string $label): void
    {
        if (preg_match('/^[a-f0-9]{64}$/i', $stored) === 1 && hash_equals(strtolower($stored), strtolower($expected))) {
            return;
        }

        $this->fail($field, 'The governed payroll '.$label.' checksum failed canonical integrity verification.');
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
