<?php

namespace Tests\Feature;

use Tests\TestCase;

class PeopleWorkspaceCompletionTest extends TestCase
{
    public function test_people_workspace_uses_vite_managed_responsive_and_accessible_contracts(): void
    {
        $root = base_path();
        $workspace = file_get_contents($root.'/resources/views/components/hr/people-workspace.blade.php');
        $rail = file_get_contents($root.'/resources/views/hr/partials/people-workspace-rail.blade.php');
        $directory = file_get_contents($root.'/resources/views/hr/employees/partials/directory-register.blade.php');
        $createForm = file_get_contents($root.'/resources/views/hr/employees/partials/create-form.blade.php');
        $profile = file_get_contents($root.'/resources/views/hr/employees/show.blade.php');
        $styles = file_get_contents($root.'/resources/css/hr-people.css');
        $script = file_get_contents($root.'/resources/js/app.js');

        $this->assertStringContainsString('aria-label="People workspace"', $workspace);
        $this->assertStringContainsString('x-on:keydown.escape.window="handlePeopleEscape"', $workspace);
        $this->assertStringContainsString('aria-current', $rail);
        $this->assertStringContainsString('x-ui.responsive-register', $directory);
        $this->assertStringContainsString('<caption>', $directory);
        $this->assertStringContainsString('people-mobile-cards', $directory);
        $this->assertStringContainsString('@container people-workspace', $styles);
        $this->assertStringContainsString('@media (max-width: 900px)', $styles);
        $this->assertStringContainsString('overflow-x: clip', $styles);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr)', $styles);
        $this->assertStringContainsString('grid-template-columns: repeat(auto-fit, minmax(min(140px, 100%), 1fr))', $styles);
        $this->assertStringContainsString("Alpine.data('peopleWorkspace'", $script);
        $this->assertStringContainsString('aria-labelledby="create-employee-title"', $createForm);
        $this->assertStringContainsString("class=\"people-modal {{ \$errors->any() ? 'is-open' : '' }}\"", $createForm);
        $this->assertStringContainsString("aria-hidden=\"{{ \$errors->any() ? 'false' : 'true' }}\"", $createForm);
        $this->assertStringContainsString('syncPeopleOverlayLock()', $script);
        $this->assertStringContainsString('html.people-overlay-open', $styles);
        $this->assertStringContainsString('aria-describedby="profile-manager-error"', $profile);
        $this->assertStringContainsString('id="profile-manager-error"', $profile);
    }

    public function test_mobile_topbar_and_people_header_do_not_force_page_width_overflow(): void
    {
        $enterpriseStyles = file_get_contents(base_path('resources/css/enterprise.css'));
        $peopleStyles = file_get_contents(base_path('resources/css/hr-people.css'));

        $tabletTopbarStart = strpos($enterpriseStyles, '@media (max-width: 1100px) and (min-width: 541px)');
        $this->assertNotFalse($tabletTopbarStart);
        $tabletTopbarEnd = strpos($enterpriseStyles, '@media (max-width: 540px)', $tabletTopbarStart);
        $this->assertNotFalse($tabletTopbarEnd);
        $tabletTopbarStyles = substr($enterpriseStyles, $tabletTopbarStart, $tabletTopbarEnd - $tabletTopbarStart);

        $mobileTopbarStart = strpos($enterpriseStyles, '@media (max-width: 540px)');
        $this->assertNotFalse($mobileTopbarStart);
        $mobileTopbarEnd = strpos($enterpriseStyles, '@media (max-width: 1500px)', $mobileTopbarStart);
        $this->assertNotFalse($mobileTopbarEnd);
        $mobileTopbarStyles = substr($enterpriseStyles, $mobileTopbarStart, $mobileTopbarEnd - $mobileTopbarStart);

        $mobilePeopleActionsStart = strpos($peopleStyles, '@media (max-width: 480px)');
        $this->assertNotFalse($mobilePeopleActionsStart);
        $mobilePeopleActionsEnd = strpos($peopleStyles, '@media (prefers-reduced-motion: reduce)', $mobilePeopleActionsStart);
        $this->assertNotFalse($mobilePeopleActionsEnd);
        $mobilePeopleActionStyles = substr(
            $peopleStyles,
            $mobilePeopleActionsStart,
            $mobilePeopleActionsEnd - $mobilePeopleActionsStart,
        );

        $this->assertStringContainsString('flex-wrap: wrap', $mobileTopbarStyles);
        $this->assertStringContainsString('height: auto', $mobileTopbarStyles);
        $this->assertStringContainsString('body.b360-classic .b360-topbar-actions', $mobileTopbarStyles);
        $this->assertStringContainsString(
            'grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) repeat(3, 48px)',
            $mobileTopbarStyles,
        );
        $this->assertStringContainsString('overflow-x: visible', $mobileTopbarStyles);
        $this->assertStringContainsString('width: 100%', $mobileTopbarStyles);
        $this->assertStringContainsString('min-width: 0', $mobileTopbarStyles);
        $this->assertStringContainsString(
            'body.b360-classic .b360-topbar-actions > :is(.b360-context-form, .b360-pill, .b360-role-chip)',
            $mobileTopbarStyles,
        );
        $this->assertStringContainsString(
            'body.b360-classic .b360-topbar-actions > form:not(.b360-context-form)',
            $mobileTopbarStyles,
        );
        $this->assertStringContainsString('body.b360-classic .b360-context-form select', $mobileTopbarStyles);
        $this->assertStringContainsString('body.b360-classic .b360-role-context-form .b360-avatar-sm', $mobileTopbarStyles);
        $this->assertStringContainsString('body.b360-classic .b360-topbar-actions .b360-icon-btn', $mobileTopbarStyles);

        $this->assertStringContainsString('flex-wrap: wrap', $tabletTopbarStyles);
        $this->assertStringContainsString('height: auto', $tabletTopbarStyles);
        $this->assertStringContainsString(
            'grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) repeat(3, 48px)',
            $tabletTopbarStyles,
        );
        $this->assertStringContainsString('body.b360-classic .b360-topbar-leading', $tabletTopbarStyles);
        $this->assertStringContainsString('body.b360-classic .b360-context-form select', $tabletTopbarStyles);

        $this->assertStringContainsString(
            '.people-page-actions { grid-template-columns: minmax(0, 1fr); }',
            $mobilePeopleActionStyles,
        );
    }

    public function test_governed_people_and_settings_mutations_share_a_busy_submission_contract(): void
    {
        $root = base_path();
        $createForm = file_get_contents($root.'/resources/views/hr/employees/partials/create-form.blade.php');
        $profile = file_get_contents($root.'/resources/views/hr/employees/show.blade.php');
        $settings = file_get_contents($root.'/resources/views/settings/system-settings/index.blade.php');
        $script = file_get_contents($root.'/resources/js/app.js');

        $this->assertStringContainsString("Alpine.data('serverFormState'", $script);
        $this->assertStringContainsString('x-bind:aria-busy="submitting"', $createForm);
        $this->assertStringContainsString('x-data="serverFormState"', $profile);
        $this->assertStringContainsString('data-busy-label="Saving changes…"', $profile);
        $this->assertGreaterThanOrEqual(2, substr_count($settings, 'x-data="serverFormState"'));
        $this->assertStringContainsString('data-busy-label="Creating draft…"', $settings);
        $this->assertStringContainsString('data-busy-label="Approving…"', $settings);
        $this->assertGreaterThanOrEqual(2, substr_count($settings, 'x-bind:disabled="busy"'));
        $this->assertStringContainsString('handleMutationSubmit', $script);
        $this->assertStringContainsString("this.\$root.addEventListener('submit'", $script);
        $this->assertStringContainsString("form.setAttribute('aria-busy', 'true')", $script);
        $this->assertStringContainsString("form.dataset.peopleSubmitting === 'true'", $script);
    }

    public function test_people_command_center_and_self_service_views_keep_queries_out_of_blade(): void
    {
        $root = base_path();
        $dashboardPath = $root.'/resources/views/hr/dashboard/index.blade.php';
        $selfServicePath = $root.'/resources/views/hr/employees/self-service.blade.php';

        $this->assertFileExists($dashboardPath);
        $this->assertFileExists($selfServicePath);

        $dashboard = file_get_contents($dashboardPath);
        $selfService = file_get_contents($selfServicePath);
        $rail = file_get_contents($root.'/resources/views/hr/partials/people-workspace-rail.blade.php');

        $this->assertStringContainsString('HR Command Center', $dashboard);
        $this->assertStringContainsString('Approval Inbox', $dashboard);
        $this->assertStringContainsString("'route' => 'hr.dashboard'", $rail);
        $this->assertStringContainsString('Employee Self Service', $selfService);
        $this->assertStringContainsString('My Attendance', $selfService);
        $this->assertStringContainsString('My Actions', $selfService);
        $this->assertStringNotContainsString('::query(', $dashboard);
        $this->assertStringNotContainsString('DB::', $dashboard);
        $this->assertStringNotContainsString('::query(', $selfService);
        $this->assertStringNotContainsString('DB::', $selfService);
    }

    public function test_document_and_compliance_workspaces_use_normalized_people_presentations(): void
    {
        $root = base_path();
        $documents = file_get_contents($root.'/resources/views/hr/documents/index.blade.php');
        $documentActions = file_get_contents($root.'/resources/views/hr/documents/partials/document-actions.blade.php');
        $compliance = file_get_contents($root.'/resources/views/hr/compliance/index.blade.php');
        $complianceActions = file_get_contents($root.'/resources/views/hr/compliance/partials/rule-actions.blade.php');

        $this->assertStringContainsString('<x-hr.people-workspace', $documents);
        $this->assertStringContainsString('Employee Documents', $documents);
        $this->assertStringContainsString('people-ops-mobile-list', $documents);
        $this->assertSame(2, substr_count($documents, "@include('hr.documents.partials.document-actions'"));
        $this->assertStringContainsString('aria-label="Approve document', $documentActions);
        $this->assertStringContainsString('x-data="serverFormState"', $documentActions);
        $this->assertStringContainsString('tabindex="-1"', $documents);
        $this->assertStringNotContainsString('::query(', $documents);
        $this->assertStringNotContainsString('DB::', $documents);

        $this->assertStringContainsString('<x-hr.people-workspace', $compliance);
        $this->assertStringContainsString('Compliance Center', $compliance);
        $this->assertStringContainsString('verified statutory values only', $compliance);
        $this->assertStringContainsString('people-ops-mobile-list', $compliance);
        $this->assertSame(2, substr_count($compliance, "@include('hr.compliance.partials.rule-actions'"));
        $this->assertStringContainsString('aria-label="Review', $complianceActions);
        $this->assertStringContainsString('x-data="serverFormState"', $complianceActions);
        $this->assertStringContainsString('tabindex="-1"', $compliance);
        $this->assertStringNotContainsString('::query(', $compliance);
        $this->assertStringNotContainsString('DB::', $compliance);
    }

    public function test_employee_operation_actions_keep_mobile_parity_and_busy_submission_feedback(): void
    {
        $root = base_path();
        $claims = file_get_contents($root.'/resources/views/hr/operations/partials/claims.blade.php');
        $claimActions = file_get_contents($root.'/resources/views/hr/operations/partials/claim-actions.blade.php');
        $loans = file_get_contents($root.'/resources/views/hr/operations/partials/loans.blade.php');
        $loanActions = file_get_contents($root.'/resources/views/hr/operations/partials/loan-actions.blade.php');
        $assetActions = file_get_contents($root.'/resources/views/hr/operations/partials/asset-actions.blade.php');
        $helpdeskActions = file_get_contents($root.'/resources/views/hr/operations/partials/helpdesk-actions.blade.php');

        $this->assertSame(2, substr_count($claims, "@include('hr.operations.partials.claim-actions'"));
        $this->assertSame(2, substr_count($loans, "@include('hr.operations.partials.loan-actions'"));
        $this->assertStringContainsString('aria-label="Approve claim', $claimActions);
        $this->assertStringContainsString('aria-label="Approve loan', $loanActions);
        $this->assertStringContainsString('x-data="serverFormState"', $claimActions);
        $this->assertStringContainsString('x-data="serverFormState"', $loanActions);
        $this->assertStringContainsString('x-data="serverFormState"', $assetActions);
        $this->assertStringContainsString('x-data="serverFormState"', $helpdeskActions);
        $this->assertStringContainsString('x-bind:disabled="busy"', $claimActions);
        $this->assertStringContainsString('x-bind:disabled="busy"', $loanActions);
    }

    public function test_shared_people_states_cover_operational_feedback_and_validation_focus(): void
    {
        $root = base_path();
        $state = file_get_contents($root.'/resources/views/components/hr/people-state.blade.php');
        $directory = file_get_contents($root.'/resources/views/hr/employees/index.blade.php');
        $profile = file_get_contents($root.'/resources/views/hr/employees/show.blade.php');
        $styles = file_get_contents($root.'/resources/css/hr-people.css');
        $script = file_get_contents($root.'/resources/js/app.js');

        foreach (['empty', 'filtered', 'restricted', 'retry', 'conflict', 'error', 'warning', 'success', 'loading'] as $type) {
            $this->assertStringContainsString("'{$type}' =>", $state);
        }

        $this->assertStringContainsString('data-people-state', $state);
        $this->assertStringContainsString('is-filtered', $styles);
        $this->assertStringContainsString('is-error', $styles);
        $this->assertStringContainsString('is-success', $styles);
        $this->assertStringContainsString("? 'filtered' : 'empty'", $directory);
        $this->assertStringContainsString('role="alert" tabindex="-1"', $directory);
        $this->assertStringContainsString('role="alert" tabindex="-1"', $profile);
        $this->assertStringContainsString('focusValidationSummary()', $script);
        $this->assertStringContainsString('[role="alert"][tabindex="-1"]', $script);
    }

    public function test_employee_operations_use_route_specific_read_model_presentation(): void
    {
        $root = base_path();
        $data = file_get_contents($root.'/app/Application/Hr/Data/EmployeeOperationsWorkspaceData.php');
        $workspace = file_get_contents($root.'/resources/views/hr/operations/workspace.blade.php');
        $partial = file_get_contents($root.'/resources/views/hr/operations/partials/people-workspace.blade.php');

        foreach (['Asset Management', 'Expense Claims', 'Loans & Advances', 'HR Helpdesk'] as $title) {
            $this->assertStringContainsString("'workspaceTitle' => '{$title}'", $data);
        }

        $this->assertStringContainsString("@section('title', \$workspacePageTitle)", $workspace);
        $this->assertStringContainsString(':title="$workspaceTitle"', $partial);
        $this->assertStringContainsString(':description="$workspaceDescription"', $partial);
        $this->assertStringNotContainsString('title="Employee Operations Workspace"', $partial);
    }

    public function test_reports_and_settings_use_the_shared_people_state_contract(): void
    {
        $root = base_path();
        $reports = file_get_contents($root.'/resources/views/hr/reports/index.blade.php');
        $settings = file_get_contents($root.'/resources/views/hr/settings/index.blade.php');

        $this->assertStringContainsString('<x-hr.people-state', $reports);
        $this->assertStringContainsString('type="restricted"', $reports);
        $this->assertStringContainsString('<x-hr.people-state type="filtered"', $settings);
        $this->assertStringNotContainsString('<div class="people-panel-empty">', $reports);
    }
}
