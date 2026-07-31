<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Support\MessageBag;
use Tests\TestCase;

class BladeComponentSystemTest extends TestCase
{
    public function test_core_ui_components_render_semantic_accessible_markup(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.page-header title="Register" eyebrow="Module" description="Business records.">
                <x-slot:actions><x-ui.action href="/records">Open</x-ui.action></x-slot:actions>
            </x-ui.page-header>
            <x-ui.card title="Record" eyebrow="Details" meta="Active">Content</x-ui.card>
            <x-ui.badge tone="success">Approved</x-ui.badge>
            <x-ui.empty-state title="No records" description="Create a record to begin." />
        BLADE);

        $this->assertStringContainsString('<header class="blade-workspace-header">', $html);
        $this->assertStringContainsString('aria-label="Page actions"', $html);
        $this->assertStringContainsString('<article class="blade-dashboard-card">', $html);
        $this->assertStringContainsString('b360-tone-success', $html);
        $this->assertStringContainsString('aria-label="No records"', $html);
    }

    public function test_form_components_connect_labels_controls_hints_and_validation_regions(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-forms.field name="email" label="Email" hint="Business email" required>
                <x-forms.input name="email" type="email" required />
            </x-forms.field>
            <x-forms.field name="status" label="Status">
                <x-forms.select name="status"><option value="active">Active</option></x-forms.select>
            </x-forms.field>
            <x-forms.field name="note" label="Note">
                <x-forms.textarea name="note">Context</x-forms.textarea>
            </x-forms.field>
        BLADE);

        $this->assertStringContainsString('for="email"', $html);
        $this->assertStringContainsString('id="email"', $html);
        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('Business email', $html);
        $this->assertStringContainsString('id="status"', $html);
        $this->assertStringContainsString('id="note"', $html);
    }

    public function test_form_field_resolves_nested_bracket_validation_keys(): void
    {
        $errors = (new ViewErrorBag)->put('default', new MessageBag([
            'value.company_timezone' => ['A valid IANA timezone is required.'],
        ]));
        view()->share('errors', $errors);

        $html = Blade::render(<<<'BLADE'
            <x-forms.field name="value[company_timezone]" for="company-timezone" label="Company timezone">
                <x-forms.input id="company-timezone" name="value[company_timezone]" />
            </x-forms.field>
        BLADE);

        $this->assertStringContainsString('for="company-timezone"', $html);
        $this->assertStringContainsString('A valid IANA timezone is required.', $html);
        $this->assertStringContainsString('role="alert"', $html);
    }

    public function test_responsive_register_requires_parallel_desktop_and_mobile_slots(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.responsive-register label="Orders">
                <x-slot:desktop><table><tr><td>ORD-1</td></tr></table></x-slot:desktop>
                <x-slot:mobile><article>ORD-1</article></x-slot:mobile>
            </x-ui.responsive-register>
        BLADE);

        $this->assertStringContainsString('aria-label="Orders"', $html);
        $this->assertStringContainsString('b360-register-desktop', $html);
        $this->assertStringContainsString('b360-register-mobile', $html);
        $this->assertSame(2, substr_count($html, 'ORD-1'));
    }

    public function test_interactive_components_use_named_csp_safe_alpine_state_and_dialog_semantics(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.alert tone="danger" dismissible>Unable to continue.</x-ui.alert>
            <x-ui.dropdown label="Options"><a href="/one" role="menuitem">One</a></x-ui.dropdown>
            <x-ui.modal id="create-record" title="Create record" trigger="Create">Form</x-ui.modal>
            <x-ui.drawer id="record-details" title="Record details" trigger="Details">Details</x-ui.drawer>
            <x-ui.tab-set initial="details">
                <x-ui.tab-list><x-ui.tab name="details">Details</x-ui.tab></x-ui.tab-list>
                <x-ui.tab-panel name="details">Panel</x-ui.tab-panel>
            </x-ui.tab-set>
        BLADE);

        $this->assertStringContainsString('x-data="dismissibleAlert"', $html);
        $this->assertStringContainsString('x-data="togglePanel"', $html);
        $this->assertSame(2, substr_count($html, 'aria-modal="true"'));
        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertStringContainsString('role="tab"', $html);
        $this->assertStringContainsString('role="tabpanel"', $html);
    }
}
