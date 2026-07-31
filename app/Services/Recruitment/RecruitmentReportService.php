<?php

namespace App\Services\Recruitment;

use App\Models\Candidate;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use Illuminate\Support\Collection;

class RecruitmentReportService
{
    public function __construct(private readonly CompanyScopeService $companyScope)
    {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function sourceSummary(User $actor, array $filters): array
    {
        $query = Candidate::query()
            ->with(['offer:id,candidate_id,status', 'jobOpening:id,department'])
            ->select([
                'id',
                'company_id',
                'job_opening_id',
                'source',
                'stage',
                'status',
                'created_at',
            ]);

        $this->companyScope->apply($query, $actor);

        $query
            ->when($filters['company_id'] ?? null, fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->when($filters['source'] ?? null, fn ($query, string $source) => $query->where('source', $source))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['department'] ?? null, function ($query, string $department): void {
                $query->whereHas('jobOpening', fn ($jobOpeningQuery) => $jobOpeningQuery->where('department', $department));
            });

        $candidates = $query->get();
        $rows = $this->sourceRows($candidates);

        return [
            'scope' => [
                'company_id' => $filters['company_id'] ?? $this->companyScope->companyIdFor($actor),
                'global' => $this->companyScope->hasUnrestrictedCompanyScope($actor),
            ],
            'filters' => [
                'source' => $filters['source'] ?? null,
                'department' => $filters['department'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
            'totals' => [
                'sources' => $rows->count(),
                'candidates' => $candidates->count(),
                'interviewed' => $candidates->whereIn('stage', ['interviewed', 'offer_draft', 'offer_released', 'employee_created'])->count(),
                'offers' => $candidates->filter(fn (Candidate $candidate): bool => in_array($candidate->offer?->status, ['draft', 'released', 'accepted'], true))->count(),
                'converted' => $candidates->where('status', 'converted')->count(),
                'rejected' => $candidates->where('status', 'rejected')->count(),
            ],
            'rows' => $rows->values()->all(),
        ];
    }

    /**
     * @param Collection<int, Candidate> $candidates
     * @return Collection<int, array<string, mixed>>
     */
    private function sourceRows(Collection $candidates): Collection
    {
        return $candidates
            ->groupBy(fn (Candidate $candidate): string => trim((string) $candidate->source) !== '' ? (string) $candidate->source : 'Unspecified')
            ->map(function (Collection $sourceCandidates, string $source): array {
                $total = $sourceCandidates->count();
                $interviewed = $sourceCandidates->whereIn('stage', ['interviewed', 'offer_draft', 'offer_released', 'employee_created'])->count();
                $offers = $sourceCandidates->filter(fn (Candidate $candidate): bool => in_array($candidate->offer?->status, ['draft', 'released', 'accepted'], true))->count();
                $acceptedOffers = $sourceCandidates->filter(fn (Candidate $candidate): bool => $candidate->offer?->status === 'accepted')->count();
                $converted = $sourceCandidates->where('status', 'converted')->count();
                $rejected = $sourceCandidates->where('status', 'rejected')->count();

                return [
                    'source' => $source,
                    'total_candidates' => $total,
                    'active_candidates' => $sourceCandidates->where('status', 'active')->count(),
                    'interviewed_candidates' => $interviewed,
                    'offer_count' => $offers,
                    'accepted_offer_count' => $acceptedOffers,
                    'converted_candidates' => $converted,
                    'rejected_candidates' => $rejected,
                    'interview_rate' => $this->percentage($interviewed, $total),
                    'offer_rate' => $this->percentage($offers, $total),
                    'conversion_rate' => $this->percentage($converted, $total),
                    'rejection_rate' => $this->percentage($rejected, $total),
                ];
            })
            ->sortByDesc('total_candidates')
            ->values();
    }

    private function percentage(int $part, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($part / $total) * 100, 2);
    }
}
