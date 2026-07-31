<?php

namespace Tests\Feature;

use App\Domain\Hr\Services\ActiveInternalUserEligibility;
use App\Domain\Hr\Services\EmployeeRegister;
use App\Domain\Recruitment\Services\RecruitmentWorkspaceRegister;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\Role;
use App\Models\User;
use App\Services\Hr\EmployeeProfileService;
use App\Services\Recruitment\RecruitmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PeopleActiveInternalUserEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_people_candidates_include_only_active_internal_users_in_actor_company_scope(): void
    {
        $this->seed();

        $actor = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $valid = $this->targetUser($company, 'eligible-internal');
        $inactive = $this->targetUser($company, 'inactive-internal', [], 'inactive');
        $buyer = $this->targetUser($company, 'buyer-portal', ['buyer.view']);
        $partner = $this->targetUser($company, 'partner-portal', ['partner.portal']);
        $crossCompany = $this->targetUser($otherCompany, 'cross-company-internal');
        $wildcardInternal = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $eligibleIds = app(ActiveInternalUserEligibility::class)
            ->forActor($actor, $company->id)
            ->modelKeys();

        $this->assertContains($valid->id, $eligibleIds);
        $this->assertContains($wildcardInternal->id, $eligibleIds);
        $this->assertNotContains($inactive->id, $eligibleIds);
        $this->assertNotContains($buyer->id, $eligibleIds);
        $this->assertNotContains($partner->id, $eligibleIds);
        $this->assertNotContains($crossCompany->id, $eligibleIds);

        $employeeCandidateIds = app(EmployeeRegister::class)->availableUsers($actor)->modelKeys();
        $panelCandidateIds = app(RecruitmentWorkspaceRegister::class)->panelUsers($actor)->modelKeys();

        $this->assertContains($valid->id, $employeeCandidateIds);
        $this->assertContains($valid->id, $panelCandidateIds);
        $this->assertNotContains($buyer->id, $employeeCandidateIds);
        $this->assertNotContains($partner->id, $panelCandidateIds);
        $this->assertNotContains($inactive->id, $employeeCandidateIds);
        $this->assertNotContains($crossCompany->id, $panelCandidateIds);
    }

    public function test_employee_linking_rejects_external_inactive_and_cross_company_users_at_http_and_domain_boundaries(): void
    {
        $this->seed();

        $actor = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $buyer = $this->targetUser($company, 'employee-link-buyer', ['buyer.view']);
        $inactive = $this->targetUser($company, 'employee-link-inactive', [], 'inactive');
        $crossCompany = $this->targetUser($otherCompany, 'employee-link-cross-company');
        $validCreate = $this->targetUser($company, 'employee-link-valid-create');
        $validUpdate = $this->targetUser($company, 'employee-link-valid-update');

        foreach ([$buyer, $inactive, $crossCompany] as $index => $invalidUser) {
            $this->actingAs($actor)
                ->postJson(route('hr.employees.store'), $this->employeePayload($company, $invalidUser, 'EMP-EL-BAD-'.$index))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('user_id');
        }

        $createdEmployeeId = $this->actingAs($actor)
            ->postJson(route('hr.employees.store'), $this->employeePayload($company, $validCreate, 'EMP-EL-VALID'))
            ->assertCreated()
            ->assertJsonPath('data.user.id', $validCreate->id)
            ->json('data.id');

        $this->assertDatabaseHas('employees', [
            'id' => $createdEmployeeId,
            'user_id' => $validCreate->id,
            'company_id' => $company->id,
        ]);

        $employee = Employee::where('employee_code', 'EMP-0030')->firstOrFail();

        foreach ([$buyer, $inactive, $crossCompany] as $invalidUser) {
            $this->actingAs($actor)
                ->patchJson(route('hr.employees.update', $employee), [
                    'user_id' => $invalidUser->id,
                    'lock_version' => $employee->fresh()->lock_version,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('user_id');
        }

        $this->actingAs($actor)
            ->patchJson(route('hr.employees.update', $employee), [
                'user_id' => $validUpdate->id,
                'lock_version' => $employee->fresh()->lock_version,
            ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $validUpdate->id);

        $this->assertValidationError(
            fn () => app(EmployeeProfileService::class)->update(
                $employee->fresh(),
                ['user_id' => $buyer->id, 'lock_version' => $employee->fresh()->lock_version],
                $actor,
            ),
            'user_id',
        );
    }

    public function test_recruitment_panel_feedback_and_conversion_require_active_internal_company_users(): void
    {
        $this->seed();

        $recruiter = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();
        $hr = User::where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $buyer = $this->targetUser($company, 'recruitment-buyer', ['buyer.view']);
        $inactive = $this->targetUser($company, 'recruitment-inactive', [], 'inactive');
        $crossCompany = $this->targetUser($otherCompany, 'recruitment-cross-company');
        $validPanel = $this->targetUser($company, 'recruitment-valid-panel');
        $validEmployee = $this->targetUser($company, 'recruitment-valid-employee');
        $candidate = Candidate::where('candidate_code', 'CAN-1001')->firstOrFail();

        $baseInterview = [
            'candidate_id' => $candidate->id,
            'round_name' => 'Internal eligibility round',
            'scheduled_at' => now()->addDays(45)->setTime(11, 0)->toDateTimeString(),
            'duration_minutes' => 60,
            'mode' => 'video',
            'venue_or_link' => 'https://meet.example.test/internal-eligibility',
        ];

        foreach ([$buyer, $inactive, $crossCompany] as $invalidPanel) {
            $this->actingAs($recruiter)
                ->postJson(route('recruitment.interviews.store'), $baseInterview + [
                    'panel_user_ids' => [$invalidPanel->id],
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('panel_user_ids');
        }

        $this->assertValidationError(
            fn () => app(RecruitmentService::class)->scheduleInterview(
                $baseInterview + ['panel_user_ids' => [$buyer->id]],
                $recruiter,
            ),
            'panel_user_ids',
        );

        $interviewId = $this->actingAs($recruiter)
            ->postJson(route('recruitment.interviews.store'), $baseInterview + [
                'panel_user_ids' => [$validPanel->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.panel.0.id', $validPanel->id)
            ->json('data.id');

        $this->actingAs($validPanel)
            ->patchJson(route('recruitment.interviews.feedback', Interview::findOrFail($interviewId)), [
                'rating' => 4,
                'recommendation' => 'selected',
                'feedback_note' => 'Eligible internal panel feedback.',
            ])
            ->assertOk();

        $legacyExternalInterview = Interview::create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'scheduled_by_user_id' => $recruiter->id,
            'interview_code' => 'INT-EXT-ELIGIBILITY',
            'round_name' => 'Legacy external panel',
            'scheduled_at' => now()->addDays(46),
            'duration_minutes' => 60,
            'mode' => 'video',
            'panel_user_ids' => [$buyer->id],
            'status' => 'scheduled',
            'feedback' => [],
        ]);

        $this->actingAs($buyer)
            ->patchJson(route('recruitment.interviews.feedback', $legacyExternalInterview), [
                'rating' => 4,
                'recommendation' => 'selected',
            ])
            ->assertForbidden();

        $this->assertValidationError(
            fn () => app(RecruitmentService::class)->submitInterviewFeedback(
                $legacyExternalInterview,
                ['rating' => 4, 'recommendation' => 'selected'],
                $buyer,
            ),
            'interview',
        );

        foreach ([$buyer, $inactive, $crossCompany] as $invalidUser) {
            $this->actingAs($hr)
                ->postJson(route('recruitment.candidates.convert-to-employee', $candidate), [
                    'employee_code' => 'EMP-CONV-BLOCKED-'.$invalidUser->id,
                    'user_id' => $invalidUser->id,
                    'joined_on' => now()->toDateString(),
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('user_id');
        }

        $this->assertValidationError(
            fn () => app(RecruitmentService::class)->convertCandidateToEmployee(
                $candidate->fresh(),
                [
                    'employee_code' => 'EMP-CONV-DOMAIN-BLOCKED',
                    'user_id' => $buyer->id,
                    'joined_on' => now()->toDateString(),
                ],
                $hr,
            ),
            'user_id',
        );

        $this->actingAs($hr)
            ->postJson(route('recruitment.candidates.convert-to-employee', $candidate->fresh()), [
                'employee_code' => 'EMP-CONV-INTERNAL',
                'user_id' => $validEmployee->id,
                'joined_on' => now()->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.employee.employee_code', 'EMP-CONV-INTERNAL');

        $this->assertDatabaseHas('employees', [
            'employee_code' => 'EMP-CONV-INTERNAL',
            'user_id' => $validEmployee->id,
            'company_id' => $company->id,
        ]);
    }

    /** @param list<string> $permissions */
    private function targetUser(Company $company, string $key, array $permissions = [], string $status = 'active'): User
    {
        $role = Role::create([
            'slug' => $key,
            'name' => str($key)->replace('-', ' ')->title()->toString(),
            'scope_level' => 'company',
            'permissions' => $permissions,
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'company_id' => $company->id,
            'name' => str($key)->replace('-', ' ')->title()->toString(),
            'email' => $key.'@example.test',
            'status' => $status,
        ]);
    }

    /** @return array<string, mixed> */
    private function employeePayload(Company $company, User $user, string $employeeCode): array
    {
        return [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'employee_code' => $employeeCode,
            'name' => $user->name,
            'designation' => 'People Operations Associate',
            'department' => 'HR',
            'employment_type' => 'full_time',
            'joined_on' => now()->subDay()->toDateString(),
        ];
    }

    private function assertValidationError(callable $operation, string $field): void
    {
        try {
            $operation();
            $this->fail("Expected a validation error for [{$field}].");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
