<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_can_list_company_scoped_users_and_roles(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $this->actingAs($admin)
            ->getJson(route('admin.roles.index'))
            ->assertOk()
            ->assertJsonFragment(['slug' => 'system_admin'])
            ->assertJsonStructure(['data' => ['*' => ['slug', 'name', 'scope_level', 'permissions', 'is_active', 'users_count']]]);

        $this->actingAs($admin)
            ->getJson(route('admin.users.index'))
            ->assertOk()
            ->assertJsonFragment(['email' => 'nikhil.desai@builder360.test'])
            ->assertJsonStructure(['data' => ['*' => ['name', 'email', 'status', 'role', 'company']]]);
    }

    public function test_admin_role_browser_workspace_renders_in_the_approved_classic_ui(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee('Role Administration')
            ->assertSee('class="b360-shell"', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($director)
            ->post(route('admin.roles.store'), [
                'slug' => 'blade_admin_test_role',
                'name' => 'Blade Admin Test Role',
                'scope_level' => 'company',
                'permissions' => ['users.view', 'reports.view'],
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.roles.index'))
            ->assertSessionHas('status');

        $role = Role::where('slug', 'blade_admin_test_role')->firstOrFail();

        $this->assertSame(['users.view', 'reports.view'], $role->permissions);

        $this->actingAs($director)
            ->patch(route('admin.roles.update', $role), [
                'name' => 'Blade Admin Test Role Updated',
                'scope_level' => 'readonly',
                'permissions' => ['users.view'],
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.roles.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'Blade Admin Test Role Updated',
            'scope_level' => 'readonly',
        ]);
    }

    public function test_admin_user_browser_workspace_renders_in_the_approved_classic_ui(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $role = Role::where('slug', 'recruiter')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('User Administration')
            ->assertSee('class="b360-shell"', false)
            ->assertDontSee('id="root"', false);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'company_id' => $company->id,
                'role_id' => $role->id,
                'name' => 'Blade User Admin',
                'email' => 'blade.user.admin@builder360.test',
                'password' => 'Builder360@123',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $created = User::where('email', 'blade.user.admin@builder360.test')->firstOrFail();

        $this->assertTrue(Hash::check('Builder360@123', $created->password));

        $this->actingAs($admin)
            ->patch(route('admin.users.access.update', $created), [
                'company_id' => $company->id,
                'role_id' => $role->id,
                'status' => 'suspended',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'id' => $created->id,
            'status' => 'suspended',
        ]);
    }

    public function test_company_creation_workspace_is_not_available_in_single_company_mode(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($director)
            ->get(route('admin.companies.index'))
            ->assertForbidden();

        $this->actingAs($director)
            ->post(route('admin.companies.store'), [
                'code' => 'B360X',
                'name' => 'Builder360 Expansion Pvt Ltd',
                'legal_name' => 'Builder360 Expansion Private Limited',
                'state' => 'MH',
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('companies', [
            'code' => 'B360X',
        ]);
    }

    public function test_admin_register_indexes_validate_filter_contracts(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $this->actingAs($admin)
            ->getJson(route('admin.roles.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($admin)
            ->getJson(route('admin.roles.index', ['status' => 'active']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($admin)
            ->getJson(route('admin.roles.index', ['scope_level' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scope_level']);

        $this->actingAs($admin)
            ->getJson(route('admin.users.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($admin)
            ->getJson(route('admin.users.index', ['scope_level' => 'company']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scope_level'])
            ->assertJsonPath('errors.scope_level.0', 'The selected filter is not available for this endpoint.');

        $this->actingAs($admin)
            ->getJson(route('admin.users.index', ['status' => 'deleted']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_system_admin_can_create_user_in_own_company_with_non_wildcard_role(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $role = Role::where('slug', 'recruiter')->firstOrFail();

        $this->actingAs($admin)
            ->postJson(route('admin.users.store'), [
                'company_id' => $company->id,
                'role_id' => $role->id,
                'name' => 'Operations Admin',
                'email' => 'operations.admin@builder360.test',
                'password' => 'Builder360@123',
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'operations.admin@builder360.test')
            ->assertJsonPath('data.role.slug', 'recruiter');

        $created = User::where('email', 'operations.admin@builder360.test')->firstOrFail();

        $this->assertTrue(Hash::check('Builder360@123', $created->password));
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'admin.user.created',
            'user_id' => $admin->id,
            'auditable_id' => $created->id,
        ]);
    }

    public function test_single_company_mode_blocks_additional_company_creation_for_all_roles(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $this->actingAs($admin)
            ->postJson(route('admin.companies.store'), [
                'code' => 'b360n',
                'name' => 'Builder360 North Pvt Ltd',
                'legal_name' => 'Builder360 North Private Limited',
                'state' => 'dl',
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('companies', ['code' => 'B360N']);

        $this->actingAs($partner)
            ->postJson(route('admin.companies.store'), [
                'code' => 'B360P2',
                'name' => 'Partner Attempt Company',
                'state' => 'MH',
            ])
            ->assertForbidden();
    }

    public function test_company_creation_validates_unique_code_and_required_fields(): void
    {
        $this->seed();
        config()->set('builder360.single_company.enabled', false);

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $this->actingAs($admin)
            ->postJson(route('admin.companies.store'), [
                'code' => 'B360D',
                'name' => '',
                'state' => '',
                'status' => 'archived',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name', 'state', 'status']);
    }

    public function test_non_wildcard_admin_cannot_create_user_in_another_company_or_assign_director(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $otherCompany = Company::where('code', 'B360P')->firstOrFail();
        $ownCompany = Company::where('code', 'B360D')->firstOrFail();
        $directorRole = Role::where('slug', 'director')->firstOrFail();
        $recruiterRole = Role::where('slug', 'recruiter')->firstOrFail();

        $this->actingAs($admin)
            ->postJson(route('admin.users.store'), [
                'company_id' => $otherCompany->id,
                'role_id' => $recruiterRole->id,
                'name' => 'Invalid Company User',
                'email' => 'invalid.company@builder360.test',
                'password' => 'Builder360@123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_id');

        $this->actingAs($admin)
            ->postJson(route('admin.users.store'), [
                'company_id' => $ownCompany->id,
                'role_id' => $directorRole->id,
                'name' => 'Invalid Director User',
                'email' => 'invalid.director@builder360.test',
                'password' => 'Builder360@123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role_id');
    }

    public function test_admin_user_creation_uses_central_strong_password_policy(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $role = Role::where('slug', 'recruiter')->firstOrFail();

        $this->actingAs($admin)
            ->postJson(route('admin.users.store'), [
                'company_id' => $company->id,
                'role_id' => $role->id,
                'name' => 'Weak Password User',
                'email' => 'weak.password.user@builder360.test',
                'password' => 'weakpassword1',
                'status' => 'active',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseMissing('users', [
            'email' => 'weak.password.user@builder360.test',
        ]);
    }

    public function test_access_update_is_scoped_and_audited(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $target = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();
        $payrollRole = Role::where('slug', 'payroll')->firstOrFail();

        $this->actingAs($admin)
            ->patchJson(route('admin.users.access.update', $target), [
                'company_id' => $target->company_id,
                'role_id' => $payrollRole->id,
                'status' => 'suspended',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended')
            ->assertJsonPath('data.role.slug', 'payroll');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'admin.user.access_updated',
            'user_id' => $admin->id,
            'auditable_id' => $target->id,
        ]);
    }

    public function test_non_wildcard_admin_cannot_update_a_wildcard_role_account(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $recruiterRole = Role::where('slug', 'recruiter')->firstOrFail();

        $this->actingAs($admin)
            ->patchJson(route('admin.users.access.update', $director), [
                'company_id' => $director->company_id,
                'role_id' => $recruiterRole->id,
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->assertSame('director', $director->fresh()->role?->slug);
    }

    public function test_director_can_create_and_update_role_but_partner_cannot_access_admin(): void
    {
        $this->seed();

        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $roleId = $this->actingAs($director)
            ->postJson(route('admin.roles.store'), [
                'slug' => 'site_ops_viewer',
                'name' => 'Site Operations Viewer',
                'scope_level' => 'department',
                'permissions' => ['construction.view', 'procurement.view', 'reports.view'],
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'site_ops_viewer')
            ->json('data.id');

        $role = Role::findOrFail($roleId);

        $this->actingAs($director)
            ->patchJson(route('admin.roles.update', $role), [
                'name' => 'Site Operations Read Only',
                'permissions' => ['construction.view', 'reports.view'],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Site Operations Read Only');

        $this->actingAs($partner)
            ->getJson(route('admin.users.index'))
            ->assertForbidden();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'admin.role.created',
            'user_id' => $director->id,
            'auditable_id' => $role->id,
        ]);
    }

    public function test_non_wildcard_admin_cannot_create_or_escalate_global_scope_roles(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $this->actingAs($admin)
            ->postJson(route('admin.roles.store'), [
                'slug' => 'invalid_global_viewer',
                'name' => 'Invalid Global Viewer',
                'scope_level' => 'global',
                'permissions' => ['users.view'],
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scope_level']);

        $roleId = $this->actingAs($admin)
            ->postJson(route('admin.roles.store'), [
                'slug' => 'company_user_viewer',
                'name' => 'Company User Viewer',
                'scope_level' => 'company',
                'permissions' => ['users.view'],
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.scope_level', 'company')
            ->json('data.id');

        $role = Role::findOrFail($roleId);

        $this->actingAs($admin)
            ->patchJson(route('admin.roles.update', $role), [
                'scope_level' => 'global',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scope_level']);
    }

    public function test_non_wildcard_admin_without_company_assignment_fails_closed_for_user_management(): void
    {
        $this->seed();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $company = Company::where('code', 'B360D')->firstOrFail();
        $target = User::where('email', 'ananya.sen@builder360.test')->firstOrFail();
        $recruiterRole = Role::where('slug', 'recruiter')->firstOrFail();

        $admin->forceFill(['company_id' => null])->save();

        $this->actingAs($admin)
            ->getJson(route('admin.users.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($admin)
            ->getJson(route('admin.users.index', ['company_id' => $company->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id']);

        $this->actingAs($admin)
            ->postJson(route('admin.users.store'), [
                'company_id' => $company->id,
                'role_id' => $recruiterRole->id,
                'name' => 'Invalid No Company Admin User',
                'email' => 'invalid.no-company.admin@builder360.test',
                'password' => 'Builder360@123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id']);

        $this->actingAs($admin)
            ->patchJson(route('admin.users.access.update', $target), [
                'company_id' => $target->company_id,
                'role_id' => $recruiterRole->id,
                'status' => 'suspended',
            ])
            ->assertForbidden();
    }
}
