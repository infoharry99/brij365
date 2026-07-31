<?php

namespace Tests\Feature;

use App\Application\Recruitment\Data\RecruitmentCommandData;
use App\Application\Recruitment\Data\RecruitmentWorkspaceData;
use ReflectionClass;
use Tests\TestCase;

class RecruitmentApplicationLayerTest extends TestCase
{
    public function test_recruitment_command_and_workspace_data_are_immutable(): void
    {
        foreach ([RecruitmentCommandData::class, RecruitmentWorkspaceData::class] as $class) {
            $this->assertTrue((new ReflectionClass($class))->isReadOnly(), $class.' must remain immutable.');
        }
    }

    public function test_recruitment_controller_uses_focused_actions_without_queries_or_workflow_services(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Recruitment/RecruitmentController.php'));
        foreach (['ViewRecruitmentSourceSummary $view', 'ListRecruitmentWorkspace $workspace', 'ListJobOpenings $list', 'CreateJobOpening $create', 'ApproveJobOpening $approve', 'RejectJobOpening $reject', 'ListCandidates $list', 'CreateCandidate $create', 'ChangeCandidateStage $change', 'ListInterviews $list', 'ScheduleCandidateInterview $schedule', 'SubmitInterviewPanelFeedback $action', 'ListJobOffers $list', 'CreateJobOffer $create', 'ReleaseJobOffer $release', 'ConvertCandidateToEmployee $convert'] as $boundary) {
            $this->assertStringContainsString($boundary, $controller);
        }
        $this->assertStringNotContainsString('::query()', $controller);
        $this->assertStringNotContainsString('RecruitmentService $', $controller);
        $this->assertStringNotContainsString('CompanyScopeService $', $controller);
    }

    public function test_recruitment_mobile_registers_preserve_authorized_actions_and_empty_states(): void
    {
        $registers = [
            'openings.blade.php' => ['recruitment.job-openings.approve', 'No job openings found'],
            'candidates.blade.php' => ['recruitment.candidates.stage', 'No candidates found'],
            'interviews.blade.php' => ['recruitment.interviews.feedback', 'No interviews found'],
            'offers.blade.php' => ['recruitment.offers.release', 'No offers found'],
        ];

        foreach ($registers as $view => [$routeName, $emptyState]) {
            $source = file_get_contents(resource_path('views/recruitment/workspace/partials/'.$view));
            $mobileMarkup = strstr($source, '<div class="people-ops-mobile-list">');

            $this->assertNotFalse($mobileMarkup, $view.' must render a responsive register.');
            $this->assertStringContainsString('@forelse', $mobileMarkup, $view.' must render a responsive empty state.');
            $this->assertStringContainsString('@empty', $mobileMarkup, $view.' must render a responsive empty state.');
            $this->assertStringContainsString('people-ops-mobile-actions', $mobileMarkup, $view.' must keep permitted workflow actions on small screens.');
            $this->assertStringContainsString($routeName, $mobileMarkup, $view.' must submit to the existing authorized workflow route.');
            $this->assertStringContainsString($emptyState, $mobileMarkup);
        }
    }
}
