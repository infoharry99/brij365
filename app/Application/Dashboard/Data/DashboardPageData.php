<?php

namespace App\Application\Dashboard\Data;

final readonly class DashboardPageData
{
    /**
     * @param  array<string, mixed>  $dashboard
     * @param  array<string, mixed>  $navigationContext
     */
    public function __construct(
        public array $dashboard,
        public array $navigationContext,
    ) {}
}
