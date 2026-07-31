<?php

namespace Tests\Unit;

use App\Domain\Payroll\Services\StatutoryRulePackDefinitionValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StatutoryRulePackDefinitionValidatorTest extends TestCase
{
    public function test_it_accepts_stable_unique_basis_and_calculation_line_codes(): void
    {
        $definition = $this->definition();
        $definition['jurisdictions'][0]['lines'][] = [
            'code' => 'CONTROLLED_FIXED',
            'name' => 'Controlled fixed adjustment',
            'line_type' => 'deduction',
            'method' => 'fixed_minor',
            'fixed_minor' => 0,
        ];

        (new StatutoryRulePackDefinitionValidator)->assertValid($definition);

        $this->addToAssertionCount(1);
    }

    public function test_it_rejects_unstable_or_empty_basis_codes(): void
    {
        $definition = $this->definition();
        $definition['jurisdictions'][0]['lines'][0]['basis_codes'] = ['BASIC', ' ', 'not a code', 42];

        $errors = $this->validationErrors($definition);

        $this->assertArrayHasKey('value.jurisdictions.0.lines.0.basis_codes', $errors);
        $this->assertContains('Basis codes must be stable, non-empty identifiers.', $errors['value.jurisdictions.0.lines.0.basis_codes']);
    }

    public function test_it_rejects_case_insensitive_duplicate_basis_and_calculation_line_codes(): void
    {
        $definition = $this->definition();
        $definition['jurisdictions'][0]['lines'][0]['basis_codes'] = ['BASIC', ' basic '];
        $duplicateLine = $definition['jurisdictions'][0]['lines'][0];
        $duplicateLine['code'] = strtolower($duplicateLine['code']);
        $definition['jurisdictions'][0]['lines'][] = $duplicateLine;

        $errors = $this->validationErrors($definition);

        $this->assertContains(
            'Basis codes must be unique without regard to letter case.',
            $errors['value.jurisdictions.0.lines.0.basis_codes'] ?? [],
        );
        $this->assertContains(
            'Calculation line codes must be unique within a jurisdiction.',
            $errors['value.jurisdictions.0.lines.1.code'] ?? [],
        );
    }

    public function test_annual_tax_projection_rejects_gross_and_taxable_aliases_together(): void
    {
        $definition = $this->definition();
        $definition['jurisdictions'][0]['lines'][0]['basis_codes'] = ['GROSS_EARNINGS', 'taxable_earnings'];

        $errors = $this->validationErrors($definition);

        $this->assertContains(
            'Annual tax projection cannot combine GROSS_EARNINGS and TAXABLE_EARNINGS because both resolve to the same gross basis.',
            $errors['value.jurisdictions.0.lines.0.basis_codes'] ?? [],
        );
    }

    /** @return array<string, list<string>> */
    private function validationErrors(array $definition): array
    {
        try {
            (new StatutoryRulePackDefinitionValidator)->assertValid($definition);
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        $this->fail('Expected the statutory rule-pack definition to be rejected.');
    }

    /** @return array<string, mixed> */
    private function definition(): array
    {
        return [
            'governed_statutory_pack_version' => 1,
            'statutory_validation_required' => true,
            'approval_chain' => ['independent_source_verifier', 'independent_rule_approver'],
            'source_evidence' => [[
                'authority' => 'Controlled Government Source Test Fixture',
                'title' => 'Synthetic source descriptor for validator testing',
                'document_reference' => 'TEST-ONLY-NOT-A-STATUTORY-CITATION',
                'source_type' => 'official_government',
                'url' => 'https://labour.gov.in/test-only-governance-fixture',
                'source_checksum' => hash('sha256', 'controlled-validator-source-fixture'),
                'published_or_accessed_on' => '2026-07-18',
            ]],
            'attendance_proration' => ['enabled' => false, 'component_codes' => []],
            'jurisdictions' => [[
                'type' => 'central',
                'code' => 'INDIA',
                'state_resolution' => 'allow_no_match',
                'effective_from' => '2026-04-01',
                'effective_to' => null,
                'applicability' => [],
                'lines' => [[
                    'code' => 'CONTROLLED_TDS',
                    'name' => 'Controlled annual tax projection',
                    'line_type' => 'deduction',
                    'method' => 'annual_tax_projection',
                    'basis_codes' => ['GROSS_EARNINGS'],
                    'projection' => [
                        'financial_year_start_month' => 4,
                        'regime_slabs' => [
                            'CONTROLLED' => [
                                ['from_minor' => 0, 'to_minor' => null, 'rate_ppm' => 100_000],
                            ],
                        ],
                        'standard_deduction_minor' => ['CONTROLLED' => 0],
                        'rebate' => [
                            'CONTROLLED' => [
                                'taxable_income_max_minor' => 0,
                                'rebate_minor' => 0,
                            ],
                        ],
                        'post_tax_rate_ppm' => 0,
                        'withholding_component_codes' => ['TDS'],
                    ],
                ]],
            ]],
        ];
    }
}
