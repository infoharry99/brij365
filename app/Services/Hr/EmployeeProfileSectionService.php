<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeProfileSection;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeProfileSectionService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function sectionsFor(Employee $employee): array
    {
        $rows = $employee->profileSections()
            ->get(['section', 'data'])
            ->keyBy('section');

        return collect(EmployeeProfileSection::SECTIONS)
            ->mapWithKeys(fn (string $section): array => [
                $section => $rows->has($section) ? ($rows->get($section)->data ?? $this->emptySection($section)) : $this->emptySection($section),
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $sections
     * @return array<string, mixed>
     */
    public function save(Employee $employee, array $sections, User $actor, ?Request $request = null): array
    {
        return DB::transaction(function () use ($employee, $sections, $actor, $request): array {
            $before = $this->sectionsFor($employee);
            $normalized = $this->normalizeSections($sections);

            foreach ($normalized as $section => $data) {
                EmployeeProfileSection::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'section' => $section,
                    ],
                    [
                        'company_id' => $employee->company_id,
                        'data' => $data,
                        'created_by_user_id' => $actor->id,
                        'updated_by_user_id' => $actor->id,
                    ],
                );
            }

            $after = $this->sectionsFor($employee->refresh());

            $this->auditLogger->record(
                $actor,
                'hr.employee_profile_sections.updated',
                'Updated employee profile sections',
                $employee,
                [
                    'employee_code' => $employee->employee_code,
                    'sections' => array_keys($normalized),
                    'before_keys' => $this->profileKeys($before),
                    'after_keys' => $this->profileKeys($after),
                ],
                $request,
            );

            return $after;
        });
    }

    /**
     * @param array<string, mixed> $sections
     * @return array<string, mixed>
     */
    private function normalizeSections(array $sections): array
    {
        $normalized = [];

        foreach (EmployeeProfileSection::SECTIONS as $section) {
            if (! array_key_exists($section, $sections)) {
                continue;
            }

            $value = $sections[$section];
            $normalized[$section] = is_array($value) ? array_values($value) === $value ? $value : array_filter($value, fn ($item): bool => $item !== null && $item !== '') : $this->emptySection($section);
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|array<int, mixed>
     */
    private function emptySection(string $section): array
    {
        return $section === 'personal' ? [] : [];
    }

    /**
     * @param array<string, mixed> $sections
     * @return array<string, array<int, string>>
     */
    private function profileKeys(array $sections): array
    {
        $keys = [];

        foreach ($sections as $section => $data) {
            $keys[$section] = is_array($data) ? array_keys($data) : [];
        }

        return $keys;
    }
}
