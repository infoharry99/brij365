<?php

namespace App\Support;

class PaginationPolicy
{
    public function defaultMaxPerPage(): int
    {
        return max(1, (int) config('builder360.pagination.default_max_per_page', 50));
    }

    public function defaultPerPage(?int $requestedPerPage = null): int
    {
        return $this->perPage(
            $requestedPerPage,
            $this->configuredDefaultPerPage(),
            $this->defaultMaxPerPage(),
        );
    }

    public function workspacePerPage(?int $requestedPerPage = null): int
    {
        return $this->perPage(
            $requestedPerPage,
            $this->configuredWorkspacePerPage(),
            $this->defaultMaxPerPage(),
        );
    }

    public function largeMaxPerPage(): int
    {
        return max(1, (int) config('builder360.pagination.large_max_per_page', 100));
    }

    public function largePerPage(?int $requestedPerPage = null): int
    {
        return $this->perPage(
            $requestedPerPage,
            $this->configuredLargePerPage(),
            $this->largeMaxPerPage(),
        );
    }

    public function absoluteMaxPerPage(): int
    {
        return max(1, (int) config('builder360.pagination.absolute_max_per_page', 100));
    }

    public function absoluteCeiling(): int
    {
        return max(1, (int) config('builder360.pagination.absolute_ceiling', 250));
    }

    public function configuredDefaultPerPage(): int
    {
        return max(1, (int) config('builder360.pagination.default_per_page', 15));
    }

    public function configuredWorkspacePerPage(): int
    {
        return max(1, (int) config('builder360.pagination.workspace_per_page', 25));
    }

    public function configuredLargePerPage(): int
    {
        return max(1, (int) config('builder360.pagination.large_per_page', 50));
    }

    /**
     * @return array<int, string>
     */
    public function defaultRule(): array
    {
        return $this->rule($this->defaultMaxPerPage());
    }

    /**
     * @return array<int, string>
     */
    public function largeRule(): array
    {
        return $this->rule($this->largeMaxPerPage());
    }

    /**
     * @return array<int, string>
     */
    public function rule(int $maxPerPage): array
    {
        return ['nullable', 'integer', 'min:1', 'max:'.max(1, $maxPerPage)];
    }

    private function perPage(?int $requestedPerPage, int $fallbackPerPage, int $maxPerPage): int
    {
        if ($requestedPerPage !== null && $requestedPerPage > 0) {
            return min($requestedPerPage, $maxPerPage);
        }

        return min($fallbackPerPage, $maxPerPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function readiness(): array
    {
        $defaultMaxPerPage = $this->defaultMaxPerPage();
        $largeMaxPerPage = $this->largeMaxPerPage();
        $absoluteMaxPerPage = $this->absoluteMaxPerPage();
        $absoluteCeiling = $this->absoluteCeiling();
        $defaultPerPage = $this->configuredDefaultPerPage();
        $workspacePerPage = $this->configuredWorkspacePerPage();
        $largePerPage = $this->configuredLargePerPage();

        $requirements = [
            'default_per_page_positive' => $defaultPerPage > 0,
            'workspace_per_page_positive' => $workspacePerPage > 0,
            'large_per_page_positive' => $largePerPage > 0,
            'default_per_page_within_default_max' => $defaultPerPage <= $defaultMaxPerPage,
            'workspace_per_page_within_default_max' => $workspacePerPage <= $defaultMaxPerPage,
            'large_per_page_within_large_max' => $largePerPage <= $largeMaxPerPage,
            'default_positive' => $defaultMaxPerPage > 0,
            'large_positive' => $largeMaxPerPage > 0,
            'absolute_positive' => $absoluteMaxPerPage > 0,
            'default_not_above_large' => $defaultMaxPerPage <= $largeMaxPerPage,
            'large_not_above_absolute' => $largeMaxPerPage <= $absoluteMaxPerPage,
            'absolute_not_above_ceiling' => $absoluteMaxPerPage <= $absoluteCeiling,
            'absolute_ceiling_operationally_safe' => $absoluteCeiling <= 250,
        ];
        $ready = ! in_array(false, $requirements, true);

        return [
            'status' => $ready ? 'ok' : 'degraded',
            'default_max_per_page' => $defaultMaxPerPage,
            'large_max_per_page' => $largeMaxPerPage,
            'absolute_max_per_page' => $absoluteMaxPerPage,
            'absolute_ceiling' => $absoluteCeiling,
            'default_per_page' => $defaultPerPage,
            'workspace_per_page' => $workspacePerPage,
            'large_per_page' => $largePerPage,
            'requirements' => $requirements,
            'failure' => $ready ? null : $this->failureReason($requirements),
        ];
    }

    /**
     * @param  array<string, bool>  $requirements
     */
    private function failureReason(array $requirements): ?string
    {
        foreach ($requirements as $requirement => $passed) {
            if (! $passed) {
                return 'pagination_'.$requirement;
            }
        }

        return null;
    }
}
