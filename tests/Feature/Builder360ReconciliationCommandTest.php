<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class Builder360ReconciliationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_command_passes_for_seeded_single_company_data(): void
    {
        $this->seed();

        $exitCode = Artisan::call('builder360:reconcile', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($payload['company']['configured_company_found']);
        $this->assertTrue($payload['checks']['company_scope_clean']);
        $this->assertTrue($payload['checks']['project_references_clean']);
    }

    public function test_reconciliation_command_fails_when_more_than_one_company_is_active(): void
    {
        $this->seed();

        Company::query()->create([
            'code' => 'OTHER',
            'name' => 'Other Company',
            'legal_name' => 'Other Company Private Limited',
            'state' => 'MH',
            'status' => 'active',
        ]);

        $exitCode = Artisan::call('builder360:reconcile', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertFalse($payload['checks']['one_active_company']);
    }
}
