<?php

namespace App\Services\Security;

use App\Models\Company;

final class ActiveCompanyResolver
{
    private bool $resolved = false;

    private ?Company $company = null;

    public function enabled(): bool
    {
        return (bool) config('builder360.single_company.enabled', true);
    }

    public function configuredCode(): ?string
    {
        $code = trim((string) config('builder360.single_company.code', ''));

        return $code !== '' ? $code : null;
    }

    public function resolve(): ?Company
    {
        if (! $this->enabled()) {
            return null;
        }

        if ($this->resolved) {
            return $this->company;
        }

        $this->resolved = true;
        $code = $this->configuredCode();

        if ($code === null) {
            return null;
        }

        $this->company = Company::query()
            ->where('code', $code)
            ->where('status', 'active')
            ->first();

        return $this->company;
    }

    public function companyId(): ?int
    {
        return $this->resolve()?->getKey();
    }

    public function allows(int|string|null $companyId): bool
    {
        $activeCompanyId = $this->companyId();

        return $activeCompanyId !== null
            && $companyId !== null
            && (int) $companyId === $activeCompanyId;
    }
}
