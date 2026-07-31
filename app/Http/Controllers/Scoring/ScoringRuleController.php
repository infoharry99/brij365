<?php

namespace App\Http\Controllers\Scoring;

use App\Application\Scoring\Actions\CreateScoringRuleDraft;
use App\Application\Scoring\Actions\EditScoringRuleDraft;
use App\Application\Scoring\Actions\UpdateScoringRuleDraft;
use App\Application\Scoring\Actions\ValidateScoringRule;
use App\Application\Scoring\Actions\SubmitScoringRule;
use App\Application\Scoring\Actions\ApproveScoringRule;
use App\Application\Scoring\Actions\ActivateScoringRule;
use App\Application\Scoring\Actions\CloneScoringRule;
use App\Application\Scoring\Actions\RejectScoringRule;
use App\Application\Scoring\Actions\RetireScoringRule;
use App\Application\Scoring\Actions\InspectScoringRule;
use App\Application\Scoring\Actions\ExportScoringRule;
use App\Application\Scoring\Actions\StartScoringRecalculation;
use App\Application\Scoring\DTOs\CreateScoringRuleData;
use App\Application\Scoring\DTOs\UpdateScoringRuleData;
use App\Domain\Scoring\Services\ScoringRuleConfigurationBuilder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scoring\StoreScoringRuleRequest;
use App\Http\Requests\Scoring\EditScoringRuleRequest;
use App\Http\Requests\Scoring\UpdateScoringRuleRequest;
use App\Http\Requests\Scoring\ValidateScoringRuleRequest;
use App\Http\Requests\Scoring\SubmitScoringRuleRequest;
use App\Http\Requests\Scoring\ApproveScoringRuleRequest;
use App\Http\Requests\Scoring\ActivateScoringRuleRequest;
use App\Http\Requests\Scoring\CloneScoringRuleRequest;
use App\Http\Requests\Scoring\RejectScoringRuleRequest;
use App\Http\Requests\Scoring\RetireScoringRuleRequest;
use App\Http\Requests\Scoring\ShowScoringRuleRequest;
use App\Http\Requests\Scoring\ExportScoringRuleRequest;
use App\Http\Requests\Scoring\RecalculateScoringRuleRequest;
use App\Models\ScoringRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ScoringRuleController extends Controller
{
    public function show(ShowScoringRuleRequest $request, ScoringRule $scoringRule, InspectScoringRule $inspect): View
    {
        return view('scoring.show', [
            'rule' => $inspect->handle($scoringRule, $request->user(), $request->integer('compare_to') ?: null),
        ]);
    }

    public function export(ExportScoringRuleRequest $request, ScoringRule $scoringRule, ExportScoringRule $export): StreamedResponse
    {
        $payload = $export->handle($scoringRule, $request->user(), $request);
        $filename = "scoring-{$scoringRule->rule_key}-v{$scoringRule->version}.json";

        return response()->streamDownload(
            static fn () => print json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }

    public function edit(EditScoringRuleRequest $request, ScoringRule $scoringRule, EditScoringRuleDraft $edit): View
    {
        return view('scoring.edit', ['rule' => $edit->handle($scoringRule)]);
    }

    public function store(StoreScoringRuleRequest $request, CreateScoringRuleDraft $create): RedirectResponse
    {
        $data = $request->validated();
        $rule = $create->handle(new CreateScoringRuleData(
            ruleKey: $data['rule_key'],
            name: $data['name'],
            changeReason: $data['change_reason'],
            effectiveAt: $data['effective_at'] ?? null,
        ), $request->user(), $request);

        return redirect()->route('scoring.index', ['view' => 'rule-history'])
            ->with('status', "Scoring rule {$rule->name} v{$rule->version} created as a draft.");
    }

    public function update(
        UpdateScoringRuleRequest $request,
        ScoringRule $scoringRule,
        ScoringRuleConfigurationBuilder $configurationBuilder,
        UpdateScoringRuleDraft $update,
    ): RedirectResponse {
        $validated = $request->validated();
        $rule = $update->handle($scoringRule, new UpdateScoringRuleData(
            name: $validated['name'],
            changeReason: $validated['change_reason'],
            effectiveAt: $validated['effective_at'] ?? null,
            configuration: $configurationBuilder->fromValidatedInput($validated),
        ), $request->user(), $request);

        return redirect()->route('scoring.index', ['view' => 'rule-history'])
            ->with('status', "Scoring rule {$rule->name} v{$rule->version} updated as a draft.");
    }

    public function validateRule(ValidateScoringRuleRequest $request, ScoringRule $scoringRule, ValidateScoringRule $validate): RedirectResponse
    {
        $rule = $validate->handle($scoringRule, $request->user(), $request);
        return back()->with('status', "Scoring rule {$rule->name} v{$rule->version} validated.");
    }

    public function submit(SubmitScoringRuleRequest $request, ScoringRule $scoringRule, SubmitScoringRule $submit): RedirectResponse
    {
        $rule = $submit->handle($scoringRule, $request->user(), $request);
        return back()->with('status', "Scoring rule {$rule->name} v{$rule->version} submitted for approval.");
    }

    public function approve(ApproveScoringRuleRequest $request, ScoringRule $scoringRule, ApproveScoringRule $approve): RedirectResponse
    {
        $rule = $approve->handle($scoringRule, $request->user(), $request);
        return back()->with('status', "Scoring rule {$rule->name} v{$rule->version} approved.");
    }

    public function activate(ActivateScoringRuleRequest $request, ScoringRule $scoringRule, ActivateScoringRule $activate): RedirectResponse
    {
        $rule = $activate->handle($scoringRule, $request->user(), $request);
        $verb = $rule->status === 'scheduled' ? 'scheduled' : 'activated';
        return back()->with('status', "Scoring rule {$rule->name} v{$rule->version} {$verb}.");
    }

    public function clone(CloneScoringRuleRequest $request, ScoringRule $scoringRule, CloneScoringRule $clone): RedirectResponse
    {
        $rule = $clone->handle($scoringRule, $request->validated('change_reason'), $request->user(), $request);

        return redirect()->route('scoring.rules.edit', $rule)
            ->with('status', "Scoring rule cloned as draft version {$rule->version}.");
    }

    public function rollback(CloneScoringRuleRequest $request, ScoringRule $scoringRule, CloneScoringRule $clone): RedirectResponse
    {
        $rule = $clone->handle($scoringRule, $request->validated('change_reason'), $request->user(), $request, true);

        return redirect()->route('scoring.rules.edit', $rule)
            ->with('status', "Rollback draft version {$rule->version} created for validation and approval.");
    }

    public function reject(RejectScoringRuleRequest $request, ScoringRule $scoringRule, RejectScoringRule $reject): RedirectResponse
    {
        $rule = $reject->handle($scoringRule, $request->validated('reason'), $request->user(), $request);

        return back()->with('status', "Scoring rule {$rule->name} v{$rule->version} returned for correction.");
    }

    public function retire(RetireScoringRuleRequest $request, ScoringRule $scoringRule, RetireScoringRule $retire): RedirectResponse
    {
        $rule = $retire->handle($scoringRule, $request->validated('reason'), $request->user(), $request);

        return back()->with('status', "Scoring rule {$rule->name} v{$rule->version} retired.");
    }

    public function recalculate(RecalculateScoringRuleRequest $request, ScoringRule $scoringRule, StartScoringRecalculation $recalculate): RedirectResponse
    {
        $run = $recalculate->handle($scoringRule, $request->user(), $request);

        return back()->with('status', "Recalculation run {$run->id} queued for {$run->total_records} eligible record(s).");
    }
}
