<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class LegacyFrontendWiringTest extends TestCase
{
    public function test_blade_views_do_not_mount_spa_assets(): void
    {
        foreach ($this->bladeFiles() as $file) {
            $contents = file_get_contents($file->getPathname());

            $this->assertIsString($contents);
            $this->assertStringNotContainsString('id="root"', $contents, $file->getPathname());
            $this->assertStringNotContainsString('createRoot(', $contents, $file->getPathname());
            $this->assertStringNotContainsString('ReactDOM', $contents, $file->getPathname());
        }
    }

    public function test_business_workspaces_use_the_shared_classic_layout(): void
    {
        $views = [
            'projects/index.blade.php',
            'crm/leads/index.blade.php',
            'finance/dashboard.blade.php',
            'construction/progress/index.blade.php',
            'hr/attendance/workspace.blade.php',
            'payroll/workspace/index.blade.php',
            'recruitment/workspace/index.blade.php',
            'procurement/workspace/index.blade.php',
            'admin/users/index.blade.php',
            'admin/roles/index.blade.php',
            'buyer/summary.blade.php',
            'partner/summary.blade.php',
        ];

        foreach ($views as $view) {
            $contents = file_get_contents(resource_path('views/'.$view));
            $this->assertIsString($contents);
            $this->assertStringContainsString("@extends('layouts.builder360-classic')", $contents, $view);
        }
    }

    public function test_shared_layout_is_presentational_and_uses_a_view_composer_for_shell_data(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/builder360-classic.blade.php'));
        $provider = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertIsString($layout);
        $this->assertIsString($provider);
        $this->assertStringNotContainsString('Builder360Bootstrap', $layout);
        $this->assertStringNotContainsString('app(Builder360Bootstrap', $layout);
        $this->assertStringContainsString('Builder360ShellComposer::class', $provider);
        $this->assertFileExists(app_path('View/Composers/Builder360ShellComposer.php'));
    }

    public function test_collaboration_workspaces_are_server_rendered_and_form_driven(): void
    {
        foreach ([
            'collaboration/tasks/index.blade.php',
            'collaboration/calendar-events/index.blade.php',
            'collaboration/chat/index.blade.php',
            'collaboration/messages/index.blade.php',
        ] as $view) {
            $contents = file_get_contents(resource_path('views/'.$view));
            $this->assertIsString($contents);
            $source = $contents;
            $partials = dirname(resource_path('views/'.$view)).'/partials';

            if (is_dir($partials)) {
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($partials)) as $partial) {
                    if ($partial instanceof SplFileInfo && $partial->isFile() && $partial->getExtension() === 'php') {
                        $source .= file_get_contents($partial->getPathname());
                    }
                }
            }

            $this->assertStringContainsString("@extends('layouts.builder360-classic')", $contents);
            $this->assertStringContainsString('<form', $source);
            $this->assertStringContainsString('@csrf', $source);
            $this->assertStringNotContainsString('fetch(', $source);
        }

        $chat = file_get_contents(resource_path('views/collaboration/chat/index.blade.php'));
        $chatTimeline = file_get_contents(resource_path('views/collaboration/chat/partials/timeline.blade.php'));
        $chatList = file_get_contents(resource_path('views/collaboration/chat/partials/conversation-list.blade.php'));
        $mailbox = file_get_contents(resource_path('views/collaboration/messages/index.blade.php'));
        $styles = file_get_contents(public_path('css/builder360-classic.css'));
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('b360-chat-screen', $chat);
        $this->assertStringContainsString('b360-thread-timeline', $chatTimeline);
        $this->assertStringContainsString('b360-thread-composer', $chat);
        $this->assertStringContainsString('b360-composer-stack', $chat);
        $this->assertStringContainsString('b360-chat-selected-file', $chat);
        $this->assertStringContainsString('x-on:click="removeAttachment"', $chat);
        $this->assertStringContainsString('b360-chat-create-panel', $chat);
        $this->assertStringContainsString('b360-chat-member-picker', $chat);
        $this->assertStringContainsString('b360-chat-search-clear', $chat);
        $this->assertStringContainsString('$searchBaseQuery', $chat);
        $this->assertStringContainsString('cc-search-summary', $chatList);
        $this->assertStringContainsString('cc-section-head', $chatList);
        $chatRow = file_get_contents(resource_path('views/collaboration/chat/partials/conversation-row.blade.php'));
        $this->assertStringContainsString('cc-conv-row', $chatRow);
        $this->assertStringContainsString('aria-current="page"', $chatRow);
        $this->assertStringContainsString('x-on:change="togglePerson"', $chat);
        $this->assertStringContainsString('x-bind:disabled="!canCreateConversation"', $chat);
        $this->assertStringNotContainsString('id="chat-members"', $chat);
        $this->assertStringNotContainsString('multiple size="7"', $chat);
        $this->assertStringContainsString('b360-mention-picker', $chat);
        $this->assertStringContainsString('name="metadata[mentions][]"', $chat);
        $this->assertStringContainsString('name="composer-panels"', $chat);
        $this->assertStringNotContainsString('aria-label="Message options"', $chat);
        $this->assertStringContainsString('name="parent_message_id" hidden', $chat);
        $this->assertStringContainsString('type="hidden" name="priority" value="normal"', $chat);
        $this->assertStringContainsString('b360-poll-dialog', $chat);
        $this->assertStringContainsString('x-data="pollComposer"', $chat);
        $this->assertStringContainsString('x-on:click="addPollOption"', $chat);
        $this->assertStringContainsString('x-on:click="removePollOption"', $chat);
        $this->assertStringContainsString('x-on:input="handleComposerInput"', $chat);
        $this->assertStringContainsString('x-ref="mentionSearch"', $chat);
        $this->assertStringContainsString('>Groups<', $chatList);
        $this->assertStringContainsString('>Channels<', $chatList);
        $this->assertLessThan(
            strpos($chat, '<div class="b360-composer-tools">'),
            strpos($chat, 'class="b360-chat-attachment-selection"'),
        );
        $this->assertStringContainsString('b360-mailbox-screen', $mailbox);
        $this->assertStringContainsString('b360-mail-list-pane', $mailbox);
        $this->assertStringContainsString('b360-mail-reading-pane', $mailbox);
        $this->assertStringContainsString('.b360-mailbox-screen { grid-template-columns:', $styles);
        $this->assertStringContainsString('.b360-chat-screen { grid-template-columns:', $styles);
        $this->assertStringContainsString('.b360-collab-tabs { display: flex; flex-wrap: wrap;', $styles);
        $this->assertStringContainsString('scrollbar-color: #b7bac4 transparent', $styles);
        $this->assertStringContainsString('.b360-composer-stack > .b360-chat-poll-create', $styles);
        $this->assertStringContainsString("form.getAttribute('action')", $app);
        $this->assertStringContainsString('new DataTransfer()', $app);
        $this->assertStringContainsString("Alpine.data('pollComposer'", $app);
        $this->assertStringContainsString('closeComposerPanel(event)', $app);
        $this->assertStringContainsString('changeConversationType()', $app);
        $this->assertStringContainsString('get canCreateConversation()', $app);
        $this->assertStringContainsString('toggleMention(event)', $app);
        $this->assertStringContainsString('handleComposerInput(event)', $app);
        $this->assertStringContainsString('this.$refs.mentionMenu.open = true', $app);
        $this->assertStringNotContainsString('this.request(form.action', $app);
    }

    public function test_role_project_and_period_contexts_use_standard_server_forms(): void
    {
        $topbar = file_get_contents(resource_path('views/builder360/classic/partials/topbar.blade.php'));
        $dashboard = file_get_contents(resource_path('views/builder360/classic/dashboard.blade.php'));

        $this->assertIsString($topbar);
        $this->assertIsString($dashboard);
        $this->assertStringContainsString("route('builder360.project-context.store')", $topbar);
        $this->assertStringContainsString("route('builder360.role-context.store')", $topbar);
        $this->assertStringContainsString("route('builder360.dashboard-context.store')", $dashboard);
        $this->assertStringContainsString('name="period_key"', $dashboard);
    }

    public function test_enterprise_blade_assets_use_vite_tailwind_and_alpine_without_spa_frameworks(): void
    {
        $this->assertFileExists(public_path('css/builder360-classic.css'));
        $this->assertFileExists(public_path('js/builder360-classic.js'));
        $this->assertFileExists(base_path('package.json'));
        $this->assertFileExists(base_path('package-lock.json'));
        $this->assertFileExists(base_path('vite.config.js'));
        $this->assertFileExists(resource_path('css/enterprise.css'));

        $layout = file_get_contents(resource_path('views/layouts/builder360-classic.blade.php'));
        $app = file_get_contents(resource_path('js/app.js'));
        $package = file_get_contents(base_path('package.json'));

        $this->assertIsString($layout);
        $this->assertIsString($app);
        $this->assertIsString($package);
        $this->assertStringContainsString("@vite(['resources/css/enterprise.css', 'resources/js/app.js'])", $layout);
        $this->assertStringContainsString("import Alpine from '@alpinejs/csp'", $app);
        $this->assertStringContainsString("import '@fortawesome/fontawesome-free/css/all.min.css'", $app);
        $this->assertStringContainsString('Alpine.start()', $app);
        $this->assertStringNotContainsString("asset('css/builder360-classic.css')", $layout);
        $this->assertStringNotContainsString('bootstrap@', $layout);
        $this->assertStringNotContainsString('bootstrap.bundle', $layout);
        $this->assertStringNotContainsString('cdnjs.cloudflare.com', $layout);
        $this->assertStringNotContainsString("asset('js/builder360-classic.js')", $layout);
        $this->assertStringContainsString('x-data="builderShell"', $layout);
        $this->assertStringNotContainsString('react', strtolower($package));
        $this->assertStringNotContainsString('vue', strtolower($package));
        $this->assertStringNotContainsString('inertia', strtolower($package));

        $routes = file_get_contents(base_path('routes/web.php'));
        $this->assertIsString($routes);
        $this->assertStringNotContainsString("view('builder360'", $routes);
    }

    /** @return array<int, SplFileInfo> */
    private function bladeFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file;
            }
        }

        return $files;
    }
}
