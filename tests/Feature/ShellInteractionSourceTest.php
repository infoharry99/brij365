<?php

namespace Tests\Feature;

use Tests\TestCase;

class ShellInteractionSourceTest extends TestCase
{
    public function test_shell_has_one_vite_managed_alpine_controller_and_complete_toggle_contract(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));
        $layout = file_get_contents(resource_path('views/layouts/builder360-classic.blade.php'));
        $sidebar = file_get_contents(resource_path('views/builder360/classic/partials/sidebar.blade.php'));
        $topbar = file_get_contents(resource_path('views/builder360/classic/partials/topbar.blade.php'));
        $enterpriseCss = file_get_contents(resource_path('css/enterprise.css'));

        $this->assertIsString($app);
        $this->assertIsString($layout);
        $this->assertIsString($sidebar);
        $this->assertIsString($topbar);
        $this->assertIsString($enterpriseCss);

        $this->assertSame(1, substr_count($app, "Alpine.data('builderShell'"));
        $this->assertStringContainsString('handleMenuToggle(event)', $app);
        $this->assertStringContainsString('toggleSidebar()', $app);
        $this->assertStringContainsString('handleResize()', $app);
        $this->assertStringContainsString('async changeTheme(event)', $app);
        $this->assertStringContainsString("document.documentElement.dataset.theme = previousTheme", $app);
        $this->assertStringNotContainsString('function builderShell()', $sidebar);
        $this->assertStringContainsString('x-on:click="handleMenuToggle"', $topbar);
        $this->assertStringContainsString('x-on:click="toggleSidebar"', $sidebar);
        $this->assertStringContainsString("'sidebar-collapsed'", $app);
        $this->assertStringContainsString("'nav-open'", $app);
        $this->assertStringContainsString('x-on:resize.window="handleResize"', $layout);
        $this->assertStringContainsString('body.b360-classic .b360-shell', $enterpriseCss);
        $this->assertStringContainsString('height: 100dvh;', $enterpriseCss);
        $this->assertStringContainsString('overflow: hidden;', $enterpriseCss);
        $this->assertStringContainsString('body.b360-classic.sidebar-collapsed .b360-sidebar', $enterpriseCss);
        $this->assertStringContainsString('flex-basis: 64px;', $enterpriseCss);
        $this->assertStringContainsString('body.b360-classic .b360-content', $enterpriseCss);
        $this->assertStringContainsString('overflow-y: auto;', $enterpriseCss);
        $this->assertMatchesRegularExpression(
            '/body\.b360-classic \.b360-main\s*\{[^}]*width:\s*0;[^}]*flex:\s*1 1 0;/s',
            $enterpriseCss,
        );
    }

    public function test_task_workspace_uses_an_explicit_independent_visibility_contract(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));
        $view = file_get_contents(resource_path('views/collaboration/tasks/index.blade.php'));
        $taskCss = file_get_contents(resource_path('css/task-calendar.css'));
        $appCss = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($app);
        $this->assertIsString($view);
        $this->assertIsString($taskCss);
        $this->assertIsString($appCss);

        $this->assertStringContainsString('builder360.task.workspace.open', $app);
        $this->assertStringContainsString("'workspace-hidden'", $app);
        $this->assertStringContainsString("'workspace-open'", $app);
        $this->assertStringContainsString('x-on:click="toggleRail"', $view);
        $this->assertStringContainsString('x-on:click="closeRail"', $view);
        $this->assertStringContainsString('.tm-rail.workspace-hidden', $view);
        $this->assertStringNotContainsString('.tm-rail.collapsed', $view);
        $this->assertStringNotContainsString('.tm-rail.collapsed', $taskCss);
        $this->assertStringNotContainsString('.tm-rail.collapsed', $appCss);
    }

    public function test_task_drawer_uses_one_container_responsive_density_contract(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));
        $drawer = file_get_contents(resource_path('views/collaboration/tasks/partials/drawer.blade.php'));
        $taskView = file_get_contents(resource_path('views/collaboration/tasks/index.blade.php'));
        $drawerCss = file_get_contents(resource_path('css/task-drawer.css'));
        $enterpriseCss = file_get_contents(resource_path('css/enterprise.css'));

        $this->assertIsString($app);
        $this->assertIsString($drawer);
        $this->assertIsString($taskView);
        $this->assertIsString($drawerCss);
        $this->assertIsString($enterpriseCss);

        $this->assertStringContainsString('@import "./task-drawer.css" layer(reference);', $enterpriseCss);
        $this->assertStringContainsString('container: task-drawer / inline-size;', $drawerCss);
        $this->assertStringContainsString('@container task-drawer (max-width: 819px)', $drawerCss);
        $this->assertStringContainsString('height: 100dvh;', $drawerCss);
        $this->assertStringContainsString('overflow: hidden;', $drawerCss);
        $this->assertStringNotContainsString('min-height: 78px;', $drawerCss);
        $this->assertStringContainsString('Legacy drawer geometry is intentionally inactive', $taskView);
        $this->assertStringContainsString('@media not all', $taskView);

        $this->assertStringContainsString('Task Info', $drawer);
        $this->assertStringContainsString('data-task-panel', $drawer);
        $this->assertStringContainsString('x-on:keydown="navigateTabs"', $drawer);
        $this->assertSame(1, substr_count($drawer, 'id="task-panel-info"'));
        $this->assertSame(1, substr_count($drawer, 'class="tm-dr-side"'));

        $this->assertStringContainsString('compactInfo: false', $app);
        $this->assertStringContainsString("'ResizeObserver' in window", $app);
        $this->assertStringContainsString('this.drawerObserver?.disconnect()', $app);
        $this->assertStringContainsString('navigateTabs(event)', $app);
        $this->assertStringContainsString('width < 820', $app);
    }

    public function test_profile_and_affected_ui_use_shared_semantic_theme_tokens(): void
    {
        $profile = file_get_contents(resource_path('views/builder360/classic/profile.blade.php'));
        $enterpriseCss = file_get_contents(resource_path('css/enterprise.css'));

        $this->assertIsString($profile);
        $this->assertIsString($enterpriseCss);

        $this->assertStringContainsString('b360-profile-page', $profile);
        $this->assertStringContainsString('Managed by administrator', $profile);
        $this->assertStringContainsString('Preferences', $profile);
        $this->assertStringContainsString('profilePhotoPicker', $profile);
        $this->assertStringNotContainsString('<style', $profile);
        $this->assertStringContainsString('--b360-canvas:', $enterpriseCss);
        $this->assertStringContainsString('--b360-surface:', $enterpriseCss);
        $this->assertStringContainsString('--b360-text:', $enterpriseCss);
        $this->assertStringContainsString('--b360-input:', $enterpriseCss);
        $this->assertStringContainsString('html[data-theme="dark"]', $enterpriseCss);
        $this->assertStringContainsString('color-scheme:', $enterpriseCss);
    }

    public function test_task_movement_activity_and_people_picker_sources_are_fully_wired(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));
        $views = file_get_contents(resource_path('views/collaboration/tasks/partials/views.blade.php'));
        $card = file_get_contents(resource_path('views/collaboration/tasks/partials/task-card.blade.php'));
        $drawer = file_get_contents(resource_path('views/collaboration/tasks/partials/drawer.blade.php'));
        $insights = file_get_contents(resource_path('views/collaboration/tasks/partials/insights.blade.php'));
        $taskCss = file_get_contents(resource_path('css/task-calendar.css'));

        $this->assertStringNotContainsString('IlluminateSupportCarbon', $insights);
        $this->assertStringNotContainsString('kanban-dnd.js', $views.$card);
        $this->assertStringContainsString("Alpine.data('taskBoard'", $app);
        $this->assertStringContainsString("Alpine.data('taskStatusForm'", $app);
        $this->assertStringContainsString('x-on:drop="dropTask"', $views);
        $this->assertStringContainsString('data-allowed-targets', $card);
        $this->assertStringContainsString('tm-card-drag-handle', $card);
        $this->assertStringContainsString('taskStatusForm', $views);
        $this->assertStringContainsString('tm-kanban-viewport', $views);
        $this->assertStringContainsString('tm-board-nav', $views);
        $this->assertStringContainsString('tm-assignee-overlay', $drawer);
        $this->assertStringContainsString('taskTransferForm', $drawer);
        $this->assertStringContainsString('lock_version', $drawer);
        $this->assertStringContainsString('.tm-kanban-track{display:flex', $taskCss);
        $this->assertStringContainsString('width:max-content', $taskCss);
        $this->assertStringContainsString('.tm-assignee-overlay{position:fixed', $taskCss);
    }
}
