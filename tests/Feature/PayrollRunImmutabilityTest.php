<?php

namespace Tests\Feature;

use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollRunImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_payroll_run_and_items_remain_mutable_before_approval(): void
    {
        [$run, $item] = $this->generateRun();

        $run->forceFill([
            'metadata' => array_merge($run->metadata ?? [], ['pre_approval_reviewed' => true]),
        ])->save();

        $item->forceFill([
            'component_breakup' => array_merge($item->component_breakup ?? [], ['pre_approval_note' => 'reviewed']),
        ])->save();

        $this->assertTrue((bool) data_get($run->fresh()->metadata, 'pre_approval_reviewed'));
        $this->assertSame('reviewed', data_get($item->fresh()->component_breakup, 'pre_approval_note'));
    }

    public function test_approved_payroll_run_and_existing_items_cannot_be_changed_or_deleted(): void
    {
        [$run, $item, $finance] = $this->generateRun();

        $this->actingAs($finance)
            ->patchJson(route('payroll.runs.approve', $run), ['note' => 'Approved after review.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $runId = $run->id;
        $itemId = $item->id;
        $originalNetPayable = $run->fresh()->net_payable;
        $originalBreakup = $item->fresh()->component_breakup;

        $this->assertImmutableMutation(
            fn (): bool => PayrollRun::findOrFail($runId)->forceFill(['net_payable' => '0.00'])->save(),
            'Approved payroll runs are immutable.',
        );
        $this->assertImmutableMutation(
            fn (): ?bool => PayrollRun::findOrFail($runId)->delete(),
            'Approved payroll runs are immutable.',
        );
        $this->assertImmutableMutation(
            fn (): bool => PayrollRunItem::findOrFail($itemId)->forceFill(['component_breakup' => []])->save(),
            'Approved payroll run items are immutable.',
        );
        $this->assertImmutableMutation(
            fn (): ?bool => PayrollRunItem::findOrFail($itemId)->delete(),
            'Approved payroll run items are immutable.',
        );

        $this->assertDatabaseHas('payroll_runs', [
            'id' => $runId,
            'status' => 'approved',
            'net_payable' => $originalNetPayable,
            'deleted_at' => null,
        ]);
        $this->assertSame($originalBreakup, PayrollRunItem::findOrFail($itemId)->component_breakup);
    }

    /** @return array{PayrollRun, PayrollRunItem, User} */
    private function generateRun(): array
    {
        $this->seed();

        $payroll = User::where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $finance = User::where('email', 'suresh.iyer@builder360.test')->firstOrFail();
        $period = now()->addMonthsNoOverflow(24);

        $runNumber = $this->actingAs($payroll)
            ->postJson(route('payroll.runs.generate'), [
                'period_year' => $period->year,
                'period_month' => $period->month,
                'working_days' => 26,
            ])
            ->assertCreated()
            ->json('data.run_number');

        $run = PayrollRun::where('run_number', $runNumber)->firstOrFail();
        $item = $run->items()->firstOrFail();

        return [$run, $item, $finance];
    }

    private function assertImmutableMutation(callable $mutation, string $message): void
    {
        try {
            $mutation();
            $this->fail('Expected the approved payroll immutability guard to reject the mutation.');
        } catch (\LogicException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }
}
